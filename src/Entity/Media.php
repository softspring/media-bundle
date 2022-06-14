<?php

namespace Softspring\MediaBundle\Entity;

use Softspring\MediaBundle\Model\Media as MediaModel;

class Media extends MediaModel
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
