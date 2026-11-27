<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\OrderLine;
use App\Entity\Rating;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\RatingRepository;
use App\Tests\Support\CreatesShopEntities;
use App\Tests\Support\FakesCatalogApi;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Avis vérifiés : seul un client dont une commande payée contient le produit peut le noter, une fois. Il modifie
 * ensuite son avis, qui garde sa date de dépôt et affiche celle de sa modification.
 */
final class ProductReviewTest extends WebTestCase
{
    use CreatesShopEntities;
    use FakesCatalogApi;

    public function testReviewsShowTheirAverageAndCount(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $this->review($this->createUser('alice'), $productId, 5, 'Excellent pour le jeu.');
        $this->review($this->createUser('bruno'), $productId, 4, 'Très performant.');

        $crawler = $client->request('GET', '/fr/produits/'.$slug);

        self::assertSame('4,5 / 5', self::plain($crawler->filter('.reviews-average')->text()));
        self::assertSelectorTextContains('.reviews-summary', '2 avis vérifiés');
        self::assertSelectorTextContains('.product-rating', '(2 avis)');
        self::assertStringContainsString('Avis (2)', $crawler->filter('[role="tab"]')->last()->text());
        self::assertCount(2, $crawler->filter('.review'));
        self::assertStringContainsString('Très performant.', $crawler->filter('.review')->first()->text());
    }

    public function testEditedReviewShowsWhenItWasEdited(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $rating = $this->review($this->createUser(), $productId, 3, 'Correct.');
        // Requête DQL : le PreUpdate de l'entité remplacerait la date choisie par celle du jour
        $this->entityManager()->createQuery('UPDATE '.Rating::class.' rating SET rating.updated_at = :date WHERE rating.id = :id')
            ->setParameter('date', new \DateTimeImmutable('2026-11-20 10:00'))
            ->setParameter('id', $rating->getId())
            ->execute();
        $this->entityManager()->clear();

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorTextContains('.review', 'Modifié le 20 novembre 2026');
    }

    public function testGuestIsInvitedToSignInToReview(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorTextContains('.reviews-access', 'Connectez-vous pour donner votre avis.');
        self::assertSelectorNotExists('form[name="review"]');
    }

