<?php

namespace App\Service\Catalog;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adresse publique d'une image du catalogue. L'API enregistre les images envoyées depuis le back-office sous un
 * chemin relatif (/images/products/{slug}/thumbnail.webp) et les sert elle-même, derrière Nginx : la boutique les
 * demande donc à l'origine de l'API, jamais à la sienne. Une URL complète (image hébergée ailleurs) reste telle quelle.
 */
final readonly class CatalogImageUrl
{
    private string $origin;

    public function __construct(#[Autowire('%env(API_BASE_URL)%')] string $apiBaseUrl)
    {
        $parts = parse_url($apiBaseUrl);
        if (!isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException(sprintf('API_BASE_URL doit être une URL complète, "%s" reçu.', $apiBaseUrl));
        }

        $this->origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** Origine des images, à autoriser dans la directive img-src de la politique de sécurité. */
    public function origin(): string
    {
        return $this->origin;
    }

    /**
     * @param string $variant suffixe d'une taille générée par l'API (ImageUploadService) : '@2x' pour la miniature
     *                        ou le logo en 800 px, '-sm' pour une image de galerie en 600 px
     */
    public function resolve(?string $path, string $variant = ''): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        if (1 === preg_match('#^https?://#i', $path)) {
            return $path;
        }

        if ('' !== $variant && str_ends_with($path, '.webp')) {
            $path = substr($path, 0, -\strlen('.webp')).$variant.'.webp';
        }

        return $this->origin.'/'.ltrim($path, '/');
    }
}
