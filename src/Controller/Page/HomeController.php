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

        return $this->render('home/index.html.twig', [
            'brands' => $brands,
            'featured_categories' => array_slice($categories, 0, 4),
        ]);
    }
}
