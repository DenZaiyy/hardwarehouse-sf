<?php

declare(strict_types=1);

namespace App\DTO\Configurator;

use App\DTO\Api\Products\ProductSpecDto;
use App\Enum\ComponentSlot;

/**
 * Sélection en cours du configurateur, gardée en session : pour chaque emplacement, les produits choisis et leur
 * quantité. Les règles qui dépendent des caractéristiques des produits (compatibilité, nombre de barrettes admis par
 * la carte mère) sont vérifiées par ConfiguratorCompatibilityManager et ConfiguratorCompletenessManager, sur les
 * produits relus dans l'API.
 *
 * @phpstan-type ConfiguratorLine array{productId: string, quantity: int}
 */
final class ConfiguratorState
{
    /** @var array<string, list<ConfiguratorLine>> lignes choisies, indexées par emplacement (ComponentSlot::value) */
    private array $lines = [];

    /** Choisit un produit. Il remplace le précédent, sauf pour les disques, qui s'ajoutent jusqu'à maxReferences(). */
    public function choose(ComponentSlot $slot, string $productId): bool
    {
        if (null !== $this->position($slot, $productId)) {
            return true;
        }

        $lines = $this->lines[$slot->value] ?? [];
        if (1 === $slot->maxReferences()) {
            $lines = [];
        } elseif (\count($lines) >= $slot->maxReferences()) {
            return false;
        }

        $lines[] = ['productId' => $productId, 'quantity' => 1];
        $this->lines[$slot->value] = $lines;

        return true;
    }

    public function remove(ComponentSlot $slot, string $productId): void
    {
        $lines = array_values(array_filter(
            $this->lines[$slot->value] ?? [],
            static fn (array $line): bool => $line['productId'] !== $productId,
        ));

        if ([] === $lines) {
            unset($this->lines[$slot->value]);
        } else {
            $this->lines[$slot->value] = $lines;
        }
    }

    /**
     * Quantité d'un produit déjà choisi, au moins 1. Le maximum dépend de la carte mère : il est vérifié par
     * ConfiguratorCompletenessManager.
     */
    public function setQuantity(ComponentSlot $slot, string $productId, int $quantity): bool
    {
        $position = $this->position($slot, $productId);
        if ($quantity < 1 || null === $position) {
            return false;
        }

        $lines = $this->lines[$slot->value] ?? [];
        $lines[$position] = ['productId' => $productId, 'quantity' => $quantity];
        $this->lines[$slot->value] = $lines;

        return true;
    }

    /**
     * @return list<ConfiguratorLine>
     */
    public function lines(ComponentSlot $slot): array
    {
        return $this->lines[$slot->value] ?? [];
    }

    /**
     * @return list<string> identifiants des produits choisis, chacun une fois, pour la lecture groupée de l'API
     */
    public function productIds(): array
    {
        $ids = [];
        foreach ($this->lines as $lines) {
            foreach ($lines as $line) {
                $ids[] = $line['productId'];
            }
        }

        return array_values(array_unique($ids));
    }

    public function isEmpty(): bool
    {
        return [] === $this->lines;
    }

    /**
     * Premier produit relu de chaque emplacement, la forme qu'attend ConfiguratorCompatibilityManager (aucune de ses
     * règles ne porte sur les disques, seul emplacement à plusieurs références).
     *
     * @param array<string, ProductSpecDto> $products produits relus dans l'API, indexés par identifiant
     *
     * @return array<string, ProductSpecDto>
     */
    public function productsBySlot(array $products): array
    {
        $bySlot = [];
        foreach ($this->linesWith($products) as $slot => $lines) {
            $bySlot[$slot] = $lines[0]['product'];
        }

        return $bySlot;
    }

    /**
     * Lignes dont le produit a été relu, avec leur quantité, la forme qu'attend ConfiguratorCompletenessManager. Un
     * produit absent de la réponse de l'API (supprimé du catalogue) est laissé de côté.
     *
     * @param array<string, ProductSpecDto> $products produits relus dans l'API, indexés par identifiant
     *
     * @return array<string, non-empty-list<array{product: ProductSpecDto, quantity: int}>>
     */
    public function linesWith(array $products): array
    {
        $resolved = [];
        foreach ($this->lines as $slot => $lines) {
            foreach ($lines as $line) {
                $product = $products[$line['productId']] ?? null;
                if (null !== $product) {
                    $resolved[$slot][] = ['product' => $product, 'quantity' => $line['quantity']];
                }
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, list<ConfiguratorLine>>
     */
    public function toArray(): array
    {
        return $this->lines;
    }

    /**
     * Relit la session. Un emplacement inconnu ou une ligne mal formée, laissés par une version antérieure, sont
     * ignorés, et chaque ligne repasse par choose() et setQuantity() : leurs règles valent aussi pour les données relues.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $state = new self();
        foreach ($data as $slotValue => $lines) {
            $slot = \is_string($slotValue) ? ComponentSlot::tryFrom($slotValue) : null;
            if (null === $slot || !\is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $productId = \is_array($line) ? ($line['productId'] ?? null) : null;
                $quantity = \is_array($line) ? ($line['quantity'] ?? null) : null;
                if (!\is_string($productId) || !\is_int($quantity) || $quantity < 1) {
                    continue;
                }

                $state->choose($slot, $productId);
                $state->setQuantity($slot, $productId, $quantity);
            }
        }

        return $state;
    }

    private function position(ComponentSlot $slot, string $productId): ?int
    {
        foreach ($this->lines[$slot->value] ?? [] as $position => $line) {
            if ($line['productId'] === $productId) {
                return $position;
            }
        }

        return null;
    }
}
