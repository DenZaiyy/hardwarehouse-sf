<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContentSecurityPolicyTest extends WebTestCase
{
    /**
     * Les flux Turbo ajoutent des scripts, comme celui qui referme la fenêtre modale après l'ajout d'une
     * adresse. Leur réponse n'a pas le nonce de la page : Turbo les signe avec celui de la balise
     * <meta name="csp-nonce">. Chromium accepte déjà un script inséré par le DOM grâce à 'strict-dynamic',
     * mais la spécification CSP3 ne prévoit pas cette exception pour le code inline (vérifié le 7 octobre
     * 2026 sur une page servant la politique de production).
     */
    public function testPagesGiveTurboTheNonceOfTheirPolicy(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/fr/connexion');

        self::assertResponseIsSuccessful();
        preg_match("/'nonce-([^']+)'/", (string) $client->getResponse()->headers->get('Content-Security-Policy'), $policy);
        $meta = $crawler->filter('meta[name="csp-nonce"]');
        self::assertCount(1, $meta);
        self::assertSame($policy[1] ?? null, $meta->attr('content'));
    }

    /**
     * Une iframe que frame-src n'autorise pas est bloquée, avec une erreur dans la console, même dans un
     * <noscript> : Turbo analyse les pages qu'il charge sans JavaScript (DOMParser), et le contenu des
     * <noscript> devient de vrais éléments à chaque navigation (vérifié le 7 octobre 2026).
     */
    public function testFramesOfThePageAreAllowedByThePolicy(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fr/connexion');

        preg_match('/frame-src ([^;]*)/', (string) $client->getResponse()->headers->get('Content-Security-Policy'), $directive);
        $sources = preg_split('/\s+/', trim($directive[1] ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        preg_match_all('/<iframe\b[^>]*\bsrc="([^"]+)"/', (string) $client->getResponse()->getContent(), $frames);

        $blocked = array_filter(
            $frames[1],
            static fn (string $src): bool => [] === array_filter($sources, static fn (string $source): bool => str_starts_with($src, $source)),
        );

        self::assertSame([], array_values($blocked));
    }
}
