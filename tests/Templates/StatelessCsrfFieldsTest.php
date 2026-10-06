<?php

declare(strict_types=1);

namespace App\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Un jeton CSRF sans état (config/packages/csrf.yaml) est validé par l'origin de la requête ou par une
 * double soumission (cookie et en-tête posés par csrf_protection_controller.js). Une fois une méthode
 * utilisée dans la session, Symfony refuse les requêtes qui ne la fournissent plus : après une connexion
 * (double soumission), les formulaires du panier, validés par l'origin seule, étaient tous rejetés.
 * Le script n'est chargé que si la page contient data-controller="csrf-protection".
 */
final class StatelessCsrfFieldsTest extends TestCase
{
    public function testHandWrittenStatelessCsrfFieldsLoadTheDoubleSubmitScript(): void
    {
        $root = \dirname(__DIR__, 2);
        /** @var array{framework: array{csrf_protection: array{stateless_token_ids: list<string>}}} $config */
        $config = Yaml::parseFile($root.'/config/packages/csrf.yaml');
        $tokenIds = implode('|', array_map(preg_quote(...), $config['framework']['csrf_protection']['stateless_token_ids']));

        $missing = [];
        foreach (Finder::create()->files()->in($root.'/templates')->name('*.twig') as $template) {
            preg_match_all("/<input\\b[^>]*csrf_token\\('(?:$tokenIds)'\\)[^>]*>/", $template->getContents(), $fields);

            foreach ($fields[0] as $field) {
                if (!str_contains($field, 'data-controller="csrf-protection"')) {
                    $missing[] = $template->getRelativePathname();
                }
            }
        }

        self::assertSame([], $missing);
    }
}
