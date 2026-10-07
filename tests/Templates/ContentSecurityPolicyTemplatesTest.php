<?php

declare(strict_types=1);

namespace App\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * CspNonceListener impose script-src 'self' 'nonce-…' 'strict-dynamic' : le navigateur refuse tout code
 * JavaScript inline qui ne porte pas le nonce de la page, sans autre signe qu'une erreur dans la console.
 * Un onsubmit="return confirm(…)" ne s'exécutait donc pas : la suppression partait sans confirmation.
 */
final class ContentSecurityPolicyTemplatesTest extends TestCase
{
    public function testTemplatesHaveNoInlineEventHandler(): void
    {
        $handlers = [];
        foreach ($this->templates() as $path => $contents) {
            preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $contents, $matches);

            foreach ($matches[0] as $handler) {
                $handlers[] = $path.' :'.rtrim($handler, '"\'= ');
            }
        }

        self::assertSame([], $handlers);
    }

    /**
     * Les scripts d'un flux Turbo sont exclus : la réponse du flux n'a pas le nonce de la page, Turbo les
     * signe en les insérant. Un bloc JSON-LD n'est pas exécuté, la CSP ne le concerne pas.
     */
    public function testScriptsCarryTheNonce(): void
    {
        $unsigned = [];
        foreach ($this->templates() as $path => $contents) {
            if (str_contains($contents, '<turbo-stream')) {
                continue;
            }

            preg_match_all('/<script\b[^>]*>/', $contents, $matches);

            foreach ($matches[0] as $tag) {
                if (!str_contains($tag, 'nonce=') && !str_contains($tag, 'application/ld+json')) {
                    $unsigned[] = $path;
                }
            }
        }

        self::assertSame([], $unsigned);
    }

    /**
     * @return iterable<string, string> contenu des gabarits, indexé par leur chemin dans templates/
     */
    private function templates(): iterable
    {
        foreach (Finder::create()->files()->in(\dirname(__DIR__, 2).'/templates')->name('*.twig')->sortByName() as $template) {
            yield $template->getRelativePathname() => $template->getContents();
        }
    }
}
