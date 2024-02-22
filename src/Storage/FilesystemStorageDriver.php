<?php

namespace Softspring\MediaBundle\Storage;

use Symfony\Component\HttpFoundation\File\File;

class FilesystemStorageDriver implements StorageDriverInterface
{
    public const PREFIX = 'sfs-media-filesystem://';

    public function __construct(protected string $path, protected string $url)
    {
        $this->path = rtrim($this->path, '/');
        $this->url = rtrim($this->url, '/');
    }

    public function store(File $file, string $destName): string
    {
        $fileFullPathname = "$this->path/$destName";

        $fileFullPath = pathinfo($fileFullPathname, PATHINFO_DIRNAME);
        if (!is_dir($fileFullPath)) {
            mkdir($fileFullPath, 0755, true);
        }

        copy($file->getPathname(), $fileFullPathname);

        return self::PREFIX.$destName;
    }

    public function remove(string $fileName): void
    {
        @unlink($this->getLocalFilePath($fileName));
    }

    public function download(string $fileName, string $destPath): void
    {
        copy($this->getLocalFilePath($fileName), $destPath);
    }

    public function url(string $fileName): string
    {
        if (str_starts_with($fileName, self::PREFIX)) {
            return "$this->url/".substr($fileName, strlen(self::PREFIX));
        }

        return $fileName;
    }

    protected function getLocalFilePath(string $fileName): string
    {
        if (str_starts_with($fileName, self::PREFIX)) {
            return $this->path.'/'.substr($fileName, strlen(self::PREFIX));
        }

        return $fileName;
    }
}
