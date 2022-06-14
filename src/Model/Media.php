<?php

namespace Softspring\MediaBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

abstract class Media implements MediaInterface
{
    protected ?string $type = null;

    protected ?string $name = null;

    protected ?string $description = null;

    /**
     * @var MediaVersion[]|Collection|null
     */
    protected ?Collection $versions;

    protected ?int $uploadedAt = null;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getUploadedAt(): ?\DateTime
    {
        return $this->uploadedAt ? \DateTime::createFromFormat('U', $this->uploadedAt) : null;
    }

    public function markUploadedAtNow(): void
    {
        $this->uploadedAt = gmdate('U');
    }

    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(MediaVersionInterface $version): void
    {
        if (empty($this->versions[$version->getVersion()])) {
            $this->versions[$version->getVersion()] = $version;
            $version->setMedia($this);
            $this->markUploadedAtNow();
        }
    }

    public function removeVersion(MediaVersionInterface $version): void
    {
        if (!empty($this->versions[$version->getVersion()])) {
            unset($this->versions[$version->getVersion()]);
            $this->markUploadedAtNow();
        }
    }

    public function getVersion(string $version): ?MediaVersionInterface
    {
        return $this->versions->filter(function (MediaVersionInterface $mediaVersion) use ($version) {
            return $mediaVersion->getVersion() == $version;
        })->first() ?: null;
    }

    public function checkVersions(array $typeConfig): array
    {
        $checkResult = [
            'new' => [],
            'ok' => [],
            'changed' => [],
            'delete' => [],
        ];

        /** @var MediaVersionInterface $version */
        foreach ($this->getVersions() as $version) {
            if ('_original' === $version->getVersion()) {
                $checkResult['ok'][] = '_original';
                continue;
            }

            if (!isset($typeConfig['versions'][$version->getVersion()])) {
                $checkResult['delete'][] = $version->getVersion();
                continue;
            }

            $changedOptions = [];
            foreach ($version->getOptions() as $option => $value) {
                if (empty($typeConfig['versions'][$version->getVersion()][$option]) || $typeConfig['versions'][$version->getVersion()][$option] !== $value) {
                    $changedOptions[$option] = [
                        'config' => $typeConfig['versions'][$version->getVersion()][$option],
                        'db' => $value,
                        'string' => "$option: $value => {$typeConfig['versions'][$version->getVersion()][$option]}",
                    ];
                }
            }
            if (!empty($changedOptions)) {
                $checkResult['changed'][$version->getVersion()] = $changedOptions;
                continue;
            }

            $checkResult['ok'][] = $version->getVersion();
        }

        $dbVersions = $this->getVersions()->map(fn (MediaVersionInterface $version) => $version->getVersion())->toArray();
        $configuredVersions = array_keys($typeConfig['versions']);
        $newVersions = array_diff($configuredVersions, $dbVersions);
        foreach ($newVersions as $version) {
            $checkResult['new'][] = $version;
        }

        return $checkResult;
    }
}
