<?php

namespace App\Service;

use App\DTO\Api\Categories\CategoryDto;
use App\DTO\Api\Products\ProductDto;
use App\Entity\Cart;
use App\Entity\CartLine;
use App\Entity\User;
use App\Exception\Api\ApiException;
use App\Exception\Api\ApiNotFoundException;
use App\Repository\CartRepository;
use App\Service\Pricing\PriceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class CartService
{
    /**
     * Per-request memoization of getCurrentCart(): getCart()/getCount()/computeTotals() are
     * routinely called several times in the same request (header badge, cart dropdown, page
     * body...) and each used to trigger its own DB lookup for the same row.
     */
    private ?Cart $currentCart = null;
    private bool $currentCartResolved = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CartRepository $cartRepository,
        private readonly RequestStack $requestStack,
        private readonly ApiService $apiService,
        private readonly Security $security,
        private readonly PriceCalculator $priceCalculator,
    ) {
    }

    /**
     * @throws \RuntimeException message destiné au client, affiché tel quel par CartController
     * @throws ExceptionInterface
     */
    public function addProduct(string $productSlug, int $quantity = 1): void
    {
        if ($quantity < 1) {
            throw new \RuntimeException('La quantité doit être au moins égale à 1.');
        }

        $product = $this->fetchProductForSale($productSlug);
        $cart = $this->getCurrentCart();
        $existingCartLine = $cart ? $this->findCartLineByProductId($cart, $product->getId()) : null;

        $this->assertStockCovers($product, $quantity, $existingCartLine?->getQuantity() ?? 0);

        if ($existingCartLine) {
            // Le produit vient d'être relu : prix et stock connus du panier suivent (réassort, nouvelle remise)
            $this->applyCatalogueSnapshots($existingCartLine, $product);
            $existingCartLine->setQuantity($existingCartLine->getQuantity() + $quantity);
        } else {
            $cart = $this->getOrCreateCart();
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

    /**
     * @throws \RuntimeException message destiné au client si la quantité augmente au-delà du stock connu
     */
    public function updateQuantity(string $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeProduct($productId);

            return;
        }

        $cart = $this->getCurrentCart();
        $cartLine = $cart ? $this->findCartLineByProductId($cart, $productId) : null;
        if (null === $cartLine) {
            return;
        }

        // Stock relevé à l'ajout au panier, relu dans l'API avant le paiement (revalidate). Une baisse
        // reste toujours permise : elle rapproche la ligne du stock
        $stock = $cartLine->getStockSnapshot() ?? 0;
        if ($quantity > $cartLine->getQuantity() && $quantity > $stock) {
            throw new \RuntimeException(sprintf('Stock insuffisant : il ne reste que %d exemplaire(s).', $stock));
        }

        $cartLine->setQuantity($quantity);
        $this->entityManager->flush();
    }

    /** Part de la quantité enregistrée : celle qu'envoyait le navigateur pouvait être forgée. */
    public function decrease(string $productId): void
    {
        $this->updateQuantity($productId, $this->quantityInCart($productId) - 1);
    }

    /**
     * @throws \RuntimeException message destiné au client si le stock est atteint
     */
    public function increase(string $productId): void
    {
        $this->updateQuantity($productId, $this->quantityInCart($productId) + 1);
    }

    /**
     * Relit dans l'API chaque produit du panier avant le paiement : un produit retiré de la vente, un
     * stock devenu insuffisant ou un prix modifié depuis l'ajout corrige le panier et prévient le client.
     *
     * @return list<string> messages destinés au client, vide si le panier n'a pas changé
     *
     * @throws \RuntimeException si l'API du catalogue ne répond pas (message destiné au client)
     */
    public function revalidate(): array
    {
        $cart = $this->getCurrentCart();
        if (null === $cart) {
            return [];
        }

        $notices = [];

        foreach ($cart->getCartLines()->toArray() as $cartLine) {
            $name = (string) $cartLine->getProductNameSnapshot();
            $product = $this->fetchCatalogueProduct((string) $cartLine->getProductSlugSnapshot());
            $stock = $product->stock->quantity ?? 0;

            if (null === $product || $stock <= 0) {
                $cart->removeCartLine($cartLine);
                $this->entityManager->remove($cartLine);
                $notices[] = sprintf("« %s » n'est plus disponible et a été retiré de votre panier.", $name);

                continue;
            }

            $previousPrice = $this->effectiveUnitPrice($cartLine);
            $this->applyCatalogueSnapshots($cartLine, $product);
            $currentPrice = $this->effectiveUnitPrice($cartLine);

            if ($cartLine->getQuantity() > $stock) {
                $cartLine->setQuantity($stock);
                $notices[] = sprintf('Il ne reste que %d exemplaire(s) de « %s » : la quantité a été ajustée.', $stock, $name);
            }

            $before = $this->priceCalculator->unitPriceIncludingTax($previousPrice);
            $after = $this->priceCalculator->unitPriceIncludingTax($currentPrice);
            if ($before !== $after) {
                $notices[] = sprintf('Le prix de « %s » est passé de %s à %s.', $name, $this->formatPrice($before), $this->formatPrice($after));
            }
        }

        $this->entityManager->flush();

        return $notices;
    }

    /**
     * @return array<string, array{productId: string, quantity: int, remaining_stock: int, category: string, name: string, price_ht: float, price_ttc: float, effective_ht: float, effective_ttc: float, imageUrl: string, slug: string, discount_price: float|null, discount_amount: float|null, promote: bool}>
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

            $discountPrice = null !== $cartLine->getDiscountPriceSnapshot()
                ? (float) $cartLine->getDiscountPriceSnapshot()
                : null;

            $discountAmount = null !== $cartLine->getDiscountAmountSnapshot()
                ? (float) $cartLine->getDiscountAmountSnapshot()
                : null;

            $effectivePriceHt = $discountPrice ?? $priceHt;

            $result[$cartLine->getProductId()] = [
                'productId' => $productId,
                'quantity' => $quantity,
                'remaining_stock' => $stock,
                'category' => $category,
                'name' => $name,
                'price_ht' => $priceHt,
                'price_ttc' => $this->priceCalculator->unitPriceIncludingTax($priceHt) / 100,
                'effective_ht' => $effectivePriceHt,
                'effective_ttc' => $this->priceCalculator->unitPriceIncludingTax($effectivePriceHt) / 100,
                'imageUrl' => $cartLine->getProductImageSnapshot() ?? '',
                'slug' => $slug,
                'discount_price' => $discountPrice,
                'discount_amount' => $discountAmount,
                'promote' => null !== $discountPrice,
            ];
        }

        return $result;
    }

    /**
     * @return array{subtotal: float, vat_rate: float, vat_amount: float, total: float}
     */
    public function computeTotals(): array
    {
        $cents = $this->priceCalculator->totals(array_map(
            static fn (array $item): array => ['unit_price' => $item['effective_ht'], 'quantity' => $item['quantity']],
            array_values($this->getCart()),
        ));

        return [
            'subtotal' => $cents['subtotal'] / 100,
            'vat_rate' => PriceCalculator::VAT_RATE,
            'vat_amount' => $cents['vat'] / 100,
            'total' => $cents['total'] / 100,
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

        $this->currentCart = null;
        $this->currentCartResolved = true;
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

            // The cart resolved earlier in this request (if any) is now stale: it may have
            // been merged/removed, or a guest cart now belongs to $user.
            $this->currentCartResolved = false;
        }
    }

    private function getCurrentCart(): ?Cart
    {
        if ($this->currentCartResolved) {
            return $this->currentCart;
        }

        $user = $this->security->getUser();

        // If user is logged in and has a valid ID, find cart by user
        if ($user instanceof User && null !== $user->getId()) {
            $cart = $this->cartRepository->findOneBy(['user' => $user]);
        } else {
            // If guest or user without ID, find cart by session token
            $sessionToken = $this->getSessionToken();
            $cart = $this->cartRepository->findOneBy(['session_token' => $sessionToken, 'user' => null]);
        }

        $this->currentCart = $cart;
        $this->currentCartResolved = true;

        return $cart;
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

        $this->currentCart = $cart;
        $this->currentCartResolved = true;

        return $cart;
    }

    /**
     * L'API renvoie aussi les fiches désactivées : seul un produit actif peut entrer dans le panier.
     *
     * @throws ExceptionInterface
     */
    private function fetchProductForSale(string $productSlug): ProductDto
    {
        return $this->fetchCatalogueProduct($productSlug) ?? throw new \RuntimeException("Ce produit n'est plus disponible.");
    }

    /**
     * Produit en vente, ou null s'il est inconnu de l'API ou désactivé : l'API renvoie aussi les
     * fiches désactivées.
     *
     * @throws \RuntimeException si l'API ne répond pas (message destiné au client)
     */
    private function fetchCatalogueProduct(string $productSlug): ?ProductDto
    {
        try {
            $product = $this->apiService->fetchOne("products/$productSlug", ProductDto::class);
        } catch (ApiNotFoundException) {
            return null;
        } catch (ApiException $e) {
            throw new \RuntimeException('Le catalogue est momentanément indisponible, merci de réessayer.', 0, $e);
        }

        return $product->active ? $product : null;
    }

    private function quantityInCart(string $productId): int
    {
        $cart = $this->getCurrentCart();

        return ($cart ? $this->findCartLineByProductId($cart, $productId)?->getQuantity() : null) ?? 0;
    }

    /** Prix unitaire hors taxe payé par le client : la remise s'il y en a une. */
    private function effectiveUnitPrice(CartLine $cartLine): float
    {
        return (float) ($cartLine->getDiscountPriceSnapshot() ?? $cartLine->getUnitPriceSnapshot());
    }

    /** Prix, remise et stock du catalogue, recopiés à l'ajout au panier puis avant le paiement. */
    private function applyCatalogueSnapshots(CartLine $cartLine, ProductDto $product): void
    {
        $isDiscounted = $product->promote && null !== $product->discountPrice && null !== $product->discountAmount;

        $cartLine->setUnitPriceSnapshot((string) $product->price);
        $cartLine->setDiscountPriceSnapshot($isDiscounted ? (string) $product->discountPrice : null);
        $cartLine->setDiscountAmountSnapshot($isDiscounted ? (string) $product->discountAmount : null);
        $cartLine->setStockSnapshot($product->stock->quantity ?? 0);
    }

    private function formatPrice(int $cents): string
    {
        return (string) (new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY))->formatCurrency($cents / 100, 'EUR');
    }

    /** Le stock porte sur la quantité totale du panier, pas seulement sur celle qu'on ajoute. */
    private function assertStockCovers(ProductDto $product, int $quantity, int $alreadyInCart): void
    {
        $stock = $product->stock->quantity ?? 0;

        if ($alreadyInCart + $quantity <= $stock) {
            return;
        }

        if ($stock <= 0) {
            throw new \RuntimeException('Ce produit est en rupture de stock.');
        }

        throw new \RuntimeException(0 === $alreadyInCart
            ? sprintf('Stock insuffisant : il ne reste que %d exemplaire(s).', $stock)
            : sprintf('Stock insuffisant : il ne reste que %d exemplaire(s), dont %d déjà dans votre panier.', $stock, $alreadyInCart));
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
        $cartLine->setProductNameSnapshot($product->name);
        $cartLine->setProductSlugSnapshot($product->getSlug());
        $cartLine->setProductImageSnapshot($product->thumbnail ?? '');
        $cartLine->setProductCategorySnapshot($category->getName());
        $cartLine->setCart($cart);
        $this->applyCatalogueSnapshots($cartLine, $product);

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
