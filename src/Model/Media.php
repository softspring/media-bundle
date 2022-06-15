<?php

namespace Softspring\MediaBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

abstract class Media implements MediaInterface
{
    protected ?int $mediaType = null;

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

    public function getMediaType(): ?int
    {
        return $this->mediaType;
    }

    public function setMediaType(?int $mediaType): void
    {
        $this->mediaType = $mediaType;
    }

    public function isVideo(): bool
    {
        return self::MEDIA_TYPE_VIDEO === $this->getMediaType();
    }

    public function isImage(): bool
    {
        return self::MEDIA_TYPE_IMAGE === $this->getMediaType();
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
            $this->markUploadedAtNow(); // TODO mark as not uploaded ??
        }
    }

    public function getVersion(string $version): ?MediaVersionInterface
    {
        return $this->versions->filter(function (MediaVersionInterface $mediaVersion) use ($version) {
            return $mediaVersion->getVersion() == $version;
        })->first() ?: null;
    }
}
