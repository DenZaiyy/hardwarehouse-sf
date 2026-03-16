<?php

namespace App\Service;

use App\DTO\Api\Categories\CategoryDto;
use App\DTO\Api\Products\ProductDto;
use App\Entity\Cart;
use App\Entity\CartLine;
use App\Entity\User;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class CartService
{
    private const float VAT_RATE = 0.20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CartRepository $cartRepository,
        private readonly RequestStack $requestStack,
        private readonly ApiService $apiService,
        private readonly Security $security,
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
        $cart = $this->getOrCreateCart();

        $existingCartLine = $this->findCartLineByProductId($cart, $product->getId());

        if ($existingCartLine) {
            $existingCartLine->setQuantity($existingCartLine->getQuantity() + $quantity);
        } else {
            $cartLine = $this->createCartLine($cart, $product, $quantity);
            $cart->addCartLine($cartLine);
            $this->entityManager->persist($cartLine);
        }

        $this->entityManager->flush();
    }

    public function removeProduct(string $productId): void
    {
        $cart = $this->getCurrentCart();
        if (!$cart) {
            return;
        }

        $cartLine = $this->findCartLineByProductId($cart, $productId);
        if ($cartLine) {
            $cart->removeCartLine($cartLine);
            $this->entityManager->remove($cartLine);
            $this->entityManager->flush();
        }
    }

    public function updateQuantity(string $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeProduct($productId);

            return;
        }

        $cart = $this->getCurrentCart();
        if (!$cart) {
            return;
        }

        $cartLine = $this->findCartLineByProductId($cart, $productId);
        if ($cartLine) {
            $cartLine->setQuantity($quantity);
            $this->entityManager->flush();
        }
    }

    public function decrease(string $productId, int $currentQty): void
    {
        $this->updateQuantity($productId, $currentQty - 1);
    }

    public function increase(string $productId, int $currentQty): void
    {
        $this->updateQuantity($productId, $currentQty + 1);
    }

    /**
     * @return array<string, array{productId: string, quantity: int, remaining_stock: int, category: string, name: string, price_ht: float, price_ttc: float, imageUrl: string, slug: string}>
     */
    public function getCart(): array
    {
        $cart = $this->getCurrentCart();
        if (!$cart) {
            return [];
        }

        $result = [];
        foreach ($cart->getCartLines() as $cartLine) {
            $productId = $cartLine->getProductId();
            $quantity = $cartLine->getQuantity();
            $stock = $cartLine->getStockSnapshot();
            $category = $cartLine->getProductCategorySnapshot();
            $name = $cartLine->getProductNameSnapshot();
            $slug = $cartLine->getProductSlugSnapshot();

            if (null === $productId || null === $quantity || null === $stock || null === $category || null === $name || null === $slug) {
                continue;
            }

            $priceHt = (float) $cartLine->getUnitPriceSnapshot();
            $priceTtc = $priceHt * (1 + self::VAT_RATE);

            $result[$cartLine->getProductId()] = [
                'productId' => $productId,
                'quantity' => $quantity,
                'remaining_stock' => $stock,
                'category' => $category,
                'name' => $name,
                'price_ht' => $priceHt,
                'price_ttc' => $priceTtc,
                'imageUrl' => $cartLine->getProductImageSnapshot() ?? '',
                'slug' => $slug,
            ];
        }

        return $result;
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
        $cart = $this->getCurrentCart();
        if (!$cart) {
            return 0;
        }

        $count = 0;
        foreach ($cart->getCartLines() as $cartLine) {
            $count += $cartLine->getQuantity();
        }

        return $count;
    }

    public function clear(): void
    {
        $cart = $this->getCurrentCart();
        if (!$cart) {
            return;
        }

        // Remove the cart - CartLines will be automatically deleted due to orphanRemoval: true
        $this->entityManager->remove($cart);
        $this->entityManager->flush();
    }

    public function associateCartToUser(User $user): void
    {
        // Ensure user has a valid ID
        if (null === $user->getId()) {
            return;
        }

        $sessionToken = $this->getSessionToken();

        // Find guest cart by session token
        $guestCart = $this->cartRepository->findOneBy(['session_token' => $sessionToken, 'user' => null]);

        if ($guestCart) {
            // Check if user already has a cart
            $existingUserCart = $this->cartRepository->findOneBy(['user' => $user]);

            if ($existingUserCart) {
                // Merge guest cart into existing user cart
                $this->mergeCart($guestCart, $existingUserCart);
                $this->entityManager->remove($guestCart);
            } else {
                // Transfer guest cart to user - clear session_token and set user
                $guestCart->setUser($user);
                $guestCart->setSessionToken(null);
            }

            $this->entityManager->flush();
        }
    }

    private function getCurrentCart(): ?Cart
    {
        $user = $this->security->getUser();

        // If user is logged in and has a valid ID, find cart by user
        if ($user instanceof User && null !== $user->getId()) {
            return $this->cartRepository->findOneBy(['user' => $user]);
        }

        // If guest or user without ID, find cart by session token
        $sessionToken = $this->getSessionToken();

        return $this->cartRepository->findOneBy(['session_token' => $sessionToken, 'user' => null]);
    }

    private function getOrCreateCart(): Cart
    {
        $cart = $this->getCurrentCart();

        if ($cart) {
            return $cart;
        }

        // Create new cart
        $cart = new Cart();
        $user = $this->security->getUser();

        if ($user instanceof User && null !== $user->getId()) {
            $cart->setUser($user);
        } else {
            $cart->setSessionToken($this->getSessionToken());
        }

        $this->entityManager->persist($cart);

        return $cart;
    }

    private function findCartLineByProductId(Cart $cart, string $productId): ?CartLine
    {
        foreach ($cart->getCartLines() as $cartLine) {
            if ($cartLine->getProductId() === $productId) {
                return $cartLine;
            }
        }

        return null;
    }

    private function createCartLine(Cart $cart, ProductDto $product, int $quantity): CartLine
    {
        /** @var CategoryDto $category */
        $category = $product->category;

        $cartLine = new CartLine();
        $cartLine->setProductId($product->getId());
        $cartLine->setQuantity($quantity);
        $cartLine->setUnitPriceSnapshot((string) $product->price);
        $cartLine->setProductNameSnapshot($product->name);
        $cartLine->setProductSlugSnapshot($product->getSlug());
        $cartLine->setProductImageSnapshot($product->thumbnail ?? '');
        $cartLine->setProductCategorySnapshot($category->getName());
        $cartLine->setStockSnapshot($product->stock->quantity ?? 0);
        $cartLine->setCart($cart);

        return $cartLine;
    }

    private function getSessionToken(): string
    {
        $session = $this->requestStack->getSession();
        $token = $session->get('cart_session_token');

        if (!is_string($token) || '' === $token) {
            $token = bin2hex(random_bytes(32));
            $session->set('cart_session_token', $token);
        }

        return $token;
    }

    private function mergeCart(Cart $sourceCart, Cart $targetCart): void
    {
        foreach ($sourceCart->getCartLines() as $sourceCartLine) {
            $productId = $sourceCartLine->getProductId();
            if (null === $productId) {
                continue;
            }

            $existingCartLine = $this->findCartLineByProductId($targetCart, $productId);

            if ($existingCartLine) {
                // Add quantities together
                $existingCartLine->setQuantity(
                    $existingCartLine->getQuantity() + $sourceCartLine->getQuantity()
                );
            } else {
                // Move cart line to target cart
                $sourceCartLine->setCart($targetCart);
                $targetCart->addCartLine($sourceCartLine);
            }
        }
    }
}
