<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Validator\PasswordRequirements;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * La règle affichée à l'inscription, appliquée aussi à la réinitialisation et au changement de mot de passe.
 */
final class PasswordRequirementsTest extends KernelTestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function passwords(): iterable
    {
        yield 'conforme' => ['PasswordTest168!', true];
        yield '32 caractères' => [str_repeat('Aa1!', 8), true];
        yield 'caractère spécial accentué' => ['Motdepasse12é', true];
        yield 'vide' => ['', false];
        yield '11 caractères' => ['Passwd168!a', false];
        yield '33 caractères' => [str_repeat('Aa1!', 8).'x', false];
        yield 'sans majuscule' => ['passwordtest168!', false];
        yield 'sans minuscule' => ['PASSWORDTEST168!', false];
        yield 'sans chiffre' => ['PasswordTest!!!!', false];
        yield 'sans caractère spécial' => ['PasswordTest1688', false];
    }

    #[DataProvider('passwords')]
    public function testPasswordRule(string $password, bool $accepted): void
    {
        $violations = static::getContainer()->get(ValidatorInterface::class)->validate($password, new PasswordRequirements());

        self::assertSame($accepted, 0 === \count($violations));
    }
}
