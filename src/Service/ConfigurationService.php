<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Api\Products\ProductSpecDto;
use App\DTO\Configurator\ConfiguratorIssue;
use App\DTO\Configurator\ConfiguratorState;
use App\Entity\Configuration;
use App\Entity\ConfigurationItem;
use App\Enum\ComponentSlot;
use App\Service\Configurator\ConfiguratorCatalogManager;
use App\Service\Configurator\ConfiguratorCompatibilityManager;
use App\Service\Configurator\ConfiguratorCompletenessManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Configurations du client : sauvegarde de la sélection (Mes configurations), réouverture et ajout au panier. Les
 * composants sont toujours relus dans l'API d'abord, en un seul appel : prix, stock et compatibilité changent avec le
 * catalogue, c'est pourquoi aucun statut de validité n'est enregistré.
 */
final readonly class ConfigurationService
{
    public function __construct(
        private ConfiguratorCatalogManager $catalog,
        private ConfiguratorCompatibilityManager $compatibility,
        private ConfiguratorCompletenessManager $completeness,
        private CartService $cartService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Composants choisis relus dans l'API, en un seul appel, et tout ce qui les tient hors du panier, sauf le stock
     * (vérifié à l'ajout, avec le contenu du panier) : composants indisponibles, incompatibilités, emplacements
     * obligatoires vides et quantités hors limites. Le configurateur s'en sert pour son affichage.
     *
     * @return array{products: array<string, ProductSpecDto>, issues: list<ConfiguratorIssue>}
     */
    public function review(ConfiguratorState $state): array
    {
        $products = $this->catalog->specs($state->productIds());

        return ['products' => $products, 'issues' => [
            ...$this->unavailable($state, $products),
            ...$this->compatibility->violations($state->productsBySlot($products)),
            ...$this->completeness->issues($state->linesWith($products)),
        ]];
    }

    /**
     * Enregistre la sélection dans la configuration, nouvelle ou rouverte, avec les instantanés des produits relus,
     * puis la sélection continue de modifier cette configuration. Une configuration incomplète peut être sauvegardée,
     * pas une configuration incompatible ni un composant indisponible.
     *
     * @return list<ConfiguratorIssue> ce qui empêche la sauvegarde, vide si elle est faite
     */
    public function save(Configuration $configuration, ConfiguratorState $state): array
    {
        $products = $this->catalog->specs($state->productIds());
        $issues = [
            ...$this->unavailable($state, $products),
            ...$this->compatibility->violations($state->productsBySlot($products)),
        ];
        if ([] !== $issues) {
            return $issues;
        }

        foreach ($configuration->getItems()->toArray() as $item) {
            $configuration->removeItem($item);
        }
        foreach ($state->linesWith($products) as $slot => $lines) {
            foreach ($lines as ['product' => $product, 'quantity' => $quantity]) {
                $configuration->addItem($this->item(ComponentSlot::from($slot), $product, $quantity));
            }
        }
        if (null !== $configuration->getId()) {
            // Seuls les composants changent : rien ne déclencherait le PreUpdate de la configuration elle-même
            $configuration->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        }

        $this->entityManager->persist($configuration);
        $this->entityManager->flush();
        $state->setConfigurationId((int) $configuration->getId());

        return [];
    }

    /** Sélection d'une configuration sauvegardée, à remettre en session pour la modifier ou l'ajouter au panier. */
    public function load(Configuration $configuration): ConfiguratorState
    {
        $state = new ConfiguratorState();
        $configurationId = $configuration->getId();
        if (null !== $configurationId) {
            $state->setConfigurationId($configurationId);
        }
        foreach ($configuration->getItems() as $item) {
            $slot = $item->getSlot();
            $productId = $item->getProductId();
            $quantity = $item->getQuantity();
            if (null === $slot || null === $productId || null === $quantity) {
                continue;
            }

            $state->choose($slot, $productId);
            $state->setQuantity($slot, $productId, $quantity);
        }

        return $state;
    }

    /**
     * Ajoute au panier une configuration complète, compatible et en stock, composant par composant : CartService relit
     * chaque produit et applique ses propres règles (prix, remises, stock). Si un composant pose problème, rien n'est
     * ajouté, pour ne jamais laisser une moitié de configuration dans le panier.
     *
     * @return list<ConfiguratorIssue> ce qui empêche l'ajout, vide si les composants sont au panier
     *
     * @throws \RuntimeException message destiné au client si le catalogue ne répond plus pendant l'ajout
     */
    public function addToCart(ConfiguratorState $state): array
    {
        ['products' => $products, 'issues' => $issues] = $this->review($state);
        $lines = $state->linesWith($products);
        $issues = [...$issues, ...$this->shortOfStock($lines)];
        if ([] !== $issues) {
            return $issues;
        }

        foreach ($lines as $slotLines) {
            foreach ($slotLines as ['product' => $product, 'quantity' => $quantity]) {
                $this->cartService->addProduct($product->slug, $quantity);
            }
        }

        return [];
    }

    /**
     * Composants introuvables (supprimés du catalogue), retirés de la vente, ou d'une catégorie qui ne fournit pas leur
     * emplacement (requête forgée) : ils ne peuvent ni être sauvegardés ni partir au panier.
     *
     * @param array<string, ProductSpecDto> $products
     *
     * @return list<ConfiguratorIssue>
     */
    private function unavailable(ConfiguratorState $state, array $products): array
    {
        $issues = [];
        foreach (ComponentSlot::cases() as $slot) {
            foreach ($state->lines($slot) as $line) {
                $product = $products[$line['productId']] ?? null;
                if (null === $product || !$product->active || !\in_array($product->categorySlug(), $slot->categorySlugs(), true)) {
                    $issues[] = new ConfiguratorIssue([$slot], 'unavailable');
                    break;
                }
            }
        }

        return $issues;
    }

    /**
     * Même règle que le panier : la quantité demandée, ajoutée à celle déjà au panier, ne dépasse pas le stock.
     *
     * @param array<string, non-empty-list<array{product: ProductSpecDto, quantity: int}>> $lines
     *
     * @return list<ConfiguratorIssue>
     */
    private function shortOfStock(array $lines): array
    {
        $inCart = array_column($this->cartService->getCart(), 'quantity', 'productId');
        $issues = [];
        foreach ($lines as $slot => $slotLines) {
            foreach ($slotLines as ['product' => $product, 'quantity' => $quantity]) {
                if ($quantity + ($inCart[$product->id] ?? 0) > ($product->stock->quantity ?? 0)) {
                    $issues[] = new ConfiguratorIssue([ComponentSlot::from($slot)], 'stock');
                    break;
                }
            }
        }

        return $issues;
    }

    private function item(ComponentSlot $slot, ProductSpecDto $product, int $quantity): ConfigurationItem
    {
        return (new ConfigurationItem())
            ->setSlot($slot)
            ->setProductId($product->id)
            ->setQuantity($quantity)
            ->setProductNameSnapshot($product->name)
            ->setProductSlugSnapshot($product->slug)
            ->setProductImageSnapshot($product->thumbnail)
            ->setUnitPriceSnapshot(number_format($product->effectivePrice(), 2, '.', ''));
    }
}
