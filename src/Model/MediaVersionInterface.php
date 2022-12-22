<?php

namespace Softspring\MediaBundle\Model;

use Symfony\Component\HttpFoundation\File\File;

interface MediaVersionInterface
{
    public function getId();

    public function getMedia(): ?MediaInterface;

    public function setMedia(?MediaInterface $media): void;

    public function getVersion(): ?string;

    public function setVersion(?string $version): void;

    public function getUpload(): ?File;

    public function setUpload(?File $upload, bool $keepTmpFile = false): void;

    public function isKeepTmpFile(): bool;

    public function getUrl(): ?string;

    public function setUrl(?string $url): void;

    public function getWidth(): ?int;

    public function setWidth(?int $width): void;

    public function getHeight(): ?int;

    public function setHeight(?int $height): void;

    public function getFileSize(): ?int;

    public function setFileSize(?int $fileSize): void;

    public function getFileMimeType(): ?string;

    public function setFileMimeType(?string $fileMimeType): void;

    public function getUploadedAt(): ?\DateTime;

    public function getGeneratedAt(): ?\DateTime;

    public function getOptions(): ?array;

    public function setOptions(?array $options): void;

    public function getOriginalVersion(): ?MediaVersionInterface;

    public function setOriginalVersion(?MediaVersionInterface $originalVersion): void;
}
