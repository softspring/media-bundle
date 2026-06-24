<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\EntityManager;

use BadMethodCallException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\EntityManager\MediaManager;
use Softspring\MediaBundle\EntityManager\MediaVersionManagerInterface;
use Softspring\MediaBundle\Exception\MigrateMediaException;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Type\ConfigMediaTypeProvider;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\HttpFoundation\File\File;

class MediaManagerTest extends TestCase
{
    private const TYPES = [
        'image' => [
            'type' => 'image',
            'versions' => [
                'thumbnail' => [
                    'from' => '_original',
                    'type' => 'jpeg',
                ],
                'large' => [
                    'from' => '_original',
                    'type' => 'jpeg',
                ],
                'manual' => [
                    'upload_requirements' => [
                        'required' => false,
                    ],
                ],
            ],
        ],
        'video' => [
            'type' => 'video',
            'versions' => [],
        ],
        'document' => [
            'type' => 'document',
            'versions' => [],
        ],
    ];

    public function testCreatesMediaForConfiguredType(): void
    {
        $manager = $this->createManager();

        $media = $manager->createEntityForType('image');

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame(MediaInterface::MEDIA_TYPE_IMAGE, $media->getMediaType());
        $this->assertSame('image', $media->getType());
        $this->assertNotNull($media->getVersion('_original'));
        $this->assertNotNull($media->getVersion('thumbnail'));
        $this->assertSame($media->getVersion('_original'), $media->getVersion('thumbnail')->getOriginalVersion());
        $this->assertSame(['from' => '_original', 'type' => 'jpeg'], $media->getVersion('thumbnail')->getOptions());
        $this->assertNull($media->getVersion('manual')->getOriginalVersion());
    }

    public function testUpdatesExistingMediaForConfiguredType(): void
    {
        $manager = $this->createManager();
        $media = new Media();

        $this->assertSame($media, $manager->createEntityForType('video', $media));
        $this->assertSame(MediaInterface::MEDIA_TYPE_VIDEO, $media->getMediaType());
        $this->assertSame('video', $media->getType());
        $this->assertNotNull($media->getVersion('_original'));
    }

    public function testUnknownConfiguredTypeUsesUnknownMediaType(): void
    {
        $media = $this->createManager()->createEntityForType('document');

        $this->assertSame(MediaInterface::MEDIA_TYPE_UNKNOWN, $media->getMediaType());
    }

    public function testGenerateVersionEntitiesDoesNotDuplicateExistingVersions(): void
    {
        $versionManager = new TestingMediaVersionManager();
        $manager = $this->createManager($versionManager);
        $media = new Media();
        $media->setType('image');
        $media->addVersion(new MediaVersion('_original'));

        $manager->generateVersionEntities($media);
        $manager->generateVersionEntities($media);

        $this->assertSame(['thumbnail', 'large', 'manual'], array_map(
            static fn (MediaVersionInterface $version): ?string => $version->getVersion(),
            array_filter($versionManager->created, static fn (MediaVersionInterface $version): bool => '_original' !== $version->getVersion())
        ));
        $this->assertCount(4, $media->getVersions());
    }

    public function testMigrateCreatesUpdatesAndDeletesVersions(): void
    {
        $versionManager = new TestingMediaVersionManager();
        $manager = $this->createManager($versionManager);
        $media = new Media();
        $media->setType('image');

        $original = new MediaVersion('_original', $media);
        $original->setOptions([]);

        $thumbnail = new MediaVersion('thumbnail', $media);
        $thumbnail->setOriginalVersion($original);
        $thumbnail->setOptions(['type' => 'png']);

        $old = new MediaVersion('old', $media);
        $old->setOptions([]);

        $manager->migrate($media);

        $this->assertSame(['large', 'thumbnail'], array_map(
            static fn (MediaVersionInterface $version): ?string => $version->getVersion(),
            $versionManager->saved
        ));
        $this->assertSame(['thumbnail', 'old'], array_map(
            static fn (MediaVersionInterface $version): ?string => $version->getVersion(),
            $versionManager->deleted
        ));
        $this->assertNotSame($thumbnail, $media->getVersion('thumbnail'));
        $this->assertSame(['from' => '_original', 'type' => 'jpeg'], $media->getVersion('thumbnail')->getOptions());
        $this->assertNull($media->getVersion('old'));
    }

    public function testMigrateThrowsDomainExceptionWhenTypeIsMissing(): void
    {
        $media = new Media();
        $media->setType('missing');

        $this->expectException(MigrateMediaException::class);
        $this->expectExceptionMessage('Missing media type "missing"');

        $this->createManager()->migrate($media);
    }

    public function testMigratingFlag(): void
    {
        $manager = $this->createManager();

        $this->assertFalse($manager->isMigrating());

        $manager->setMigrating(true);

        $this->assertTrue($manager->isMigrating());
    }

    public function testDuplicateStats(): void
    {
        $manager = $this->createManager();
        $manager->duplicates = [
            ['duplicated' => 2],
            ['duplicated' => 4],
        ];

        $this->assertSame([
            'different' => 2,
            'total' => 6,
            'duplicates' => [
                ['duplicated' => 2],
                ['duplicated' => 4],
            ],
        ], $manager->getDuplicatesStats());
    }

    private function createManager(?TestingMediaVersionManager $versionManager = null): TestingMediaManager
    {
        return new TestingMediaManager(
            $this->createMock(EntityManagerInterface::class),
            new MediaTypesCollection([new ConfigMediaTypeProvider(self::TYPES)]),
            $versionManager ?? new TestingMediaVersionManager(),
            new TestingStorageDriver(),
        );
    }
}

class TestingMediaManager extends MediaManager
{
    public array $duplicates = [];

    public function createEntity(): object
    {
        return new Media();
    }

    public function findDuplicates(): array
    {
        return $this->duplicates;
    }
}

class TestingMediaVersionManager implements MediaVersionManagerInterface
{
    /**
     * @var MediaVersionInterface[]
     */
    public array $created = [];

    /**
     * @var MediaVersionInterface[]
     */
    public array $saved = [];

    /**
     * @var MediaVersionInterface[]
     */
    public array $deleted = [];

    public function getTargetClass(): string
    {
        return MediaVersionInterface::class;
    }

    public function getEntityClass(): string
    {
        return MediaVersion::class;
    }

    public function getEntityClassReflection(): ReflectionClass
    {
        return new ReflectionClass(MediaVersion::class);
    }

    public function getRepository(): EntityRepository
    {
        throw new BadMethodCallException('Repository is not used by these tests');
    }

    public function createEntity(): object
    {
        $version = new MediaVersion();
        $this->created[] = $version;

        return $version;
    }

    public function saveEntity(object $entity): void
    {
        $this->saved[] = $entity;
    }

    public function deleteEntity(object $entity): void
    {
        $this->deleted[] = $entity;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        throw new BadMethodCallException('Entity manager is not used by these tests');
    }
}

class TestingStorageDriver implements StorageDriverInterface
{
    public function store(File $file, string $destName): string
    {
        return $destName;
    }

    public function remove(string $fileName): void
    {
    }

    public function download(string $fileName, string $destPath): void
    {
    }

    public function url(string $fileName): string
    {
        return $fileName;
    }
}
