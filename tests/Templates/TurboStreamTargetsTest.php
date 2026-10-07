<?php

declare(strict_types=1);

namespace App\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * L'attribut target d'un flux Turbo désigne l'identifiant d'un élément, et Turbo ignore en silence une cible
 * introuvable : avec target="body", le script qui referme la fenêtre modale après l'ajout d'une adresse
 * n'était jamais inséré. Pour viser une balise, Turbo prévoit l'attribut targets, qui prend un sélecteur CSS.
 */
final class TurboStreamTargetsTest extends TestCase
{
    public function testStreamsTargetElementsThatExist(): void
    {
        $templates = [];
        foreach (Finder::create()->files()->in(\dirname(__DIR__, 2).'/templates')->name('*.twig')->sortByName() as $template) {
            $templates[$template->getRelativePathname()] = $template->getContents();
        }

        preg_match_all('/\bid="([^"{]+)"/', implode("\n", $templates), $ids);

        $missing = [];
        foreach ($templates as $path => $contents) {
            preg_match_all('/<turbo-stream\b[^>]*\btarget="([^"{]+)"/', $contents, $targets);

            foreach (array_diff($targets[1], $ids[1]) as $target) {
                $missing[] = $path.' : '.$target;
            }
        }

        self::assertSame([], $missing);
    }
}
