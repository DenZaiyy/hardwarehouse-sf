<?php

declare(strict_types=1);

namespace App\Components\Configurator;

use App\DTO\Api\Categories\CategoryProductsDto;
use App\DTO\Api\PaginationMeta;
use App\DTO\Api\Products\ProductSpecDto;
use App\DTO\Configurator\ConfiguratorIssue;
use App\DTO\Configurator\ConfiguratorState;
use App\Entity\Configuration;
use App\Entity\User;
use App\Enum\ComponentSlot;
use App\Exception\Api\ApiException;
use App\Repository\ConfigurationRepository;
use App\Security\Voter\ConfigurationVoter;
use App\Service\ConfigurationService;
use App\Service\Configurator\ConfiguratorCatalogManager;
use App\Service\Configurator\ConfiguratorCompatibilityManager;
use App\Service\Configurator\ConfiguratorStateManager;
use App\Service\Pricing\PriceCalculator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Configurateur de PC. La sélection est gardée en session, donc accessible sans compte, et la liste n'affiche que les
 * produits compatibles avec elle. Chaque rendu relit les composants choisis dans l'API, en un seul appel : prix, stock
 * et compatibilité suivent le catalogue.
 */
#[AsLiveComponent('Configurator:ConfiguratorComponent')]
final class ConfiguratorComponent
{
    use DefaultActionTrait;
    use TargetPathTrait;

    /** Identifiant MongoDB : l'API refuse toute la lecture groupée si l'un des identifiants a un autre format. */
    private const string PRODUCT_ID_PATTERN = '/^[0-9a-f]{24}$/';

    /** Pare-feu de la boutique (config/packages/security.yaml), où la connexion range la page à rouvrir. */
    private const string FIREWALL = 'main';

    private const int NAME_MAX_LENGTH = 100;

    /** Emplacement dont la liste est affichée. */
    #[LiveProp]
    public ComponentSlot $slot = ComponentSlot::CPU;

    /** Catégorie affichée parmi celles de l'emplacement : le stockage en a deux (SSD et disques durs). */
    #[LiveProp]
    public string $category = 'processeurs';

    #[LiveProp]
    public int $page = 1;

    /** Nom sous lequel le client sauvegarde la configuration. */
    #[LiveProp(writable: true)]
    public string $name = '';

    /**
     * Ce qui a empêché la dernière sauvegarde ou le dernier ajout au panier : affiché le temps du rendu qui suit
     * l'action (ce n'est pas une LiveProp).
     *
     * @var list<ConfiguratorIssue>
     */
    public array $issues = [];

    /** Clé de traduction du message de réussite de la dernière action. */
    public ?string $notice = null;

    /**
     * Message d'échec de la dernière action : une clé de traduction, ou le message destiné au client que CartService
     * a levé (le filtre trans le rend tel quel).
     */
    public ?string $error = null;

    /** @var array{products: array<string, ProductSpecDto>, issues: list<ConfiguratorIssue>}|null */
    private ?array $review = null;

    private bool $reviewed = false;

