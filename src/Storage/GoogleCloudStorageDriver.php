<?php

namespace Softspring\MediaBundle\Storage;

use Google\Cloud\Storage\StorageClient;
use Symfony\Component\HttpFoundation\File\File;

class GoogleCloudStorageDriver implements StorageDriverInterface
{
    protected StorageClient $storageClient;

    protected string $bucket;

    public function __construct(StorageClient $storageClient, string $bucket)
    {
        $this->storageClient = $storageClient;
        $this->bucket = $bucket;
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
        if ('gs://' !== substr($fileName, 0, 5)) {
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
        if ('gs://' !== substr($fileName, 0, 5)) {
            return;
        }

        [$bucket, $fileName] = explode('/', substr($fileName, 5), 2);

        $bucket = $this->storageClient->bucket($bucket);
        $object = $bucket->object($fileName);

        if ($object->exists()) {
            $object->downloadToFile($destPath);
        }
    }
}
