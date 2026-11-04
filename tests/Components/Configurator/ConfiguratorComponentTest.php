<?php

declare(strict_types=1);

namespace App\Tests\Components\Configurator;

use App\DTO\Configurator\ConfiguratorState;
use App\Entity\CartLine;
use App\Entity\Configuration;
use App\Entity\ConfigurationItem;
use App\Entity\User;
use App\Enum\ComponentSlot;
use App\Service\Configurator\ConfiguratorStateManager;
use App\Tests\Http\ApiMockResponseFactory;
use App\Tests\Support\CreatesShopEntities;
use App\Tests\Support\FakesConfiguratorCatalog;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Le configurateur ne liste que des produits compatibles avec la sélection gardée en session, ne retient que des
 * identifiants de produit valides, et ne sauvegarde que dans une configuration du client connecté.
 */
final class ConfiguratorComponentTest extends WebTestCase
{
    use CreatesShopEntities;
    use FakesConfiguratorCatalog;
    use InteractsWithLiveComponents;

    public function testChosenProcessorStaysInTheSelectionAndTheMotherboardsOpen(): void
    {
        $client = static::createClient();
        $cpu = $this->apiHasComponent('processeurs', self::AM5_CPU);
        $configurator = $this->configurator($client);

        $configurator->call('choose', ['productId' => $cpu]);
        $configurator->refresh();

        self::assertStringContainsString('Produit '.$cpu, $configurator->render()->crawler()->filter('aside')->text());
        // Le ventirad est fourni avec le processeur : le prochain composant à choisir est la carte mère
        self::assertSame(ComponentSlot::MOTHERBOARD, $this->displayedSlot($configurator));
    }

    public function testOnlyMotherboardsWithTheProcessorSocketAreRequested(): void
    {
        $client = static::createClient();
        $cpu = $this->apiHasComponent('processeurs', self::AM5_CPU);
        $motherboard = $this->apiHasComponent('cartes-meres', ['socket' => ['value' => 'AM5']]);
        $configurator = $this->configurator($client);

        $configurator->call('choose', ['productId' => $cpu]);

        $queries = $this->queriesTo('categories/cartes-meres/products');
        self::assertNotSame([], $queries);
        $query = urldecode((string) end($queries));
        self::assertStringContainsString('attr[socket]=AM5', $query);
        self::assertStringContainsString('active=true', $query);
        self::assertStringContainsString('Produit '.$motherboard, $configurator->render()->crawler()->filter('section')->text());
    }

    public function testStorageListsHardDrivesButNoUnrelatedCategory(): void
    {
        $client = static::createClient();
        $configurator = $this->configurator($client);

        $configurator->call('selectSlot', ['slot' => 'storage', 'category' => 'disques-durs']);
        self::assertCount(1, $this->queriesTo('categories/disques-durs/products'));

        // Catégorie venue du navigateur qui ne fournit pas l'emplacement : la liste reprend sa première catégorie
        $configurator->call('selectSlot', ['slot' => 'storage', 'category' => 'ecrans']);
        self::assertSame([], $this->queriesTo('categories/ecrans/products'));
        self::assertCount(1, $this->queriesTo('categories/ssd/products'));
    }

    public function testUnknownSlotIsIgnored(): void
    {
        $client = static::createClient();
        $configurator = $this->configurator($client);

        $configurator->call('selectSlot', ['slot' => 'fan']);

        self::assertSame(ComponentSlot::CPU, $this->displayedSlot($configurator));
    }

    public function testListPagesStartAtOne(): void
    {
        $client = static::createClient();
        $configurator = $this->configurator($client);

        $configurator->call('goToPage', ['page' => 2]);
        $configurator->call('goToPage', ['page' => -3]);

        $queries = $this->queriesTo('categories/processeurs/products');
        self::assertCount(3, $queries);
        self::assertStringContainsString('page=2', $queries[1]);
        self::assertStringContainsString('page=1', $queries[2]);
    }

    public function testForgedProductIdIsIgnored(): void
    {
        $client = static::createClient();
        $configurator = $this->configurator($client);

        $configurator->call('choose', ['productId' => '../../admin']);

        self::assertTrue($this->storedState($client)->isEmpty());
    }

