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
use App\Tests\Support\CreatesShopEntities;
use App\Tests\Support\FakesConfiguratorCatalog;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
