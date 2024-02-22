<?php

namespace Softspring\MediaBundle\Render;

use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Storage\StorageDriverInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;

class MediaRenderer
{
    public function __construct(protected MediaTypesCollection $mediaTypesCollection, protected StorageDriverInterface $storageDriver)
    {
    }

    public function imageUrl(MediaInterface $media, $version, array $attr = []): ?string
    {
        if (is_array($version)) {
            foreach ($version as $singleVersion) {
                if ($url = $this->getFinalUrl($singleVersion)) {
                    return $url;
                }
            }

            return '';
        } else {
            if (!$mediaVersion = $media->getVersion($version)) {
                return '';
            }

            return $this->getFinalUrl($mediaVersion);
        }
    }

    /**
     * @throws \Exception
     */
    public function renderMediaOrArray($mediaObjectOrArray, $versionStringOrAttr = null, $attr = null): string
    {
        if (is_array($mediaObjectOrArray)) {
            return $this->renderMediaArray($mediaObjectOrArray, is_array($versionStringOrAttr) ? $versionStringOrAttr : (is_array($attr) ? $attr : []));
        }

        return $this->render($mediaObjectOrArray, $versionStringOrAttr, $attr ?: []);
    }

    /**
     * @throws \Exception
     */
    public function renderMediaArray(array $mediaArray, array $attr = []): string
    {
        return $this->render($mediaArray['media'] ?? null, $mediaArray['version'] ?? null, $attr);
    }

    /**
     * @throws \Exception
     */
    public function render(?MediaInterface $media, ?string $versionString, array $attr = []): string
    {
        if (!$media || !$versionString) {
            return '';
        }

        [$versionType, $versionName] = explode('#', $versionString, 2);

        return match ($versionType) {
            'image' => $this->renderImage($media, $versionName, $attr),
            'video' => $this->renderVideo($media, $versionName, $attr),
            'videoSet' => $this->renderVideoWithSources($media, $versionName, $attr),
            'picture' => $this->renderPicture($media, $versionName, $attr),
            default => throw new \Exception('Invalid $versionString, valid names are (image|video|picture|videoSet)#<versionName>'),
        };
    }

    public function renderVideo(MediaInterface $media, $version, array $attr = []): string
    {
        if (is_array($version)) {
            foreach ($version as $singleVersion) {
                if ($html = $this->renderVideo($media, $singleVersion, $attr)) {
                    return $html;
                }
            }

            return '';
        } else {
            if (!$mediaVersion = $media->getVersion($version)) {
                return '';
            }

            // it could be the poster
            if (str_starts_with($mediaVersion->getFileMimeType(), 'image/')) {
                return $this->renderImgTag($mediaVersion, $attr);
            }

            return $this->renderVideoTag($mediaVersion, $attr);
        }
    }

    public function renderImage(?MediaInterface $media, $version, array $attr = []): string
    {
        if (!$media) {
            return '';
        }

        if (is_array($version)) {
            foreach ($version as $singleVersion) {
                if ($html = $this->renderImage($media, $singleVersion, $attr)) {
                    return $html;
                }
            }

            return '';
        } else {
            if (!$mediaVersion = $media->getVersion($version)) {
                return '';
            }

            return $this->renderImgTag($mediaVersion, $attr);
        }
    }

    /**
     * @throws InvalidTypeException
     * @throws \Exception
     */
    public function renderPicture(?MediaInterface $media, string $picture = '_default', array $pictureAttr = [], array $imgAttr = []): string
    {
        if (!$media) {
            return '';
        }

        $config = $this->mediaTypesCollection->getType($media->getType());

        if (!isset($config['pictures'][$picture])) {
            throw new \Exception('picture config is not set for '.$media->getType());
        }

        $html = '<picture '.$this->htmlAttributes($pictureAttr).'>';
        foreach ($config['pictures'][$picture]['sources'] ?? [] as $source) {
            $sourceAttrs = $source['attrs'] ?? [];
            $sourceAttrs['srcset'] = implode(', ', array_map(function ($srcset) use ($media) {
                return $this->getFinalUrl($media->getVersion($srcset['version'])).($srcset['suffix'] ? " {$srcset['suffix']}" : '');
            }, $source['srcset']));
            $html .= '<source '.$this->htmlAttributes($sourceAttrs).' />';
        }

        $html .= $this->renderImgTag($media->getVersion($config['pictures'][$picture]['img']['src_version']), $imgAttr);
        $html .= '</picture>';

        return $html;
    }

    protected function htmlAttributes(array $attributes): string
    {
        array_walk($attributes, function (&$value, $attribute) {
            $value = "$attribute=\"$value\"";
        });

        return implode(' ', $attributes);
    }

    protected function renderImgTag(?MediaVersionInterface $version, array $attr = []): ?string
    {
        if (!$version) {
            return null;
        }

        $attributes = array_merge([
            'width' => $version->getWidth(),
            'height' => $version->getHeight(),
        ], $attr);

        $attributes['src'] = $this->getFinalUrl($version);
        $attributes['alt'] = $version->getMedia()->getName() ?: $version->getMedia()->getDescription();

        return '<img '.$this->htmlAttributes($attributes).' />';
    }

    protected function renderVideoTag(?MediaVersionInterface $version, array $attr = []): ?string
    {
        if (!$version) {
            return null;
        }

        $attributes = $attr;
        $attributes['src'] = $this->getFinalUrl($version);

        if (isset($attributes['controls']) && false !== $attributes['controls']) {
            $attributes['controls'] = '';
        } else {
            unset($attributes['controls']);
        }

        return '<video '.$this->htmlAttributes($attributes).' />';
    }

    /**
     * @throws InvalidTypeException
     * @throws \Exception
     */
    public function renderVideoWithSources(MediaInterface $media, string $video = '_default', array $videoTagAttr = []): string
    {
        $config = $this->mediaTypesCollection->getType($media->getType());

        if (!isset($config['video_sets'][$video])) {
            throw new \Exception('video_sets config is not set for '.$media->getType());
        }

        $attrs = array_merge($config['video_sets'][$video]['attrs'] ?? [], $videoTagAttr);

        if (!empty($config['video_sets'][$video]['poster_version'])) {
            $posterVersion = $media->getVersion($config['video_sets'][$video]['poster_version']);
            $attrs['poster'] = $this->getFinalUrl($posterVersion);
        }

        $html = '<video '.$this->htmlAttributes($attrs).'>';
        foreach ($config['video_sets'][$video]['sources'] ?? [] as $source) {
            $version = $media->getVersion($source['version']);

            if (!$version) {
                continue;
            }

            $sourceAttrs = $source['attrs'] ?? [];
            $sourceAttrs['src'] = $this->getFinalUrl($version);
            $sourceAttrs['type'] = $version->getFileMimeType();
            $html .= '<source '.$this->htmlAttributes($sourceAttrs).' />';
        }
        $html .= '</video>';

        return $html;
    }

    protected function getFinalUrl(?MediaVersionInterface $version): ?string
    {
        if (!$version) {
            return null;
        }

        return $this->storageDriver->url($version->getUrl());
    }
}
