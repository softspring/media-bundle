<?php

namespace Softspring\MediaBundle\Render;

use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;

class MediaRenderer
{
    protected MediaTypesCollection $mediaTypesCollection;

    public function __construct(MediaTypesCollection $mediaTypesCollection)
    {
        $this->mediaTypesCollection = $mediaTypesCollection;
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

    public function renderMediaOrArray($mediaObjectOrArray, $versionStringOrAttr = null, $attr = null): string
    {
        if (is_array($mediaObjectOrArray)) {
            return $this->renderMediaArray($mediaObjectOrArray, is_array($versionStringOrAttr) ? $versionStringOrAttr : (is_array($attr) ? $attr : []));
        }

        return $this->render($mediaObjectOrArray, $versionStringOrAttr, $attr);
    }

    public function renderMediaArray(array $mediaArray, array $attr = []): string
    {
        return $this->render($mediaArray['media'] ?? null, $mediaArray['version'] ?? null, $attr);
    }

    public function render(?MediaInterface $media, ?string $versionString, array $attr = []): string
    {
        if (!$media || !$versionString) {
            return '';
        }

        [$versionType, $versionName] = explode('#', $versionString, 2);

        switch ($versionType) {
            case 'image':
                return $this->renderImage($media, $versionName, $attr);

            case 'video':
                return $this->renderVideo($media, $versionName, $attr);

            case 'picture':
                return $this->renderPicture($media, $versionName, $attr);

            default:
                throw new \Exception('Invalid $versionString, valid names are (image|video|picture)#<versionName>');
        }
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

            return $this->renderVideoTag($mediaVersion, $attr);
        }
    }

    public function renderImage(MediaInterface $media, $version, array $attr = []): string
    {
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

    public function renderPicture(MediaInterface $media, string $picture = '_default', array $pictureAttr = [], array $imgAttr = []): string
    {
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
        array_walk($attributes, function (&$value, $attribute) { $value = "$attribute=\"$value\""; });

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

    public function renderVideoWithSources(MediaInterface $media, string $video = '_default', array $videoTagAttr = []): string
    {
        $config = $this->mediaTypesCollection->getType($media->getType());

        if (!isset($config['video_sources'][$video])) {
            throw new \Exception('video_sources config is not set for '.$media->getType());
        }

        if (isset($videoTagAttr['controls']) && false !== $videoTagAttr['controls']) {
            $videoTagAttr['controls'] = '';
        } else {
            unset($videoTagAttr['controls']);
        }

        $html = '<video '.$this->htmlAttributes($videoTagAttr).'>';
        foreach ($config['video_sources'][$video]['sources'] ?? [] as $source) {
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

        if ('gs://' == substr($version->getUrl(), 0, 5)) {
            return 'https://storage.googleapis.com/'.substr($version->getUrl(), 5);
        }

        return $version->getUrl();
    }
}
