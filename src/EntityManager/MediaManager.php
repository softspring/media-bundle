<?php

namespace Softspring\MediaBundle\EntityManager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Softspring\Component\CrudlController\Manager\CrudlEntityManagerTrait;
use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Exception\MigrateMediaException;
use Softspring\MediaBundle\Helper\TypeChecker;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\Console\Output\OutputInterface;

class MediaManager implements MediaManagerInterface
{
    use CrudlEntityManagerTrait;

    protected EntityManagerInterface $em;

    protected MediaTypesCollection $mediaTypesCollection;

    protected MediaVersionManagerInterface $mediaVersionManager;

    public function __construct(EntityManagerInterface $em, MediaTypesCollection $mediaTypesCollection, MediaVersionManagerInterface $mediaVersionManager)
    {
        $this->em = $em;
        $this->mediaTypesCollection = $mediaTypesCollection;
        $this->mediaVersionManager = $mediaVersionManager;
    }

    public function getTargetClass(): string
    {
        return MediaInterface::class;
    }

    public function createEntityForType(string $type, ?MediaInterface $media = null): MediaInterface
    {
        $typeDefinition = $this->mediaTypesCollection->getType($type);

        if (empty($typeDefinition)) {
            throw new Exception(sprintf('Invalid %s media type', $type));
        }

        if (!$media) {
            $media = $this->createEntity();
        }

        $media->setMediaType([
            'image' => MediaInterface::MEDIA_TYPE_IMAGE,
            'video' => MediaInterface::MEDIA_TYPE_VIDEO,
        ][$typeDefinition['type']] ?? MediaInterface::MEDIA_TYPE_UNKNOWN);
        $media->setType($type);

        $this->generateVersionEntities($media);

        return $media;
    }

    public function generateVersionEntities(MediaInterface $media): void
    {
        $typeDefinition = $this->mediaTypesCollection->getType($media->getType());

        if (!$media->getVersion('_original')) {
            $originalVersion = $this->mediaVersionManager->createEntity();
            $originalVersion->setVersion('_original');
            $media->addVersion($originalVersion);
        }

        foreach ($typeDefinition['versions'] as $key => $versionOptions) {
            if (!$media->getVersion($key)) {
                $this->generateVersionEntity($media, $key);
            }
        }
    }

    public function generateVersionEntity(MediaInterface $media, string $versionKey): MediaVersionInterface
    {
        $versionOptions = $this->mediaTypesCollection->getTypes()[$media->getType()]['versions'][$versionKey];
        $version = $this->mediaVersionManager->createEntity();
        $version->setVersion($versionKey);
        if (!isset($versionOptions['upload_requirements'])) {
            $version->setOriginalVersion($media->getVersion($versionOptions['from']));
        }
        $version->setOptions($versionOptions);
        $media->addVersion($version);

        return $version;
    }

    protected bool $migrating = false;

    public function isMigrating(): bool
    {
        return $this->migrating;
    }

    public function setMigrating(bool $migrating): void
    {
        $this->migrating = $migrating;
    }

    /**
     * @throws MigrateMediaException
     */
    public function migrate(MediaInterface $media, ?OutputInterface $output = null): void
    {
        try {
            $typeConfig = $this->mediaTypesCollection->getType($media->getType());

            $checkVersions = TypeChecker::checkMedia($media, $typeConfig);

            foreach ($checkVersions['ok'] as $versionId) {
                if ('_original' !== $versionId) {
                    $output && $output->writeln(sprintf(' - version "%s" is <fg=green>OK</>', $versionId));
                }
            }

            foreach ($checkVersions['new'] as $versionId) {
                $output && $output->write(sprintf(' - version "%s" is new in config, needs to be created: ', $versionId));
                try {
                    $version = $this->generateVersionEntity($media, $versionId);
                    $this->mediaVersionManager->saveEntity($version);
                    $output && $output->writeln('<fg=green>CREATED</>');
                } catch (Exception $e) {
                    $message = sprintf('Error creating version %s', $versionId);
                    if ($output) {
                        $output->writeln("<error>$message</error>");
                        $output->writeln($e->getMessage());
                    } else {
                        throw new MigrateMediaException($message, 0, $e);
                    }
                }
            }

            foreach ($checkVersions['changed'] as $versionId => $changes) {
                $changedOptionsString = implode(', ', array_map(fn ($v) => $v['string'], $changes));
                $output && $output->write(sprintf(' - version "%s" needs to be recreated (%s): ', $versionId, $changedOptionsString));
                try {
                    $media->removeVersion($oldVersion = $media->getVersion($versionId));
                    $this->mediaVersionManager->deleteEntity($oldVersion);
                    $version = $this->generateVersionEntity($media, $versionId);
                    $this->mediaVersionManager->saveEntity($version);
                    $output && $output->writeln('<fg=green>RECREATED</>');
                } catch (Exception $e) {
                    $message = sprintf('Error updating version %s', $versionId);
                    if ($output) {
                        $output->writeln("<error>$message</error>");
                        $output->writeln($e->getMessage());
                    } else {
                        throw new MigrateMediaException($message, 0, $e);
                    }
                }
            }

            foreach ($checkVersions['delete'] as $versionId) {
                $output && $output->write(sprintf(' - version "%s" to be deleted from database (has been deleted from config) ', $versionId));
                try {
                    $media->removeVersion($version = $media->getVersion($versionId));
                    $this->mediaVersionManager->deleteEntity($version);
                    $output && $output->writeln('<fg=green>DELETED</>');
                } catch (Exception $e) {
                    $message = sprintf('Error deleting version %s', $versionId);
                    if ($output) {
                        $output->writeln("<error>$message</error>");
                        $output->writeln($e->getMessage());
                    } else {
                        throw new MigrateMediaException($message, 0, $e);
                    }
                }
            }

            foreach ($media->getVersions() as $version) {
                if ($version->getSha1() && $version->getFileSize()) {
                    continue;
                }

                if (!$version->getSha1()) {
                    $version->setSha1(sha1_file($version->getUrl()));
                    $output && $output->writeln('<fg=green>Updated sha1</>');
                }

                if (!$version->getFileSize()) {
                    clearstatcache(); // prevent filesize cache problems returning 0
                    $version->setFileSize(filesize($version->getUrl()));
                    $output && $output->writeln('<fg=green>Updated file size</>');
                }

                $this->mediaVersionManager->saveEntity($version);
            }
        } catch (InvalidTypeException $e) {
            $message = sprintf('Missing media type "%s"', $media->getType());
            if ($output) {
                $output->writeln("<error>$message</error>");
                $output->writeln($e->getMessage());
            } else {
                throw new MigrateMediaException($message, 0, $e);
            }
        }
    }

    public function findDuplicates(MediaInterface $media): Collection
    {
        $qb = $this->em->getRepository(MediaInterface::class)->createQueryBuilder('m')
            ->andWhere('m.type = :type')
            ->setParameter('type', $media->getType());

        $query = $this->em->getRepository(MediaVersionInterface::class)->createQueryBuilder('mv')
            ->select('IDENTITY(mv.media)')
            ->where('mv.version = :version_original')
            ->andWhere('mv.sha1 = :sha1')
            ->getDQL();

        $qb->setParameter('version_original', '_original');
        $qb->setParameter('sha1', $media->getVersion('_original')->getSha1());

        $qb->andWhere($qb->expr()->in('m', $query));
        $qb->andWhere('m != :media');
        $qb->setParameter('media', $media->getId());

        return new ArrayCollection($qb->getQuery()->getResult());
    }
}
