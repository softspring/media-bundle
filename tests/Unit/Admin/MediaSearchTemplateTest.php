<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\TokenParser\TransDefaultDomainTokenParser;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class MediaSearchTemplateTest extends TestCase
{
    public function testSearchRendersWithMissingOriginalAndUnknownDimensionsInStrictMode(): void
    {
        $files = new FilesystemLoader();
        $files->addPath(__DIR__.'/../../../templates', 'SfsMedia');
        $twig = new Environment(new ChainLoader([$files, new ArrayLoader([
            '@SfsComponents/paginator/pager.html.twig' => '',
        ])]), ['strict_variables' => true]);
        $twig->addExtension(new FormExtension());
        // Translation output is irrelevant here. Isolate it from differences
        // between supported Symfony/Twig translation visitor versions.
        $twig->addTokenParser(new TransDefaultDomainTokenParser());
        $twig->addFilter(new TwigFilter('trans', static fn (string $message): string => $message));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer(new TwigRendererEngine([], $twig)),
        ]));
        foreach (['form_start', 'form_end', 'form_widget', 'form_rest'] as $function) {
            $twig->addFunction(new TwigFunction($function, static fn (): string => ''));
        }
        $twig->addFunction(new TwigFunction('url', static fn (): string => '/media/create/image'));
        $twig->addFunction(new TwigFunction('sfs_media_type_config', static fn (): array => []));
        foreach (['sfs_media_render_image', 'sfs_media_render_video', 'sfs_media_render_picture', 'sfs_media_render_video_set'] as $filter) {
            $twig->addFilter(new TwigFilter($filter, static fn (): string => ''));
        }
        $filter = Forms::createFormFactory()->createBuilder()->add('text', TextType::class)->getForm()->createView();
        $filter->vars['valid_types'] = ['image'];

        $missing = new Media();
        $missing->setName('Missing original');
        $missing->setType('image');
        $missing->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $unknown = new Media();
        $unknown->setName('Unknown dimensions');
        $unknown->setType('image');
        $unknown->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $version = new MediaVersion();
        $version->setVersion('_original');
        $unknown->addVersion($version);
        $complete = new Media();
        $complete->setName('Complete image');
        $complete->setType('image');
        $complete->setMediaType(MediaInterface::MEDIA_TYPE_IMAGE);
        $original = new MediaVersion();
        $original->setVersion('_original');
        $original->setWidth(734);
        $original->setHeight(414);
        $complete->addVersion($original);

        self::assertNull($missing->getVersion('_original'));
        $html = $twig->render('@SfsMedia/admin/media/search_type.html.twig', [
            'filterForm' => $filter,
            'entities' => [$missing, $unknown, $complete],
            'media_types' => ['image' => ['name' => 'Test image']],
        ]);

        self::assertStringContainsString('Missing original', $html);
        self::assertStringContainsString('Unknown dimensions', $html);
        self::assertStringContainsString('Complete image', $html);
        self::assertSame(1, substr_count($html, '734x414'));
    }
}
