<?php

declare(strict_types=1);

namespace App\Tests\DTO\Checkout;

use App\DTO\Checkout\AddressData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * Les longueurs maximales sont celles d'OrderAddress, où l'adresse est recopiée à la commande.
 */
final class AddressDataTest extends TestCase
{
    public function testCompleteAddressIsValid(): void
    {
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate(self::address()));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function invalidFields(): iterable
    {
        yield 'libellé vide' => ['label', ''];
        yield 'libellé trop court' => ['label', 'AB'];
        yield 'prénom vide' => ['firstName', ''];
        yield 'prénom trop long' => ['firstName', str_repeat('a', 51)];
        yield 'nom vide' => ['lastName', ''];
        yield 'nom trop long' => ['lastName', str_repeat('a', 51)];
        yield 'adresse vide' => ['address1', ''];
        yield 'adresse trop longue' => ['address1', str_repeat('a', 256)];
        yield 'code postal vide' => ['postcode', ''];
        yield 'code postal trop long' => ['postcode', str_repeat('1', 11)];
        yield 'ville vide' => ['city', ''];
        yield 'ville trop longue' => ['city', str_repeat('a', 51)];
        yield 'pays absent' => ['country', null];
    }

    #[DataProvider('invalidFields')]
    public function testInvalidFieldIsRejected(string $property, ?string $value): void
    {
        $address = self::address();
        $address->{$property} = $value;

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($address);

        self::assertNotCount(0, $violations);
        foreach ($violations as $violation) {
            self::assertSame($property, $violation->getPropertyPath());
        }
    }

    private static function address(): AddressData
    {
        $address = new AddressData();
        $address->label = 'Domicile';
        $address->firstName = 'Jean';
        $address->lastName = 'Dupont';
        $address->address1 = '12 rue des Essais';
        $address->postcode = '68100';
        $address->city = 'Mulhouse';
        $address->country = 'FR';

        return $address;
    }
}
