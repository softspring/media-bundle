<?php

namespace Softspring\MediaBundle\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Storage\FilesystemStorageDriver;
use Symfony\Component\HttpFoundation\File\File;

class FilesystemStorageDriverTest extends TestCase
{
    protected string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir().'/media-bundle-filesystem-driver';
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
    }

    public function testStoreUrlDownloadAndRemove(): void
    {
        $driver = new FilesystemStorageDriver($this->storagePath, '/custom-media');

        $sourcePath = tempnam(sys_get_temp_dir(), 'media-storage-source-');
        file_put_contents($sourcePath, 'stored-content');
        $file = new File($sourcePath);

        $storedUrl = $driver->store($file, 'images/example.txt');

        $this->assertSame('sfs-media-filesystem://images/example.txt', $storedUrl);
        $this->assertSame('/custom-media/images/example.txt', $driver->url($storedUrl));
        $this->assertFileExists($this->storagePath.'/images/example.txt');

        $downloadPath = tempnam(sys_get_temp_dir(), 'media-storage-download-');
        $driver->download($storedUrl, $downloadPath);

        $this->assertSame('stored-content', file_get_contents($downloadPath));

        $driver->remove($storedUrl);

        $this->assertFileDoesNotExist($this->storagePath.'/images/example.txt');
    }
}
