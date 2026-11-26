<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DTO\Configurator\ConfiguratorState;
use App\Entity\CartLine;
use App\Entity\Configuration;
use App\Entity\ConfigurationItem;
use App\Entity\User;
use App\Enum\ComponentSlot;
use App\Service\ConfigurationService;
use App\Service\Configurator\ConfiguratorStateManager;
use App\Tests\Support\CreatesShopEntities;
use App\Tests\Support\FakesConfiguratorCatalog;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;

/**
 * Le configurateur est ouvert à tous. Mes configurations et chaque configuration sauvegardée ne le sont qu'à leur
 * propriétaire, et les actions qui les modifient passent par un formulaire protégé contre le CSRF.
 */
final class ConfigurationControllerTest extends WebTestCase
{
    use CreatesShopEntities;
    use FakesConfiguratorCatalog;

    public function testConfiguratorIsOpenToGuests(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/configurations/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Construis ton ordinateur');
    }

    public function testGuestIsAskedToSignInForTheirConfigurations(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/configurations');

        self::assertResponseRedirects();
        self::assertStringEndsWith('/connexion', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testCustomerOnlySeesTheirOwnConfigurations(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner');
        $this->savedConfiguration($owner, 'Poste de jeu');
        $this->savedConfiguration($this->createUser('other'), 'Poste bureautique');

        $client->loginUser($owner);
        $client->request('GET', '/fr/configurations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Poste de jeu');
        self::assertSelectorTextNotContains('main', 'Poste bureautique');
    }

    public function testOwnerSeesTheSavedComponents(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner');
        $configuration = $this->savedConfiguration($owner, 'Poste de jeu');

        $client->loginUser($owner);
        $client->request('GET', \sprintf('/fr/configurations/%d', $configuration->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Poste de jeu');
        self::assertSelectorTextContains('main', 'Produit '.$this->firstProductId($configuration));
    }

    public function testAnotherCustomerCannotOpenTheConfiguration(): void
    {
        $client = static::createClient();
        $configuration = $this->savedConfiguration($this->createUser('owner'), 'Poste de jeu');

        $client->loginUser($this->createUser('intruder'));
        $client->request('GET', \sprintf('/fr/configurations/%d', $configuration->getId()));

        // 404 plutôt que 403 : l'existence de la configuration d'un autre client n'est pas révélée
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnotherCustomerCannotDeleteTheConfiguration(): void
    {
        $client = static::createClient();
        $configuration = $this->savedConfiguration($this->createUser('owner'), 'Poste de jeu');
        $configurationId = $configuration->getId();

        $client->loginUser($this->createUser('intruder'));
        $client->request('POST', \sprintf('/fr/configurations/%d/delete', $configurationId));

        self::assertResponseStatusCodeSame(404);
        $this->entityManager()->clear();
        self::assertNotNull($this->entityManager()->find(Configuration::class, $configurationId));
    }

    public function testOwnerDeletesTheConfiguration(): void
    {
        $client = static::createClient();
        $configurationId = $this->openOwnConfiguration($client)->getId();

        $client->submitForm('Supprimer');

        self::assertResponseRedirects('/fr/configurations');
        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->find(Configuration::class, $configurationId));
    }

    public function testDeletionWithoutAValidTokenIsIgnored(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner');
        $configurationId = $this->savedConfiguration($owner, 'Poste de jeu')->getId();

        $client->loginUser($owner);
        $client->request('POST', \sprintf('/fr/configurations/%d/delete', $configurationId), ['_token' => 'jeton-forgé']);

        $this->entityManager()->clear();
        self::assertNotNull($this->entityManager()->find(Configuration::class, $configurationId));
    }

    public function testOpenedConfigurationIsEditedInTheConfigurator(): void
    {
        $client = static::createClient();
        $configuration = $this->openOwnConfiguration($client);

        $client->submitForm('Modifier');

        self::assertResponseRedirects('/fr/configurations/new');
        $client->followRedirect();
        // Le nom est repris : la sauvegarde mettra à jour cette configuration
        self::assertSelectorExists('input[data-model="norender|name"][value="Poste de jeu"]');
        self::assertSelectorTextContains('aside', 'Produit '.$this->firstProductId($configuration));
    }

    public function testCompleteConfigurationGoesToTheCart(): void
    {
        $client = static::createClient();
        $this->openOwnConfiguration($client);

        $client->submitForm('Ajouter au panier');

        self::assertResponseRedirects('/fr/cart');
    }

    public function testConfigurationWithAConflictStaysOutOfTheCart(): void
    {
        $client = static::createClient();
        $owner = $this->createUser('owner');
        $cpu = $this->apiHasComponent('processeurs', self::AM5_CPU);
        // Compatible à la sauvegarde, la carte mère ne l'est plus depuis une correction de sa fiche dans le catalogue
        $motherboard = $this->apiHasComponent('cartes-meres', ['socket' => ['value' => 'LGA1700']]);
        $configuration = (new Configuration())->setName('Poste de jeu')->setUser($owner)
            ->addItem($this->item(ComponentSlot::CPU, $cpu))
            ->addItem($this->item(ComponentSlot::MOTHERBOARD, $motherboard));
        $this->entityManager()->persist($configuration);
        $this->entityManager()->flush();

        $client->loginUser($owner);
        $client->request('GET', \sprintf('/fr/configurations/%d', $configuration->getId()));
        $client->submitForm('Ajouter au panier');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', "n'ont pas le même socket");
        self::assertSame([], $this->entityManager()->getRepository(CartLine::class)->findBy(['productId' => $cpu]));
    }

    public function testProductPageOffersToAddTheProductToTheConfiguration(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorTextSame('form[action="/fr/configurations/ajouter"] button[type="submit"]', 'Ajouter à ma configuration');
    }

    public function testProductOutsideTheConfiguratorHasNoConfigurationButton(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug, category: 'ecrans');

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorNotExists('form[action="/fr/configurations/ajouter"]');
    }

    public function testAddedProductTakesItsSlotAndOpensTheConfiguratorOnItsCategory(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug, category: 'watercooling');

        $client->submit($this->addToConfigurationForm($client, $slug));

        self::assertResponseRedirects('/fr/configurations/new?categorie=watercooling');
        self::assertSame([['productId' => $productId, 'quantity' => 1]], $this->configuratorLines($client)['cooler'] ?? null);
    }

    public function testAddedProductReplacesTheOneAlreadyChosen(): void
    {
        $client = static::createClient();
        $first = self::newProductSlug();
        $second = self::newProductSlug();
        $this->apiHasProduct($first);
        $secondId = $this->apiHasProduct($second);

        $client->submit($this->addToConfigurationForm($client, $first));
        $client->submit($this->addToConfigurationForm($client, $second));

        self::assertSame([['productId' => $secondId, 'quantity' => 1]], $this->configuratorLines($client)['cpu'] ?? null);
    }

    public function testStorageKeepsAtMostThreeDrives(): void
    {
        $client = static::createClient();
        $slugs = [self::newProductSlug(), self::newProductSlug(), self::newProductSlug(), self::newProductSlug()];
        foreach ($slugs as $slug) {
            $this->apiHasProduct($slug, category: 'ssd');
        }

        foreach ($slugs as $slug) {
            $client->submit($this->addToConfigurationForm($client, $slug));
        }

        self::assertResponseRedirects('/fr/produits/'.$slugs[3]);
        self::assertCount(3, $this->configuratorLines($client)['storage'] ?? []);
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-danger[role="alert"]', 'Trois disques au plus');
    }

    public function testProductOutsideTheConfiguratorCannotBeAdded(): void
    {
        $client = static::createClient();
        $component = self::newProductSlug();
        $screen = self::newProductSlug();
        $this->apiHasProduct($component);
        $this->apiHasProduct($screen, category: 'ecrans');

        $client->submit($this->addToConfigurationForm($client, $component), ['slug' => $screen]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->configuratorLines($client));
    }

    public function testMalformedSlugIsNotSentToTheCatalogue(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $client->submit($this->addToConfigurationForm($client, $slug), ['slug' => '../categories']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testAddingWithoutAValidTokenChangesNothing(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $client->request('POST', '/fr/configurations/ajouter', ['slug' => $slug, '_csrf_token' => 'invalide']);

        self::assertResponseRedirects('/fr/produits/'.$slug);
        self::assertSame([], $this->configuratorLines($client));
    }

    public function testConfiguratorOpensOnTheCategoryAsked(): void
    {
        $client = static::createClient();
        $this->emptyConfiguratorCategories();

        $client->request('GET', '/fr/configurations/new?categorie=watercooling');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button[data-live-category-param="watercooling"][aria-pressed="true"]');
    }

    private function addToConfigurationForm(KernelBrowser $client, string $slug): Form
    {
        return $client->request('GET', '/fr/produits/'.$slug)->filter('form[action="/fr/configurations/ajouter"]')->form();
    }

    /**
     * @return array<string, list<array{productId: string, quantity: int}>> sélection en session, par emplacement
     */
    private function configuratorLines(KernelBrowser $client): array
    {
        $state = $client->getRequest()->getSession()->get(ConfiguratorStateManager::SESSION_KEY, []);

        /** @var array<string, list<array{productId: string, quantity: int}>> $lines */
        $lines = \is_array($state) && \is_array($state['lines'] ?? null) ? $state['lines'] : [];

        return array_filter($lines);
    }

    /** Connecte le client sur la page d'une de ses configurations, complète et compatible. */
    private function openOwnConfiguration(KernelBrowser $client): Configuration
    {
        $owner = $this->createUser('owner');
        $configuration = $this->savedConfiguration($owner, 'Poste de jeu', $this->completeBuild());

        $client->loginUser($owner);
        $client->request('GET', \sprintf('/fr/configurations/%d', $configuration->getId()));

        return $configuration;
    }

    private function firstProductId(Configuration $configuration): string
    {
        $item = $configuration->getItems()->first();
        self::assertInstanceOf(ConfigurationItem::class, $item);

        return (string) $item->getProductId();
    }

    private function item(ComponentSlot $slot, string $productId): ConfigurationItem
    {
        return (new ConfigurationItem())
            ->setSlot($slot)
            ->setProductId($productId)
            ->setQuantity(1)
            ->setProductNameSnapshot('Produit '.$productId)
            ->setProductSlugSnapshot($slot->value.'-'.$productId)
            ->setUnitPriceSnapshot('374.92');
    }

    private function savedConfiguration(User $owner, string $name, ?ConfiguratorState $state = null): Configuration
    {
        if (null === $state) {
            $state = new ConfiguratorState();
            $state->choose(ComponentSlot::CPU, $this->apiHasComponent('processeurs', self::AM5_CPU));
        }

        $configuration = (new Configuration())->setName($name)->setUser($owner);
        $service = static::getContainer()->get(ConfigurationService::class);
        self::assertInstanceOf(ConfigurationService::class, $service);
        self::assertSame([], $service->save($configuration, $state));

        return $configuration;
    }
}
