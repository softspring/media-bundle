<?php

namespace Softspring\MediaBundle\Twig\Extension;

use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Render\MediaRenderer;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RenderMediaExtension extends AbstractExtension
{
    protected MediaRenderer $mediaRenderer;
    protected MediaTypesCollection $mediaTypesCollection;

    public function __construct(MediaRenderer $mediaRenderer, MediaTypesCollection $mediaTypesCollection)
    {
        $this->mediaRenderer = $mediaRenderer;
        $this->mediaTypesCollection = $mediaTypesCollection;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('sfs_media_render_video', [$this->mediaRenderer, 'renderVideo'], ['is_safe' => ['html']]),
            new TwigFilter('sfs_media_render_video_set', [$this->mediaRenderer, 'renderVideoWithSources'], ['is_safe' => ['html']]),
            new TwigFilter('sfs_media_render_image', [$this->mediaRenderer, 'renderImage'], ['is_safe' => ['html']]),
            new TwigFilter('sfs_media_render_picture', [$this->mediaRenderer, 'renderPicture'], ['is_safe' => ['html']]),
            new TwigFilter('sfs_media_render', [$this->mediaRenderer, 'render'], ['is_safe' => ['html']]),
            new TwigFilter('sfs_media_image_url', [$this->mediaRenderer, 'imageUrl']),
            new TwigFilter('sfs_media_type_config', [$this, 'getMediaTypeConfig']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sfs_media_type_config', [$this, 'getMediaTypeConfig']),
        ];
    }

    public function getMediaTypeConfig($typeOrMedia): ?array
    {
        if (is_string($typeOrMedia)) {
            return $this->mediaTypesCollection->getType($typeOrMedia);
        }

        if ($typeOrMedia instanceof MediaInterface) {
            return $this->mediaTypesCollection->getType($typeOrMedia->getType());
        }

        throw new \Exception('sfs_media_type_config parameter can be a string or a MediaInterface');
    }
}
