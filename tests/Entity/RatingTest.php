<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Rating;
use App\Entity\User;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RatingTest extends KernelTestCase
{
    use CreatesShopEntities;

    public function testCustomerCannotRateTheSameProductTwice(): void
    {
        $customer = $this->createUser();
        $this->saveRating(self::rating($customer, 'ryzen-7-7800x3d'));

        $violations = $this->validator()->validate(self::rating($customer, 'ryzen-7-7800x3d'));

        self::assertCount(1, $violations);
        self::assertSame('Vous avez déjà donné votre avis sur ce produit.', $violations[0]->getMessage());
    }

    public function testEachCustomerCanRateTheSameProduct(): void
    {
        $this->saveRating(self::rating($this->createUser('premier'), 'ryzen-7-7800x3d'));
        $secondRating = self::rating($this->createUser('second'), 'ryzen-7-7800x3d');

        self::assertCount(0, $this->validator()->validate($secondRating));

        // L'index unique de la base accepte lui aussi le second avis
        $this->saveRating($secondRating);
        self::assertNotNull($secondRating->getId());
    }

    private function saveRating(Rating $rating): void
    {
        $this->entityManager()->persist($rating);
        $this->entityManager()->flush();
    }

    private static function rating(User $customer, string $productId): Rating
    {
        return (new Rating())
            ->setUser($customer)
            ->setProductId($productId)
            ->setNote(4.5)
            ->setCreatedAt(new \DateTimeImmutable());
    }

    private function validator(): ValidatorInterface
    {
        return static::getContainer()->get(ValidatorInterface::class);
    }
}