    public function __construct(
        private readonly ConfiguratorStateManager $stateManager,
        private readonly ConfigurationService $configurationService,
        private readonly ConfiguratorCatalogManager $catalog,
        private readonly ConfiguratorCompatibilityManager $compatibility,
        private readonly ConfigurationRepository $configurationRepository,
        private readonly PriceCalculator $priceCalculator,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** Le configurateur s'ouvre sur le premier composant qui reste à choisir, sous le nom de la configuration rouverte. */
    public function mount(): void
    {
        $this->show($this->firstMissingSlot() ?? ComponentSlot::CPU);
        $this->name = $this->editedConfiguration($this->stateManager->getState())?->getName() ?? '';
    }

    #[LiveAction]
    public function selectSlot(#[LiveArg] string $slot, #[LiveArg] string $category = ''): void
    {
        // Les valeurs viennent du navigateur : seuls un emplacement connu et l'une de ses catégories sont retenus
        $selected = ComponentSlot::tryFrom($slot);
        if (null === $selected) {
            return;
        }

        $this->show($selected, $category);
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }

    #[LiveAction]
    public function choose(#[LiveArg] string $productId): void
    {
        if (1 !== preg_match(self::PRODUCT_ID_PATTERN, $productId)) {
            return;
        }

        // Un produit d'une autre catégorie, retiré de la vente ou incompatible est signalé par review() : le client le
        // voit et le remplace, la sauvegarde et le panier le refusent
        $state = $this->stateManager->getState();
        if (!$state->choose($this->slot, $productId)) {
            $this->error = 'configurator.storage_full';

            return;
        }
        $this->stateManager->saveState($state);

        if (1 === $this->slot->maxReferences()) {
            $this->show($this->firstMissingSlot() ?? $this->slot);
        }
    }

    #[LiveAction]
    public function remove(#[LiveArg] string $slot, #[LiveArg] string $productId): void
    {
        $selected = ComponentSlot::tryFrom($slot);
        if (null === $selected) {
            return;
        }

        $state = $this->stateManager->getState();
        $state->remove($selected, $productId);
        $this->stateManager->saveState($state);
    }

    /** Nouvelle configuration : la sélection et la configuration rouverte sont oubliées. */
    #[LiveAction]
    public function startNew(): void
    {
        $this->stateManager->reset();
        $this->name = '';
        $this->show(ComponentSlot::CPU);
    }

    /**
     * @return RedirectResponse|null vers la connexion pour un visiteur, qui revient ensuite au configurateur avec sa
     *                               sélection, ou null quand le composant se réaffiche avec le résultat
     */
    #[LiveAction]
    public function save(): ?RedirectResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
            $this->saveTargetPath($this->requestStack->getSession(), self::FIREWALL, $this->urlGenerator->generate('configuration.new'));

            return new RedirectResponse($this->urlGenerator->generate('app.login'));
        }

        $name = mb_substr(trim($this->name), 0, self::NAME_MAX_LENGTH);
        if ('' === $name) {
            $this->error = 'configurator.save.name_required';

            return null;
        }

        $state = $this->stateManager->getState();
        // La configuration rouverte est mise à jour. À défaut (aucune, supprimée depuis, ou celle d'un autre compte
        // restée en session), une nouvelle est créée
        $configuration = $this->editedConfiguration($state) ?? (new Configuration())->setUser($user);
        $configuration->setName($name);

        try {
            $this->issues = $this->configurationService->save($configuration, $state);
        } catch (ApiException) {
            $this->error = 'configurator.unavailable';

            return null;
        }

        if ([] === $this->issues) {
            $this->stateManager->saveState($state);
            $this->name = $name;
            $this->notice = 'configurator.save.done';
        }

        return null;
    }

    /** @return RedirectResponse|null vers le panier, ou null quand le composant se réaffiche avec ce qui bloque */
    #[LiveAction]
    public function addToCart(): ?RedirectResponse
    {
        try {
            $this->issues = $this->configurationService->addToCart($this->stateManager->getState());
        } catch (ApiException) {
            $this->error = 'configurator.unavailable';

            return null;
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return null;
        }

        if ([] !== $this->issues) {
            return null;
        }

        return new RedirectResponse($this->urlGenerator->generate('cart.index'));
    }

    public function getState(): ConfiguratorState
    {
        return $this->stateManager->getState();
    }

    public function isCatalogueAvailable(): bool
    {
        return null !== $this->review();
    }

    /**
     * Emplacements dans l'ordre du montage, avec les produits choisis (null quand l'API ne renvoie plus le produit :
     * supprimé du catalogue) et les règles que la sélection enfreint.
     *
     * @return list<array{slot: ComponentSlot, lines: list<array{productId: string, quantity: int, product: ProductSpecDto|null}>, rules: list<string>}>
     */
    public function getSlots(): array
    {
        $state = $this->stateManager->getState();
        $products = $this->review()['products'] ?? [];
        $issues = $this->review()['issues'] ?? [];

        $slots = [];
        foreach (ComponentSlot::cases() as $slot) {
            $lines = [];
            foreach ($state->lines($slot) as $line) {
                $lines[] = $line + ['product' => $products[$line['productId']] ?? null];
            }

            $rules = [];
            foreach ($issues as $issue) {
                if (\in_array($slot, $issue->slots, true)) {
                    $rules[] = $issue->rule;
                }
            }

            $slots[] = ['slot' => $slot, 'lines' => $lines, 'rules' => $rules];
        }

        return $slots;
    }

    /**
     * Problèmes à expliquer au client : ceux de la sélection, puis ceux de la dernière action (le stock). Les
     * emplacements vides sont comptés à part (getMissingCount()).
     *
     * @return list<ConfiguratorIssue>
     */
    public function getProblems(): array
    {
        $problems = [];
        foreach ([...($this->review()['issues'] ?? []), ...$this->issues] as $issue) {
            if ('missing' !== $issue->rule) {
                // La sauvegarde et le panier renvoient aussi les problèmes de la sélection : chacun n'est affiché qu'une fois
                $slots = array_map(static fn (ComponentSlot $slot): string => $slot->value, $issue->slots);
                $problems[$issue->rule.':'.implode(',', $slots)] = $issue;
            }
        }

        return array_values($problems);
    }

    public function getMissingCount(): int
    {
        return \count(array_filter($this->review()['issues'] ?? [], static fn (ConfiguratorIssue $issue): bool => 'missing' === $issue->rule));
    }

    /** La configuration est complète et compatible : seul le stock reste à vérifier, au moment de l'ajout. */
    public function isReadyForCart(): bool
    {
        return [] === ($this->review()['issues'] ?? null);
    }

    /** Total TTC en centimes des composants relus, calculé comme au panier (remises comprises). */
    public function getTotal(): int
    {
        $lines = [];
        foreach ($this->stateManager->getState()->linesWith($this->review()['products'] ?? []) as $slotLines) {
            foreach ($slotLines as ['product' => $product, 'quantity' => $quantity]) {
                $lines[] = ['unit_price' => $product->effectivePrice(), 'quantity' => $quantity];
            }
        }

        return $this->priceCalculator->totals($lines)['total'];
    }

    /**
     * Produits de la catégorie affichée compatibles avec la sélection. Null si le catalogue ne répond pas : jamais de
     * liste non filtrée à la place, qui présenterait des produits incompatibles comme compatibles.
     *
     * @return array{data: array<CategoryProductsDto>, total: int, meta: PaginationMeta, filtered: bool}|null
     */
    public function getCompatibleProducts(): ?array
    {
        $review = $this->review();
        if (null === $review) {
            return null;
        }

        $filters = $this->compatibility->filtersFor($this->slot, $this->stateManager->getState()->productsBySlot($review['products']));

        try {
            $page = $this->catalog->compatibleProducts($this->slot, $this->category, $filters, $this->page);
        } catch (ApiException) {
            return null;
        }

        return $page + ['filtered' => [] !== $filters];
    }

    private function show(ComponentSlot $slot, string $category = ''): void
    {
        $this->slot = $slot;
        $this->category = \in_array($category, $slot->categorySlugs(), true) ? $category : $slot->categorySlugs()[0];
        $this->page = 1;
    }

    /** Premier emplacement obligatoire encore vide, dans l'ordre du montage. */
    private function firstMissingSlot(): ?ComponentSlot
    {
        foreach ($this->review()['issues'] ?? [] as $issue) {
            if ('missing' === $issue->rule) {
                return $issue->slots[0] ?? null;
            }
        }

        return null;
    }

    /** Configuration sauvegardée que la sélection modifie, si elle existe encore et appartient au client connecté. */
    private function editedConfiguration(ConfiguratorState $state): ?Configuration
    {
        $configurationId = $state->configurationId();
        $configuration = null === $configurationId ? null : $this->configurationRepository->find($configurationId);

        return null !== $configuration && $this->security->isGranted(ConfigurationVoter::MANAGE, $configuration) ? $configuration : null;
    }

    /**
     * Lecture groupée des composants choisis, faite une seule fois par requête : le panneau de la sélection et la liste
     * en dépendent tous deux. Les actions qui modifient la sélection l'appellent après l'avoir enregistrée.
     *
     * @return array{products: array<string, ProductSpecDto>, issues: list<ConfiguratorIssue>}|null null si le catalogue ne répond pas
     */
    private function review(): ?array
    {
        if (!$this->reviewed) {
            $this->reviewed = true;
            try {
                $this->review = $this->configurationService->review($this->stateManager->getState());
            } catch (ApiException) {
                $this->review = null;
            }
        }

        return $this->review;
    }
}
