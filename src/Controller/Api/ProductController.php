<?php

namespace App\Controller\Api;

use App\DTO\Api\Brands\BrandDto;
use App\DTO\Api\Categories\CategoryDto;
use App\DTO\Api\Products\ProductDto;
use App\Entity\Rating;
use App\Entity\User;
use App\Enum\ComponentSlot;
use App\Form\ReviewType;
use App\Repository\CarrierRepository;
use App\Repository\RatingRepository;
use App\Security\Voter\ProductReviewVoter;
use App\SEO\Schema\ProductSchemaBuilder;
use App\Service\ApiService;
use App\Service\Catalog\ProductSpecFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: ['en' => '/products', 'fr' => '/produits'], name: 'product.')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ApiService $apiService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $categories = $request->query->get('categories', '');
        $brands = $request->query->get('brands', '');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');

        $categories = '' !== $categories ? array_filter(explode(',', $categories)) : [];
        $brands = '' !== $brands ? array_filter(explode(',', $brands)) : [];

        $params = [
            'page' => $page,
            'limit' => $limit,
        ];

        if (!empty($categories)) {
            $params['categories'] = implode(',', $categories);
        }

        if (!empty($brands)) {
            $params['brands'] = implode(',', $brands);
        }

        if (null !== $minPrice && '' !== $minPrice) {
            $params['minPrice'] = $minPrice;
        }

        if (null !== $maxPrice && '' !== $maxPrice) {
            $params['maxPrice'] = $maxPrice;
        }

        $categoriesData = $this->apiService->fetchAllCached('categories', CategoryDto::class);
        $brandsData = $this->apiService->fetchAllCached('brands', BrandDto::class);

        try {
            $result = $this->apiService->fetchPaginated(
                'products',
                ProductDto::class,
                $params,
            );

            if ($request->isXmlHttpRequest()) {
                $productsHtml = $this->renderView('_partials/_products_grid.html.twig', [
                    'products' => $result['data'],
                ]);

                $paginationHtml = $this->renderView('_partials/_pagination_ajax.html.twig', [
                    'pagination' => $result['meta'],
                ]);

                return new JsonResponse([
                    'success' => true,
                    'productsHtml' => $productsHtml,
                    'paginationHtml' => $paginationHtml,
                    'total' => $result['total'],
                ]);
            }
        } catch (\Throwable $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Failed to fetch products',
                ], 500);
            }

            throw $e;
        }

        return $this->render('product/index.html.twig', [
            'products' => $result['data'],
            'pagination' => $result['meta'],
            'categories' => $categoriesData,
            'brands' => $brandsData,
            'limit' => $limit,
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug, Request $request, ProductSchemaBuilder $productSchemaBuilder, ProductSpecFormatter $specFormatter, CarrierRepository $carrierRepository, RatingRepository $ratingRepository): Response
    {
        $product = $this->apiService->fetchOne('products/'.$slug, ProductDto::class);

        // L'API renvoie aussi les fiches désactivées dans le back-office : elles ne sont plus en vente
        if (!$product->active) {
            throw $this->createNotFoundException();
        }

        $specs = $specFormatter->specs($product->productAttributeValues);

        // Formulaire d'avis pour un acheteur du produit : le sien s'il en a déjà déposé un, qu'il modifie alors
        $reviewForm = null;
        $ownReview = null;
        if ($this->isGranted(ProductReviewVoter::REVIEW, $product->id)) {
            $ownReview = $ratingRepository->findOneBy(['user' => $this->getUser(), 'productId' => $product->id]);
            $reviewForm = $this->createForm(ReviewType::class, $ownReview ?? new Rating(), [
                'action' => $this->generateUrl('product.review', ['slug' => $product->slug]),
            ])->createView();
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'specs' => $specs,
            'highlights' => $specFormatter->highlights($specs),
            'cheapestDelivery' => $carrierRepository->findCheapestPrice(),
            'inConfigurator' => null !== $product->category && null !== ComponentSlot::forCategory($product->category->slug),
            'productSchema' => $productSchemaBuilder->build($product),
            'reviews' => $ratingRepository->findForProduct($product->id),
            'reviewSummary' => $ratingRepository->summary($product->id),
            'reviewForm' => $reviewForm,
            'ownReview' => null !== $ownReview,
            // Retour d'un dépôt d'avis : la fiche rouvre l'onglet Avis
            'openTab' => 'avis' === $request->query->getString('onglet') ? 2 : 1,
        ]);
    }

    /**
     * Dépôt ou modification de l'avis d'un acheteur (ProductReviewVoter) : un seul avis par client et par produit, un
     * second envoi modifie le premier, qui garde sa date de dépôt et prend une date de modification.
     */
    #[Route('/{slug}/avis', name: 'review', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['POST'])]
    public function review(string $slug, Request $request, RatingRepository $ratingRepository, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $product = $this->apiService->fetchOne('products/'.$slug, ProductDto::class);
        if (!$product->active) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(ProductReviewVoter::REVIEW, $product->id);

        /** @var User $customer */
        $customer = $this->getUser();
        $rating = $ratingRepository->findOneBy(['user' => $customer, 'productId' => $product->id])
            ?? (new Rating())->setUser($customer)->setProductId($product->id);

        $form = $this->createForm(ReviewType::class, $rating);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($rating);
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('product.page.reviews.saved'));
        } else {
            $this->addFlash('danger', $translator->trans('product.page.reviews.invalid'));
        }

        return $this->redirectToRoute('product.show', ['slug' => $product->slug, 'onglet' => 'avis', '_fragment' => 'avis']);
    }
}
