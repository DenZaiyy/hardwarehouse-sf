<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * translation:extract préfixe de « __ » les messages qu'il ajoute sans les traduire : la boutique
 * les afficherait tels quels, comme « __footer.description » sur les pages en anglais.
 */
final class TranslationCatalogsTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function catalogs(): iterable
    {
        foreach (glob(\dirname(__DIR__, 2).'/translations/*.yaml') ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('catalogs')]
    public function testEveryMessageIsTranslated(string $file): void
    {
        $messages = (array) Yaml::parseFile($file);
        $untranslated = [];
        array_walk_recursive($messages, static function (mixed $message) use (&$untranslated): void {
            if (\is_string($message) && str_starts_with($message, '__')) {
                $untranslated[] = $message;
            }
        });

        self::assertSame([], $untranslated);
    }
}
