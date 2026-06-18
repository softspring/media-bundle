<?php

namespace Softspring\MediaBundle\Model;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;
use Softspring\TranslatableBundle\Model\Translation;

abstract class Media implements MediaInterface
{
    protected ?int $mediaType = null;

    protected ?bool $private = null;

    protected ?string $type = null;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?Collection $versions;

    protected ?int $createdAt = null;

    protected ?string $sha1 = null;

    protected ?Translation $altTexts = null;

    protected ?array $metadata = null;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }

    public function getPrivate(): ?bool
    {
        return $this->private;
    }

    public function setPrivate(?bool $private): void
    {
        $this->private = $private;
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

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt ? DateTime::createFromFormat('U', "$this->createdAt") : null;
    }

    public function setCreatedAt(?int $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function markCreatedAtNow(): void
    {
        $this->createdAt = (int) gmdate('U');
    }

    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function setVersions(?Collection $versions): void
    {
        $this->versions = $versions;
    }

    /**
     * @return ?MediaVersionInterface
     * @throws InvalidArgumentException
     */
    public function __get(string $id): mixed
    {
        if (method_exists($this, 'get'.$id)) {
            return $this->{'get'.$id}();
        }

        if (!str_starts_with($id, 'version_')) {
            throw new InvalidArgumentException("Property $id not found");
        }

        return $this->getVersion(substr($id, 8));
    }

    /**
     * @return ?void
     * @throws InvalidArgumentException
     */
    public function __set(string $id, mixed $value)
    {
        if (method_exists($this, 'set'.$id)) {
            $this->{'set'.$id}($value);

            return;
        }

        if (!str_starts_with($id, 'version_')) {
            throw new InvalidArgumentException("Property $id not found");
        }

        if (!$value instanceof MediaVersionInterface) {
            throw new InvalidArgumentException("Property $id must be an instance of MediaVersionInterface");
        }

        $value->setVersion(substr($id, 8));
        $this->addVersion($value);
    }

    public function __isset(string $id): bool
    {
        return str_starts_with($id, 'version_');
    }

    public function addVersion(MediaVersionInterface $version): void
    {
        $exisingVersion = $this->getVersion($version->getVersion());
        if (!$exisingVersion instanceof MediaVersionInterface) {
            $this->versions->set($version->getVersion(), $version);
            $version->setMedia($this);
        }
    }

    public function removeVersion(MediaVersionInterface $version): void
    {
        $exisingVersion = $this->getVersion($version->getVersion());
        if ($exisingVersion instanceof MediaVersionInterface) {
            $this->versions->offsetUnset($version->getVersion());
            $version->setMedia(null);
        }
    }

    public function getVersion(string $version): ?MediaVersionInterface
    {
        return $this->versions->filter(function (MediaVersionInterface $mediaVersion) use ($version): bool {
            return $mediaVersion->getVersion() == $version;
        })->first() ?: null;
    }

    public function getSha1(): ?string
    {
        return $this->sha1;
    }

    public function setSha1(?string $sha1): void
    {
        $this->sha1 = $sha1;
    }

    public function getAltTexts(): ?Translation
    {
        return $this->altTexts;
    }

    public function setAltTexts(?Translation $altTexts): void
    {
        $this->altTexts = $altTexts;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function setMetadataField(string $field, mixed $value): void
    {
        $metadata = $this->getMetadata() ?? [];
        $metadata[$field] = $value;
        $this->setMetadata($metadata);
    }

    public function getMetadataField(string $field, mixed $default = null): mixed
    {
        return $this->getMetadata()[$field] ?? $default;
    }

    public function removeMetadataField(string $field): void
    {
        $metadata = $this->getMetadata() ?? [];
        unset($metadata[$field]);
        $this->setMetadata([] !== $metadata ? $metadata : null);
    }
}