    public function testRemovedComponentLeavesTheSelection(): void
    {
        $client = static::createClient();
        $cpu = $this->apiHasComponent('processeurs', self::AM5_CPU);
        $configurator = $this->configurator($client);
        $configurator->call('choose', ['productId' => $cpu]);

        $configurator->call('remove', ['slot' => 'cpu', 'productId' => $cpu]);

        self::assertTrue($this->storedState($client)->isEmpty());
    }

    public function testCatalogueOutageKeepsTheSelectionAndListsNothing(): void
    {
        $client = static::createClient();
        $cpu = $this->apiHasComponent('processeurs', self::AM5_CPU);
        $configurator = $this->configurator($client);
        $configurator->call('choose', ['productId' => $cpu]);

        $this->apiFailsOn('products');
        $listsBefore = \count($this->queriesTo('categories/cartes-meres/products'));
        $configurator->refresh();

        self::assertStringContainsString('Le catalogue ne répond pas', $configurator->render()->crawler()->text());
        self::assertSame([$cpu], $this->storedState($client)->productIds());
        // Jamais de liste non filtrée à la place de la liste compatible
        self::assertCount($listsBefore, $this->queriesTo('categories/cartes-meres/products'));
    }

    public function testGuestIsSentToTheLoginPageAndThenBackToTheConfigurator(): void
    {
        $client = static::createClient();
        $configurator = $this->configurator($client);

        $configurator->call('save');

        self::assertTrue($configurator->response()->isRedirect());
        self::assertStringEndsWith('/connexion', (string) $configurator->response()->headers->get('Location'));
        $targetPath = $this->session($client)->get('_security.main.target_path');
        self::assertIsString($targetPath);
        self::assertStringEndsWith('/configurations/new', $targetPath);
    }

    public function testSignedInCustomerSavesTheConfigurationUnderItsName(): void
    {
        $client = static::createClient();
        $customer = $this->createUser();
        $cpu = $this->apiHasComponent('processeurs', self::AM5_CPU);
        $configurator = $this->configurator($client)->actingAs($customer);
        $configurator->call('choose', ['productId' => $cpu]);

        $configurator->set('name', '  PC de jeu  ');
        $configurator->call('save');

        $configuration = $this->onlyConfigurationOf($customer);
        self::assertSame('PC de jeu', $configuration->getName());
        self::assertSame([$cpu], array_map(static fn (ConfigurationItem $item): ?string => $item->getProductId(), $configuration->getItems()->toArray()));
        self::assertSame($configuration->getId(), $this->storedState($client)->configurationId());
        self::assertStringContainsString('Configuration enregistrée', $configurator->render()->crawler()->text());
    }

    public function testConfigurationIsNotSavedWithoutAName(): void
    {
        $client = static::createClient();
        $customer = $this->createUser();
        $configurator = $this->configurator($client)->actingAs($customer);

        $configurator->call('save');

        self::assertSame([], $this->configurationsOf($customer));
        self::assertStringContainsString('Donnez un nom', $configurator->render()->crawler()->text());
    }

    public function testAnotherCustomersConfigurationIsNeverOverwritten(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner');
        $theirs = (new Configuration())->setName('Poste du propriétaire')->setUser($owner);
        $this->entityManager()->persist($theirs);
        $this->entityManager()->flush();
        $intruder = $this->createUser('intruder');
        $configurator = $this->configurator($client)->actingAs($intruder);
        // Session restée sur la configuration d'un autre compte (connexion par-dessus, sans déconnexion)
        $state = new ConfiguratorState();
        $state->setConfigurationId((int) $theirs->getId());
        $this->storeState($client, $state);

        $configurator->set('name', 'Mon poste');
        $configurator->call('save');

        $this->entityManager()->clear();
        self::assertSame('Poste du propriétaire', $this->entityManager()->find(Configuration::class, $theirs->getId())?->getName());
        self::assertSame('Mon poste', $this->onlyConfigurationOf($intruder)->getName());
    }

    public function testIncompatibleSelectionIsNotSavedAndTheConflictIsExplained(): void
    {
        $client = static::createClient();
        $customer = $this->createUser();
        $cpu = $this->apiHasComponent('processeurs', self::AM5_CPU);
        $motherboard = $this->apiHasComponent('cartes-meres', ['socket' => ['value' => 'LGA1700'], 'ddr_type' => ['value' => 'DDR5']]);
        $configurator = $this->configurator($client)->actingAs($customer);
        $configurator->call('choose', ['productId' => $cpu]);
        // Produit choisi hors de la liste filtrée (autre onglet, requête forgée) : la sauvegarde le refuse
        $configurator->call('choose', ['productId' => $motherboard]);

        $configurator->set('name', 'PC de jeu');
        $configurator->call('save');

        self::assertSame([], $this->configurationsOf($customer));
        self::assertStringContainsString("n'ont pas le même socket", $configurator->render()->crawler()->text());
    }

