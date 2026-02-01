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

    public function __construct(
        private readonly string $uploadDirectory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function upload(UploadedFile $file, ?string $username = null, ?string $oldFile = null, ?string $subdirectory = null): string
    {
        $this->validateMimeType($file);

        if ($oldFile) {
            $this->delete($oldFile);
        }

        if ($username) {
            $newFilename = sprintf('%s-%s.%s', $username, uniqid('', true), $file->guessExtension());
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

    public function delete(?string $filename): bool
    {
        if (!$filename) {
            return false;
        }

        $filePath = $this->uploadDirectory.'/'.$filename;

        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    public function replace(?string $oldFilename, UploadedFile $newFile, ?string $subdirectory = null): string
    {
        $this->delete($oldFilename);

        return $this->upload($newFile, $subdirectory);
    }

    private function validateMimeType(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Type MIME non autorisé : "%s". Types acceptés : %s', $mimeType, implode(', ', self::ALLOWED_MIME_TYPES)));
        }
    }
}
