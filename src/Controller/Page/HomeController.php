<?php

declare(strict_types=1);

namespace App\Controller\Page;

use App\DTO\Api\Brands\BrandDto;
use App\DTO\Api\Categories\CategoryDto;
use App\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class HomeController extends AbstractController
{
    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws ExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     */
    #[Route('/', name: 'homepage', options: ['sitemap' => true])]
    public function index(ApiService $apiService): Response
    {
        $brands = $apiService->fetchAll('brands', BrandDto::class);
        $categories = $apiService->fetchAll('categories', CategoryDto::class);

        $hero = [
            'eyebrow' => 'Spécialiste composants PC',
            'title' => 'Construis un setup premium, pièce par pièce.',
            'highlight' => 'Performance. Compatibilité. Fiabilité.',
            'description' => 'CPU, GPU, cartes mères, RAM, SSD et périphériques sélectionnés pour offrir une expérience d’achat claire, rapide et orientée performance.',
            'image' => 'images/header_50.webp',
            'primary_cta_label' => 'Explorer le catalogue',
            'primary_cta_url' => $this->generateUrl('product.index'),
            'secondary_cta_label' => 'Voir les catégories',
            'secondary_cta_url' => '#home-categories',
        ];

        $stats = [
            [
                'number' => 2000,
                'suffix' => '+',
                'decimals' => 0,
                'label' => 'Références en stock',
            ],
            [
                'number' => 24,
                'suffix' => 'h',
                'decimals' => 0,
                'label' => 'Livraison express',
            ],
            [
                'number' => 4.9,
                'suffix' => '',
                'decimals' => 1,
                'label' => 'Note clients',
            ],
        ];

        $promo = [
            'badge' => 'Offre limitée',
            'title' => '-15% sur une sélection de processeurs',
            'description' => 'Des performances premium à prix réduit sur une sélection de CPU Intel et AMD. Offre valable jusqu’à épuisement des stocks.',
            'cta_label' => 'Découvrir l’offre',
            'cta_url' => $this->generateUrl('category.show', ['slug' => 'processeurs']),
        ];

        $perks = [
            [
                'icon' => 'ph:lightning-duotone',
                'title' => 'Livraison express 24h',
                'desc' => 'Commande traitée rapidement depuis notre entrepôt pour accélérer la mise en production de ton setup.',
            ],
            [
                'icon' => 'ph:magnifying-glass-duotone',
                'title' => 'Sélection experte',
                'desc' => 'Des composants choisis pour leur fiabilité, leurs performances et leur cohérence dans des builds modernes.',
            ],
            [
                'icon' => 'ph:shield-check-duotone',
                'title' => 'Compatibilité maîtrisée',
                'desc' => 'Un catalogue pensé pour réduire les erreurs de choix et sécuriser tes achats techniques.',
            ],
            [
                'icon' => 'ph:trophy-duotone',
                'title' => 'Prix compétitifs',
                'desc' => 'Des offres suivies régulièrement pour garder un positionnement premium sans dérive tarifaire.',
            ],
        ];

        return $this->render('home/index.html.twig', [
            'hero' => $hero,
            'stats' => $stats,
            'promo' => $promo,
            'perks' => $perks,
            'brands' => $brands,
            'featured_categories' => array_slice($categories, 0, 4),
            // 'featured_products' => ...
        ]);
    }
}
