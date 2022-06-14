<?php

namespace Softspring\MediaBundle\Media;

use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Component\HttpFoundation\File\File;

interface NameGeneratorInterface
{
    public function generateName(MediaInterface $media, string $version, File $file): string;
}
