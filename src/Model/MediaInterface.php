<?php

namespace Softspring\MediaBundle\Model;

use Doctrine\Common\Collections\Collection;

interface MediaInterface
{
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

    public function checkVersions(array $typeConfig): array;

    public function getUploadedAt(): ?\DateTime;

    public function markUploadedAtNow(): void;
}
