<?php

namespace Softspring\MediaBundle\Model;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class MediaVersion implements MediaVersionInterface
{
    protected ?MediaInterface $media = null;

    protected ?string $version = null;

    protected ?File $upload = null;

    protected bool $keepTmpFile = false;

    protected ?string $url = null;

    protected ?int $width = null;

    protected ?int $height = null;

    protected ?int $fileSize = null;

    protected ?string $fileMimeType = null;

    protected ?int $uploadedAt = null;

    protected ?int $generatedAt = null;

    protected ?array $options = null;

    /**
     * this field is not mapped.
     */
    protected ?MediaVersionInterface $originalVersion = null;

    public function __construct(string $version = null, MediaInterface $media = null)
    {
        $this->setVersion($version);
        if ($media instanceof MediaInterface) {
            $media->addVersion($this);
        }
    }

    public function getMedia(): ?MediaInterface
    {
        return $this->media;
    }

    public function setMedia(?MediaInterface $media): void
    {
        $this->media = $media;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    public function getUpload(): ?File
    {
        return $this->upload;
    }

    public function setUpload(?File $upload, bool $keepTmpFile = false): void
    {
        $this->upload = $upload;
        $this->keepTmpFile = $keepTmpFile;

        if ($upload instanceof UploadedFile) {
            $this->uploadedAt = gmdate('U');
        } elseif ($upload instanceof File) {
            $this->generatedAt = gmdate('U');
        }
    }

    public function isKeepTmpFile(): bool
    {
        return $this->keepTmpFile;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): void
    {
        $this->height = $height;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function getFileMimeType(): ?string
    {
        return $this->fileMimeType;
    }

    public function setFileMimeType(?string $fileMimeType): void
    {
        $this->fileMimeType = $fileMimeType;
    }

    public function getUploadedAt(): ?\DateTime
    {
        return $this->uploadedAt ? \DateTime::createFromFormat('U', $this->uploadedAt) : null;
    }

    public function getGeneratedAt(): ?\DateTime
    {
        return $this->generatedAt ? \DateTime::createFromFormat('U', $this->generatedAt) : null;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): void
    {
        $this->options = $options;
    }

    public function getOriginalVersion(): ?MediaVersionInterface
    {
        return $this->originalVersion;
    }

    public function setOriginalVersion(?MediaVersionInterface $originalVersion): void
    {
        $this->originalVersion = $originalVersion;
    }
}
