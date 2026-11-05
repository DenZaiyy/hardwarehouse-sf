<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\DTO\Configurator\ConfiguratorState;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sélection en cours du configurateur, gardée en session comme l'état du tunnel de commande (CheckoutStateManager) :
 * le configurateur s'utilise sans compte, seule la sauvegarde demande d'être connecté.
 */
final readonly class ConfiguratorStateManager
{
    public const string SESSION_KEY = 'configurator_state';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function getState(): ConfiguratorState
    {
        $data = $this->requestStack->getSession()->get(self::SESSION_KEY, []);

        return ConfiguratorState::fromArray(\is_array($data) ? $data : []);
    }

    public function saveState(ConfiguratorState $state): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $state->toArray());
    }

    public function reset(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }
}
