<?php

namespace Softspring\MediaBundle\Tests\Unit\Storage;

use Google\Cloud\Storage\StorageClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Storage\GoogleCloudStorageDriver;

class GoogleCloudStorageDriverTest extends TestCase
{
    #[DataProvider('urlProvider')]
    public function testUrl(string $storedUrl, ?string $publicBaseUrl, string $expectedUrl): void
    {
        $driver = new GoogleCloudStorageDriver($this->createMock(StorageClient::class), 'media-bucket', $publicBaseUrl);

        $this->assertSame($expectedUrl, $driver->url($storedUrl));
    }

    public static function urlProvider(): iterable
    {
        yield 'non gcs url' => [
            'https://example.com/image.jpg',
            'https://cdn.example.com/media',
            'https://example.com/image.jpg',
        ];

        yield 'gcs url without public base url' => [
            'gs://media-bucket/path/to/image.jpg',
            null,
            'https://storage.googleapis.com/media-bucket/path/to/image.jpg',
        ];

        yield 'gcs url with public base url' => [
            'gs://media-bucket/path/to/image.jpg',
            'https://cdn.example.com/media',
            'https://cdn.example.com/media/path/to/image.jpg',
        ];

        yield 'gcs url with trailing slash in public base url' => [
            'gs://media-bucket/path/to/image.jpg',
            'https://cdn.example.com/media/',
            'https://cdn.example.com/media/path/to/image.jpg',
        ];

        yield 'gcs url with string null public base url' => [
            'gs://media-bucket/path/to/image.jpg',
            'null',
            'https://storage.googleapis.com/media-bucket/path/to/image.jpg',
        ];

        yield 'gcs url with empty public base url' => [
            'gs://media-bucket/path/to/image.jpg',
            '',
            '/path/to/image.jpg',
        ];

        yield 'gcs bucket url without path' => [
            'gs://media-bucket',
            'https://cdn.example.com/media',
            'https://storage.googleapis.com/media-bucket',
        ];
    }
}
