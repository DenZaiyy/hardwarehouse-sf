<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploadService
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/images')]
        private readonly string $uploadDirectory,
    ) {
    }

    /**
     * @return string the stored filename, to save on User::avatar
     */
    public function uploadAvatar(UploadedFile $file, User $user): string
    {
        $this->validateMimeType($file);
        $this->validateFileSize($file);

        // Keyed by the user's id: stable and guaranteed unique, unlike the username
        // (no DB uniqueness guarantee prior to this, and unsafe to use verbatim as a
        // filename). Re-uploading naturally replaces the previous avatar file instead
        // of accumulating orphans. $user must already be persisted (has an id).
        $userId = $user->getId();
        if (null === $userId) {
            throw new \LogicException('Cannot upload an avatar for a User that has not been persisted yet.');
        }

        $newFilename = sprintf('%d.%s', $userId, $file->guessExtension());

        try {
            $file->move($this->uploadDirectory.'/avatar', $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Impossible d\'uploader le fichier : '.$e->getMessage());
        }

        return $newFilename;
    }

    private function validateMimeType(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Type MIME non autorisé : "%s". Types acceptés : %s', $mimeType, implode(', ', self::ALLOWED_MIME_TYPES)));
        }
    }

    private function validateFileSize(UploadedFile $file): void
    {
        $maxBytes = $this->parseIniSize((string) ini_get('upload_max_filesize'));

        if ($file->getSize() > $maxBytes) {
            throw new \InvalidArgumentException('Fichier trop volumineux.');
        }
    }

    /**
     * Converts a php.ini shorthand byte value (e.g. "8M", "2G") to a plain byte count.
     * Comparing UploadedFile::getSize() (an int) directly against ini_get()'s raw string
     * does not compare sizes at all under PHP 8's string/number comparison rules.
     */
    private function parseIniSize(string $iniValue): int
    {
        if ('' === $iniValue) {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($iniValue, -1));
        $value = (int) $iniValue;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $iniValue,
        };
    }
}
