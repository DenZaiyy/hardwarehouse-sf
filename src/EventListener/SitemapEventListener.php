<?php

namespace App\EventListener;

use App\Dto\Api\Brands\BrandDto;
use App\Dto\Api\Categories\CategoryDto;
use App\Dto\Api\Products\ProductDto;
use App\Service\ApiService;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\GoogleMultilangUrlDecorator;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: SitemapPopulateEvent::class, method: 'onSitemapPopulate')]
readonly class SitemapEventListener
{
    public function __construct(
        private ApiService $apiService,
    ) {
    }

    public function onSitemapPopulate(SitemapPopulateEvent $event): void
    {
        $this->registerProductsUrls($event->getUrlContainer(), $event->getUrlGenerator());
        $this->registerBrandsUrls($event->getUrlContainer(), $event->getUrlGenerator());
        $this->registerCategoriesUrls($event->getUrlContainer(), $event->getUrlGenerator());
    }

    public function registerProductsUrls(UrlContainerInterface $urls, UrlGeneratorInterface $router): void
    {
        $products = $this->apiService->fetchPaginated('products', ProductDto::class, ['limit' => 50]);

        foreach ($products['data'] as $product) {
            $url = new UrlConcrete($router->generate('product.show', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));

            $decoratedUrl = new GoogleMultilangUrlDecorator($url);
            $decoratedUrl->addLink($router->generate('product.show.en', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL), 'en');
            $decoratedUrl->addLink($router->generate('product.show.fr', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL), 'fr');

            $urls->addUrl($decoratedUrl, 'product');
        }
    }

    public function registerBrandsUrls(UrlContainerInterface $urls, UrlGeneratorInterface $router): void
    {
        $brands = $this->apiService->fetchPaginated('brands', BrandDto::class, ['limit' => 50]);

        foreach ($brands['data'] as $brand) {
            $url = new UrlConcrete($router->generate('brand.show', ['slug' => $brand->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));

            $decoratedUrl = new GoogleMultilangUrlDecorator($url);
            $decoratedUrl->addLink($router->generate('brand.show.en', ['slug' => $brand->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL), 'en');
            $decoratedUrl->addLink($router->generate('brand.show.fr', ['slug' => $brand->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL), 'fr');

            $urls->addUrl($decoratedUrl, 'brand');
        }
    }

    public function registerCategoriesUrls(UrlContainerInterface $urls, UrlGeneratorInterface $router): void
    {
        $categories = $this->apiService->fetchAll('categories', CategoryDto::class);

        foreach ($categories as $category) {
            $url = new UrlConcrete($router->generate('category.show', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));

            $decoratedUrl = new GoogleMultilangUrlDecorator($url);
            $decoratedUrl->addLink($router->generate('category.show.en', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL), 'en');
            $decoratedUrl->addLink($router->generate('category.show.fr', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL), 'fr');

            $urls->addUrl($decoratedUrl, 'category');
        }
    }
}
