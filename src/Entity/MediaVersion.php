<?php

namespace Softspring\MediaBundle\Entity;

use Softspring\MediaBundle\Model\MediaVersion as MediaVersionModel;

class MediaVersion extends MediaVersionModel
{
    protected ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return ''.$this->getId();
    }
}
