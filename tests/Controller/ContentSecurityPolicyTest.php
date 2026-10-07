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
}
