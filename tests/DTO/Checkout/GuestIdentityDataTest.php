<?php

declare(strict_types=1);

namespace App\Tests\DTO\Checkout;

use App\DTO\Checkout\GuestIdentityData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class GuestIdentityDataTest extends TestCase
{
    public function testCompleteIdentityIsValid(): void
    {
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate(self::identity()));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function invalidFields(): iterable
    {
        yield 'prénom vide' => ['firstName', ''];
        yield 'prénom trop long' => ['firstName', str_repeat('a', 51)];
        yield 'nom vide' => ['lastName', ''];
        yield 'nom trop long' => ['lastName', str_repeat('a', 51)];
        yield 'e-mail absent' => ['email', null];
        yield 'e-mail mal formé' => ['email', 'jean.dupont'];
        yield 'e-mail trop long' => ['email', str_repeat('a', 169).'@example.com'];
    }

    #[DataProvider('invalidFields')]
    public function testInvalidFieldIsRejected(string $property, ?string $value): void
    {
        $identity = self::identity();
        $identity->{$property} = $value;

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($identity);

        self::assertNotCount(0, $violations);
        foreach ($violations as $violation) {
            self::assertSame($property, $violation->getPropertyPath());
        }
    }

    private static function identity(): GuestIdentityData
    {
        $identity = new GuestIdentityData();
        $identity->firstName = 'Jean';
        $identity->lastName = 'Dupont';
        $identity->email = 'jean.dupont@example.com';

        return $identity;
    }
}
