<?php

namespace Softspring\MediaBundle\Model;

use Symfony\Component\HttpFoundation\File\File;

abstract class MediaVersion implements MediaVersionInterface
{
    protected ?MediaInterface $media = null;

    protected ?string $version = null;

    protected ?File $upload = null;

    protected ?string $url = null;

    protected ?int $width = null;

    protected ?int $height = null;

    protected ?int $fileSize = null;

    protected ?string $fileMimeType = null;

    protected ?int $uploadedAt = null;

    protected ?array $options = null;

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

    public function setUpload(?File $upload): void
    {
        $this->upload = $upload;

        if (!$this->uploadedAt) {
            $this->uploadedAt = gmdate('U');
        }

        if ($this->getMedia()) {
            $this->getMedia()->markUploadedAtNow();
        }
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

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): void
    {
        $this->options = $options;
    }
}
