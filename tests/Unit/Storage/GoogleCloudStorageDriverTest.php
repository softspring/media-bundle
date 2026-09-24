<?php

namespace Softspring\MediaBundle\Tests\Unit\Storage;

use DateTimeImmutable;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
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

    public function testRemoveDeletesObjectImmediatelyByDefault(): void
    {
        $object = $this->createMock(StorageObject::class);
        $object->expects($this->once())->method('exists')->willReturn(true);
        $object->expects($this->once())->method('delete');
        $object->expects($this->never())->method('update');

        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())->method('object')->with('path/to/image.jpg')->willReturn($object);

        $storageClient = $this->createMock(StorageClient::class);
        $storageClient->expects($this->once())->method('bucket')->with('media-bucket')->willReturn($bucket);

        $driver = new GoogleCloudStorageDriver($storageClient, 'unused-bucket');
        $driver->remove('gs://media-bucket/path/to/image.jpg');
    }

    public function testRemoveSchedulesObjectDeletionWhenConfigured(): void
    {
        $before = new DateTimeImmutable();

        $object = $this->createMock(StorageObject::class);
        $object->expects($this->once())->method('exists')->willReturn(true);
        $object->expects($this->never())->method('delete');
        $object->expects($this->once())->method('update')->with($this->callback(function (array $metadata) use ($before): bool {
            if (!isset($metadata['customTime'])) {
                return false;
            }

            $customTime = new DateTimeImmutable($metadata['customTime']);

            return $customTime->getTimestamp() >= $before->modify('+30 days')->getTimestamp()
                && $customTime->getTimestamp() <= (new DateTimeImmutable())->modify('+30 days')->getTimestamp();
        }));

        $bucket = $this->createMock(Bucket::class);
        $bucket->expects($this->once())->method('object')->with('path/to/image.jpg')->willReturn($object);

        $storageClient = $this->createMock(StorageClient::class);
        $storageClient->expects($this->once())->method('bucket')->with('media-bucket')->willReturn($bucket);

        $driver = new GoogleCloudStorageDriver($storageClient, 'unused-bucket', null, 30);
        $driver->remove('gs://media-bucket/path/to/image.jpg');
    }
}
