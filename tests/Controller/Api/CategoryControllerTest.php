<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CategoryControllerTest extends WebTestCase
{
    use FakesCatalogApi;

    public function testCategoriesPageIsDisplayedWhenTheApiFails(): void
    {
        $client = static::createClient();
        $this->apiFailsOn('categories');

        $client->request('GET', '/fr/categories');

        self::assertResponseIsSuccessful();
    }

    public function testUnknownCategoryPageIsNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/categories/categorie-inconnue');

        self::assertResponseStatusCodeSame(404);
    }
}
