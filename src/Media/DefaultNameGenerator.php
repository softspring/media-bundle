<?php

namespace Softspring\MediaBundle\Media;

use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Component\HttpFoundation\File\File;

class DefaultNameGenerator implements NameGeneratorInterface
{
    public function generateName(MediaInterface $media, string $version, File $file): string
    {
        if ('_original' === $version) {
            $versionName = '';
        } elseif (str_starts_with($version, '_')) {
            $versionName = substr($version, 1);
        } else {
            $versionName = $version;
        }

        if ($media->getId()) {
            return $media->getId().'/'.sha1(time().microtime()).('' !== $versionName && '0' !== $versionName ? ".$versionName" : '').'.'.$file->guessExtension();
        }

        return sha1(time().$file->getRealPath()).('' !== $versionName && '0' !== $versionName ? ".$versionName" : '').'.'.$file->guessExtension();
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
