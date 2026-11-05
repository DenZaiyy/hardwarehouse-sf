<?php

declare(strict_types=1);

namespace App\DTO\Configurator;

use App\Enum\ComponentSlot;

/**
 * Problème d'une configuration : les emplacements en cause et la règle non respectée. La règle est la clé d'une
 * règle de compatibilité (socket, power…) ou un manque de complétude (missing, references, quantity).
 */
final readonly class ConfiguratorIssue
{
    /**
     * @param list<ComponentSlot> $slots
     */
    public function __construct(
        public array $slots,
        public string $rule,
    ) {
    }
}
