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

    public function mediaUrl(MediaInterface $media, $version, array $attr = []): ?string
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

    public function renderMedia(MediaInterface $media, $version, array $attr = []): string
    {
        if (is_array($version)) {
            foreach ($version as $singleVersion) {
                if ($html = $this->renderMedia($media, $singleVersion, $attr)) {
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

    public function renderPicture(MediaInterface $media, string $picture = '_default', array $attr = []): string
    {
        $config = $this->mediaTypesCollection->getType($media->getType());

        if (!isset($config['pictures'][$picture])) {
            throw new \Exception('picture config is not set for '.$media->getType());
        }

        $html = '<picture>';
        foreach ($config['pictures'][$picture]['sources'] ?? [] as $source) {
            $sourceAttrs = $source['attrs'] ?? [];
            $sourceAttrs['srcset'] = implode(', ', array_map(function ($srcset) use ($media) {
                return $this->getFinalUrl($media->getVersion($srcset['version'])).($srcset['suffix'] ? " {$srcset['suffix']}" : '');
            }, $source['srcset']));
            $html .= '<source '.$this->htmlAttributes($sourceAttrs).' />';
        }

        $html .= $this->renderImgTag($media->getVersion($config['pictures'][$picture]['img']['src_version']), $attr);
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

        return '<img '.$this->htmlAttributes($attributes).' />';
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
