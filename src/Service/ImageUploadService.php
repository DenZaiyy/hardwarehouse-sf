<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageUploadService
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    private const array UPLOAD_TYPES = [
        'avatar',
        'product',
        'category',
    ];

    public function __construct(
        private readonly string $uploadDirectory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function upload(UploadedFile $file, ?string $username = null, ?string $subdirectory = null, ?string $type = null): string
    {
        $this->validateMimeType($file);
        $this->valideFileSize($file);

        if ($username) {
            $newFilename = sprintf('%s-%s.%s', $username, uniqid('', true), $file->guessExtension());
        } elseif (in_array($type, self::UPLOAD_TYPES, true)) {
            switch ($type) {
                case 'avatar':
                    $subdirectory = 'avatar';
                    $newFilename = sprintf('%s.%s', strtolower((string) $username), $file->guessExtension());
                    break;
                case 'product':
                    $subdirectory = 'product';
                    $newFilename = sprintf('%s-%s.%s', $file->getClientOriginalName(), uniqid('', true), $file->guessExtension());
                    break;
                case 'category':
                    $subdirectory = 'category';
                    $newFilename = sprintf('%s-%s.%s', $file->getClientOriginalName(), uniqid('', true), $file->guessExtension());
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Type d\'upload non géré : "%s"', $type));
            }
        } else {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = strtolower($this->slugger->slug($originalFilename));
            $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid('', true), $file->guessExtension());
        }

        $targetDirectory = $subdirectory
            ? $this->uploadDirectory.'/'.$subdirectory
            : $this->uploadDirectory;

        try {
            $file->move($targetDirectory, $newFilename);
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

    private function valideFileSize(UploadedFile $file): void
    {
        $maxSize = ini_get('upload_max_filesize');

        if ($file->getSize() > $maxSize) {
            throw new \InvalidArgumentException('Fichier trop volumineux.');
        }
    }
}
