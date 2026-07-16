<?php

namespace Softspring\MediaBundle\Storage;

use Google\Cloud\Storage\StorageClient;
use Symfony\Component\HttpFoundation\File\File;

class GoogleCloudStorageDriver implements StorageDriverInterface
{
    public function __construct(protected StorageClient $storageClient, protected string $bucket, protected ?string $publicBaseUrl = null)
    {
    }

    public function store(File $file, string $destName): string
    {
        $bucket = $this->storageClient->bucket($this->bucket);
        $stgObject = $bucket->upload(fopen($file->getRealPath(), 'r'), [
            'name' => $destName,
        ]);

        return 'gs://'.$this->bucket.'/'.$stgObject->name();
    }

    public function remove(string $fileName): void
    {
        if (!str_starts_with($fileName, 'gs://')) {
            return;
        }

        [$bucket, $fileName] = explode('/', substr($fileName, 5), 2);

        $bucket = $this->storageClient->bucket($bucket);
        $object = $bucket->object($fileName);

        if ($object->exists()) {
            $object->delete();
        }
    }

    public function download(string $fileName, string $destPath): void
    {
        if (!str_starts_with($fileName, 'gs://')) {
            return;
        }

        [$bucket, $fileName] = explode('/', substr($fileName, 5), 2);

        $bucket = $this->storageClient->bucket($bucket);
        $object = $bucket->object($fileName);

        if ($object->exists()) {
            $object->downloadToFile($destPath);
        }
    }

    public function url(string $fileName): string
    {
        if (!str_starts_with($fileName, 'gs://')) {
            return $fileName;
        }

        $fileName = substr($fileName, 5);
        $parts = explode('/', $fileName, 2);

        $bucket = $parts[0];
        $filePath = $parts[1] ?? null;

        $publicBaseUrl = null !== $this->publicBaseUrl ? trim($this->publicBaseUrl) : null;
        if ($filePath && '' === $publicBaseUrl) {
            return "/$filePath";
        }

        if ($filePath && null !== $publicBaseUrl && 'null' !== strtolower($publicBaseUrl)) {
            $publicBaseUrl = rtrim($publicBaseUrl, '/');

            return "$publicBaseUrl/$filePath";
        }

        return "https://storage.googleapis.com/$bucket".($filePath ? "/$filePath" : '');
    }
}
