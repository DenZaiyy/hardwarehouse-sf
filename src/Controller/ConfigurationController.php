<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Api\Products\ProductDto;
use App\DTO\Configurator\ConfiguratorIssue;
use App\Entity\Configuration;
use App\Entity\User;
use App\Enum\ComponentSlot;
use App\Exception\Api\ApiException;
use App\Repository\ConfigurationRepository;
use App\Security\Voter\ConfigurationVoter;
use App\Service\ApiService;
use App\Service\ConfigurationService;
use App\Service\Configurator\ConfiguratorStateManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Configurateur (Configurator:ConfiguratorComponent), ouvert à tous, et configurations sauvegardées du client : liste,
 * détail, réouverture dans le configurateur, ajout au panier et suppression.
 */
#[Route('/configurations', name: 'configuration.')]
final class ConfigurationController extends AbstractController
{
    /** Slug d'un produit du catalogue : lettres minuscules, chiffres et tirets */
    private const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly ConfiguratorStateManager $stateManager,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(ConfigurationRepository $repository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('configuration/index.html.twig', [
            'configurations' => $repository->findByUserWithItems($user),
        ]);
    }

    /**
     * La sélection est gardée en session : le configurateur ne demande pas de compte. ?categorie= l'ouvre sur l'étape
     * de cette catégorie (après « Ajouter à ma configuration »), une catégorie inconnue est ignorée.
     */
    #[Route('/new', name: 'new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        return $this->render('configuration/new.html.twig', [
            'openCategory' => $request->query->getString('categorie'),
        ]);
    }

    /**
     * « Ajouter à ma configuration » depuis une fiche produit : le produit prend son emplacement dans la sélection en
     * cours (il remplace le précédent, sauf pour les disques, qui s'ajoutent jusqu'à trois), puis le configurateur
     * s'ouvre sur sa catégorie. La compatibilité est vérifiée là, comme pour un produit choisi dans le configurateur.
     */
    #[Route('/ajouter', name: 'add_product', methods: ['POST'])]
    public function addProduct(Request $request, ApiService $apiService): Response
    {
        $slug = $request->request->getString('slug');
        // Le slug compose l'adresse de l'API : un « ../ » ne doit jamais l'atteindre
        if (1 !== preg_match(self::SLUG_PATTERN, $slug)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('configuration_add', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('product.show', ['slug' => $slug]);
        }

        $product = $apiService->fetchOne('products/'.$slug, ProductDto::class);
        $slot = null === $product->category ? null : ComponentSlot::forCategory($product->category->slug);
        if (!$product->active || null === $product->category || null === $slot) {
            throw $this->createNotFoundException();
        }

        $state = $this->stateManager->getState();
        if (!$state->choose($slot, $product->id)) {
            $this->addFlash('danger', $this->translator->trans('configurator.storage_full'));

            return $this->redirectToRoute('product.show', ['slug' => $slug]);
        }
        $this->stateManager->saveState($state);
        $this->addFlash('success', $this->translator->trans('configurator.added', ['%product%' => $product->name]));

        return $this->redirectToRoute('configuration.new', ['categorie' => $product->category->slug]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[IsGranted(ConfigurationVoter::MANAGE, subject: 'configuration', statusCode: Response::HTTP_NOT_FOUND)]
    public function show(Configuration $configuration): Response
    {
        return $this->renderShow($configuration);
    }

    /** Rouvre la configuration dans le configurateur, à la place de la sélection en cours : la sauvegarde la mettra à jour. */
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[IsGranted(ConfigurationVoter::MANAGE, subject: 'configuration', statusCode: Response::HTTP_NOT_FOUND)]
    public function edit(Configuration $configuration, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('configuration_edit'.$configuration->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('configuration.show', ['id' => $configuration->getId()]);
        }

        $this->stateManager->saveState($this->configurationService->load($configuration));

        return $this->redirectToRoute('configuration.new');
    }

    /** Ajoute au panier la configuration telle que sauvegardée, sans toucher à la sélection en cours du configurateur. */
    #[Route('/{id}/cart', name: 'cart', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[IsGranted(ConfigurationVoter::MANAGE, subject: 'configuration', statusCode: Response::HTTP_NOT_FOUND)]
    public function addToCart(Configuration $configuration, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('configuration_cart'.$configuration->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('configuration.show', ['id' => $configuration->getId()]);
        }

        try {
            $issues = $this->configurationService->addToCart($this->configurationService->load($configuration));
        } catch (ApiException) {
            return $this->renderShow($configuration, error: 'configurator.unavailable');
        } catch (\RuntimeException $exception) {
            return $this->renderShow($configuration, error: $exception->getMessage());
        }

        if ([] !== $issues) {
            return $this->renderShow($configuration, $issues);
        }

        return $this->redirectToRoute('cart.index');
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[IsGranted(ConfigurationVoter::MANAGE, subject: 'configuration', statusCode: Response::HTTP_NOT_FOUND)]
    public function delete(Configuration $configuration, Request $request): Response
    {
        if ($this->isCsrfTokenValid('configuration_delete'.$configuration->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($configuration);
            $this->em->flush();

            $this->addFlash('success', $this->translator->trans('configurator.saved.deleted'));
        }

        return $this->redirectToRoute('configuration.index');
    }

    /**
     * Page d'une configuration. Après un ajout au panier refusé, elle explique pourquoi, avec le statut 422 qu'attend
     * Turbo pour afficher la réponse d'un formulaire.
     *
     * @param list<ConfiguratorIssue> $issues
     * @param string|null             $error  clé de traduction, ou message destiné au client levé par CartService
     */
    private function renderShow(Configuration $configuration, array $issues = [], ?string $error = null): Response
    {
        $refused = [] !== $issues || null !== $error;

        return $this->render('configuration/show.html.twig', [
            'configuration' => $configuration,
            'slots' => ComponentSlot::cases(),
            'issues' => $issues,
            'error' => $error,
        ], new Response(status: $refused ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
