<?php

namespace Softspring\MediaBundle\Model;

use Doctrine\Common\Collections\Collection;

interface MediaInterface
{
    const MEDIA_TYPE_UNKNOWN = null;
    const MEDIA_TYPE_IMAGE = 1;
    const MEDIA_TYPE_VIDEO = 2;
    const MEDIA_TYPE_OTHER = 99;

    public function getMediaType(): ?int;

    public function setMediaType(?int $mediaType): void;

    public function isVideo(): bool;

    public function isImage(): bool;

    public function getType(): ?string;

    public function setType(?string $type): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    /**
     * @return Collection|MediaVersionInterface[]
     */
    public function getVersions(): Collection;

    public function addVersion(MediaVersionInterface $version): void;

    public function removeVersion(MediaVersionInterface $version): void;

    public function getVersion(string $version): ?MediaVersionInterface;

    public function getUploadedAt(): ?\DateTime;

    public function markUploadedAtNow(): void;
}
