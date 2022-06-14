<?php

namespace Softspring\MediaBundle\Storage;

use Symfony\Component\HttpFoundation\File\File;

interface StorageDriverInterface
{
    public function store(File $file, string $destName): string;

    public function remove(string $fileName): void;

    public function download(string $fileName, string $destPath): void;
}
