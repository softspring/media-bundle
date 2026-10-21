<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Helper;

use Softspring\MediaBundle\Model\MediaInterface;

final class MediaSelectionChoices
{
    /** @return array<string, string> Selection value => label, in configured order. */
    public static function forMedia(MediaInterface $media, ?array $mediaTypes, array $definition): array
    {
        $available = [];
        $mode = $media->isImage() ? 'image' : ($media->isVideo() ? 'video' : null);
        if ($mode) {
            foreach ($media->getVersions() as $version) {
                $available[$mode][$version->getVersion()] = true;
            }
        }
        $available['picture'] = array_fill_keys(array_keys($definition['pictures'] ?? []), true);
        $available['video_sets'] = array_fill_keys(array_keys($definition['video_sets'] ?? []), true);

        $allowed = null === $mediaTypes ? array_map('array_keys', $available) : ($mediaTypes[$media->getType()] ?? []);
        $choices = [];
        foreach ($allowed as $kind => $names) {
            $kind = 'videoSet' === $kind ? 'video_sets' : $kind;
            foreach ((array) $names as $name) {
                if (isset($available[$kind][$name])) {
                    $prefix = 'video_sets' === $kind ? 'videoSet' : $kind;
                    $choices[$prefix.'#'.$name] = $name;
                }
            }
        }

        return $choices;
    }
}