    public function testCustomerWhoDidNotBuyTheProductCannotReviewIt(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $this->apiHasProduct($slug);
        $client->loginUser($this->createUser());

        $client->request('GET', '/fr/produits/'.$slug);
        self::assertSelectorTextContains('.reviews-access', 'Seuls les clients qui ont acheté ce produit peuvent donner leur avis.');
        self::assertSelectorNotExists('form[name="review"]');

        $client->request('POST', '/fr/produits/'.$slug.'/avis', ['review' => ['note' => '5']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnpaidOrderDoesNotAllowAReview(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $client->loginUser($this->buyer($productId, OrderStatus::PENDING));

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorNotExists('form[name="review"]');
    }

    public function testBuyerPublishesAReview(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $buyer = $this->buyer($productId);
        $client->loginUser($buyer);

        $crawler = $client->request('GET', '/fr/produits/'.$slug);
        $client->submit($crawler->selectButton('Publier mon avis')->form(['review[note]' => '4', 'review[comment]' => 'Silencieux et efficace.']));

        self::assertResponseRedirects('/fr/produits/'.$slug.'?onglet=avis#avis');
        $ratings = $this->entityManager()->getRepository(Rating::class)->findBy(['productId' => $productId]);
        self::assertCount(1, $ratings);
        self::assertSame([4.0, 'Silencieux et efficace.', $buyer->getId()], [$ratings[0]->getNote(), $ratings[0]->getComment(), $ratings[0]->getUser()?->getId()]);
        self::assertNull($ratings[0]->getUpdatedAt());
    }

    public function testBuyerEditsTheirReviewInsteadOfAddingASecondOne(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $buyer = $this->buyer($productId);
        $this->review($buyer, $productId, 5, 'Parfait.');
        $client->loginUser($buyer);

        $crawler = $client->request('GET', '/fr/produits/'.$slug);
        self::assertSame('5', $crawler->filter('input[name="review[note]"][checked]')->attr('value'));
        $client->submit($crawler->selectButton('Enregistrer les modifications')->form(['review[note]' => '3', 'review[comment]' => 'Plus bruyant que prévu.']));

        $this->entityManager()->clear();
        $ratings = $this->entityManager()->getRepository(Rating::class)->findBy(['productId' => $productId]);
        self::assertCount(1, $ratings);
        self::assertSame([3.0, 'Plus bruyant que prévu.'], [$ratings[0]->getNote(), $ratings[0]->getComment()]);
        self::assertNotNull($ratings[0]->getUpdatedAt());
    }

    public function testCommentIsShownAsText(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $this->review($this->createUser(), $productId, 2, '<b>Décevant</b><script>alert(1)</script>');

        $client->request('GET', '/fr/produits/'.$slug);

        self::assertSelectorNotExists('.review b');
        self::assertSelectorNotExists('.review script');
        self::assertSelectorTextContains('.review', '<b>Décevant</b>');
    }

    public function testNoteOutsideOneToFiveIsRefused(): void
    {
        $client = static::createClient();
        $slug = self::newProductSlug();
        $productId = $this->apiHasProduct($slug);
        $client->loginUser($this->buyer($productId));

        $crawler = $client->request('GET', '/fr/produits/'.$slug);
        $form = $crawler->selectButton('Publier mon avis')->form();
        $client->request('POST', $form->getUri(), ['review' => ['note' => '6', '_token' => $form->get('review[_token]')->getValue()]]);

        self::assertSame([], $this->entityManager()->getRepository(Rating::class)->findBy(['productId' => $productId]));
    }

    public function testListedProductShowsItsAverageAndCount(): void
    {
        $client = static::createClient();
        $reviewed = self::newProductSlug();
        $silent = self::newProductSlug();
        $this->apiListsProducts([self::productPayload($reviewed), self::productPayload($silent)]);
        $this->review($this->createUser(), self::productId($reviewed), 5, 'Parfait.');
        $this->review($this->createUser(), self::productId($reviewed), 4, 'Bien.');

        $crawler = $client->request('GET', '/fr/produits');

        // Sous le bouton, hors du lien vers la fiche, comme la maquette
        $reviewedCard = $crawler->filter('a[href$="/produits/'.$reviewed.'"]')->closest('.product-card');
        $silentCard = $crawler->filter('a[href$="/produits/'.$silent.'"]')->closest('.product-card');
        self::assertNotNull($reviewedCard);
        self::assertNotNull($silentCard);
        self::assertSame('4,5 (2 avis)', self::plain($reviewedCard->filter('.product-card-rating')->text()));
        self::assertCount(0, $silentCard->filter('.product-card-rating'));
    }

    public function testSummariesOfAPageAreReadInOneQuery(): void
    {
        static::createClient();
        $first = 'produit-'.bin2hex(random_bytes(4));
        $second = 'produit-'.bin2hex(random_bytes(4));
        $this->review($this->createUser(), $first, 5, 'Parfait.');
        $this->review($this->createUser(), $first, 2, 'Bof.');
        $this->review($this->createUser(), $second, 4, 'Bien.');

        $summaries = static::getContainer()->get(RatingRepository::class)->summaries([$first, $second, 'sans-avis']);
        $expected = [$first => ['average' => 3.5, 'count' => 2], $second => ['average' => 4.0, 'count' => 1]];
        // GROUP BY ne fixe pas l'ordre des produits
        ksort($summaries);
        ksort($expected);

        self::assertSame($expected, $summaries);
    }

    /** Client dont une commande, à ce statut, contient le produit */
    private function buyer(string $productId, OrderStatus $status = OrderStatus::CONFIRMED): User
    {
        $customer = $this->createUser('acheteur');
        $order = $this->createOrder($customer)->setStatus($status);
        $order->addOrderLine((new OrderLine())
            ->setProductId($productId)
            ->setProductSlug('produit')
            ->setProductName('Produit acheté')
            ->setQuantity(1)
            ->setUnitPrice('100.00')
            ->setTaxRate('20.00')
            ->setLineTotal('120.00'));
        $this->entityManager()->flush();

        return $customer;
    }

    private function review(User $author, string $productId, int $note, string $comment): Rating
    {
        $rating = (new Rating())->setUser($author)->setProductId($productId)->setNote($note)->setComment($comment);
        $this->entityManager()->persist($rating);
        $this->entityManager()->flush();

        return $rating;
    }

    private static function plain(string $text): string
    {
        return trim(str_replace(["\u{a0}", "\u{202f}"], ' ', $text));
    }
}
