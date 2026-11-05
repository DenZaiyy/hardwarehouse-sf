<?php

declare(strict_types=1);

namespace App\Tests\Service\Configurator;

use App\Enum\ComponentSlot;
use App\Service\Configurator\ConfiguratorStateManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * La sélection du configurateur vit dans la session : le configurateur s'utilise sans compte, comme le panier.
 */
final class ConfiguratorStateManagerTest extends TestCase
{
    private Session $session;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);
    }

    public function testSelectionIsFoundAgainOnTheNextRequest(): void
    {
        $state = (new ConfiguratorStateManager($this->requestStack))->getState();
        $state->choose(ComponentSlot::CPU, 'cpu-a');
        (new ConfiguratorStateManager($this->requestStack))->saveState($state);

        $next = (new ConfiguratorStateManager($this->requestStack))->getState();

        self::assertSame(['cpu-a'], $next->productIds());
    }

    public function testUnreadableSessionDataGivesAnEmptySelection(): void
    {
        $this->session->set(ConfiguratorStateManager::SESSION_KEY, 'cpu-a');

        self::assertTrue((new ConfiguratorStateManager($this->requestStack))->getState()->isEmpty());
    }

    public function testResetForgetsTheSelection(): void
    {
        $manager = new ConfiguratorStateManager($this->requestStack);
        $state = $manager->getState();
        $state->choose(ComponentSlot::CPU, 'cpu-a');
        $manager->saveState($state);

        $manager->reset();

        self::assertTrue($manager->getState()->isEmpty());
    }
}
