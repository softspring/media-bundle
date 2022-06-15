<?php

namespace Softspring\MediaBundle\Twig\Extension;

use Softspring\MediaBundle\Render\MediaRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class RenderMediaExtension extends AbstractExtension
{
    protected MediaRenderer $mediaRenderer;

    public function __construct(MediaRenderer $mediaRenderer)
    {
        $this->mediaRenderer = $mediaRenderer;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('sfs_media_render_video', [$this->mediaRenderer, 'renderVideo'], ['is_safe' => ['html']]),
            new TwigFilter('sfs_media_render_video_set', [$this->mediaRenderer, 'renderVideoWithSources'], ['is_safe' => ['html']]),
            new TwigFilter('sfs_media_render_image', [$this->mediaRenderer, 'renderImage'], ['is_safe' => ['html']]),
            new TwigFilter('sfs_media_render_picture', [$this->mediaRenderer, 'renderPicture'], ['is_safe' => ['html']]),
            new TwigFilter('sfs_media_image_url', [$this->mediaRenderer, 'imageUrl']),
        ];
    }
}
