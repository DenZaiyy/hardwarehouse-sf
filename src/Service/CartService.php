<?php

namespace App\Service;

use App\Dto\Api\Categories\CategoryDto;
use App\Dto\Api\Products\ProductDto;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class CartService
{
    private const string SESSION_KEY = 'cart';
    private const float VAT_RATE = 0.20;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ApiService $apiService,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function addProduct(string $productSlug, int $quantity = 1): void
    {
        $product = $this->apiService->fetchOne("products/$productSlug", ProductDto::class);

        $productId = $product->getId();
        /** @var CategoryDto $category */
        $category = $product->category;
        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            // Calculate price TVA included
            $priceTTC = $product->price * self::VAT_RATE;
            // Add price HT to get price TTC
            $priceTTC += $product->price;

            $cart[$productId] = [
                'productId' => $productId,
                'quantity' => $quantity,
                'remaining_stock' => $product->stock,
                'category' => $category->getName(),
                'name' => $product->name,
                'price_ht' => (float) $product->price,
                'price_ttc' => $priceTTC,
                'imageUrl' => $product->thumbnail ?? '',
                'slug' => $product->getSlug(),
            ];
        }

        $this->saveCart($cart);
    }

    public function removeProduct(string $productId): void
    {
        $cart = $this->getCart();
        unset($cart[$productId]);
        $this->saveCart($cart);
    }

    public function decrease(string $productId, int $currentQtt): void
    {
        if ($currentQtt <= 1) {
            $this->removeProduct($productId);

            return;
        }

        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] = $currentQtt - 1;
            $this->saveCart($cart);
        }
    }

    public function increase(string $productId, int $currentQtt): void
    {
        if ($currentQtt <= 0) {
            $this->removeProduct($productId);

            return;
        }

        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] = $currentQtt + 1;
            $this->saveCart($cart);
        }
    }

    /**
     * @return array<string, array{productId: string, quantity: int, name: string, price_ht: float, imageUrl: string, slug: string}>
     */
    public function getCart(): array
    {
        /** @var array<string, array{productId: string, quantity: int, name: string, price_ht: float, imageUrl: string, slug: string}> */
        return $this->requestStack->getSession()->get(self::SESSION_KEY, []);
    }

    /**
     * @return array{subtotal: float, vat_rate: float, vat_amount: float, total: float}
     */
    public function computeTotals(): array
    {
        $subtotal = 0.0;

        foreach ($this->getCart() as $item) {
            $subtotal += $item['price_ht'] * $item['quantity'];
        }

        $vatAmount = $subtotal * self::VAT_RATE;

        return [
            'subtotal' => $subtotal,
            'vat_rate' => self::VAT_RATE,
            'vat_amount' => $vatAmount,
            'total' => $subtotal + $vatAmount,
        ];
    }

    public function getCount(): int
    {
        return array_sum(array_map(
            static fn (array $item): int => $item['quantity'],
            $this->getCart()
        ));
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * @param array<string, array{productId: string, quantity: int, name: string, price_ht: float, imageUrl: string, slug: string}> $cart
     */
    private function saveCart(array $cart): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $cart);
    }
}