    public function testCompleteConfigurationGoesToTheCart(): void
    {
        $client = static::createClient();
        $configurator = $this->configurator($client);
        $this->storeState($client, $this->completeBuild());

        $configurator->call('addToCart');

        self::assertTrue($configurator->response()->isRedirect());
        self::assertStringEndsWith('/cart', (string) $configurator->response()->headers->get('Location'));
    }

    public function testIncompleteConfigurationStaysOutOfTheCart(): void
    {
        $client = static::createClient();
        $cpu = $this->apiHasComponent('processeurs', self::AM5_CPU);
        $configurator = $this->configurator($client);
        $configurator->call('choose', ['productId' => $cpu]);

        $configurator->call('addToCart');

        self::assertTrue($configurator->response()->isSuccessful());
        self::assertStringContainsString('Configuration incomplète', $configurator->render()->crawler()->text());
        self::assertSame([], $this->entityManager()->getRepository(CartLine::class)->findBy(['productId' => $cpu]));
    }

    public function testStartingANewConfigurationForgetsTheSelection(): void
    {
        $client = static::createClient();
        $cpu = $this->apiHasComponent('processeurs', self::AM5_CPU);
        $configurator = $this->configurator($client);
        $configurator->call('choose', ['productId' => $cpu]);

        $configurator->call('startNew');

        self::assertTrue($this->storedState($client)->isEmpty());
        self::assertSame(ComponentSlot::CPU, $this->displayedSlot($configurator));
    }

    /** Le premier montage a lieu hors requête HTTP : les actions passent ensuite par le client et sa session. */
    private function configurator(KernelBrowser $client): TestLiveComponent
    {
        $request = new Request();
        $request->setLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = static::getContainer()->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);

        return $this->createLiveComponent('Configurator:ConfiguratorComponent', client: $client);
    }

    /** Emplacement dont le dernier rendu affiche la liste (LiveProp slot). */
    private function displayedSlot(TestLiveComponent $configurator): ComponentSlot
    {
        $props = json_decode((string) $configurator->render()->crawler()->filter('[data-live-props-value]')->attr('data-live-props-value'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($props);
        self::assertIsString($props['slot']);

        return ComponentSlot::from($props['slot']);
    }

    /** @return list<string> paramètres de chaque lecture de cette ressource de l'API, dans l'ordre */
    private function queriesTo(string $path): array
    {
        $queries = [];
        foreach (ApiMockResponseFactory::requests() as $request) {
            if (str_ends_with($request['path'], $path)) {
                $queries[] = $request['query'];
            }
        }

        return $queries;
    }

    /** Session du client : celle où loginUser() range le jeton de connexion, créée au besoin. */
    private function session(KernelBrowser $client): SessionInterface
    {
        $factory = static::getContainer()->get('session.factory');
        self::assertInstanceOf(SessionFactoryInterface::class, $factory);
        $session = $factory->createSession();
        $cookie = $client->getCookieJar()->get($session->getName());
        if (null !== $cookie) {
            $session->setId($cookie->getValue());
        }
        $session->start();

        return $session;
    }

    private function storeState(KernelBrowser $client, ConfiguratorState $state): void
    {
        $session = $this->session($client);
        $session->set(ConfiguratorStateManager::SESSION_KEY, $state->toArray());
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    private function storedState(KernelBrowser $client): ConfiguratorState
    {
        $data = $this->session($client)->get(ConfiguratorStateManager::SESSION_KEY, []);

        return ConfiguratorState::fromArray(\is_array($data) ? $data : []);
    }

    /** @return list<Configuration> */
    private function configurationsOf(User $customer): array
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(Configuration::class)->findBy(['user' => $customer->getId()]);
    }

    private function onlyConfigurationOf(User $customer): Configuration
    {
        $configurations = $this->configurationsOf($customer);
        self::assertCount(1, $configurations);

        return $configurations[0];
    }
}
