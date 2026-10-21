<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Form\MediaModalType;
use Softspring\MediaBundle\Form\MediaVersionModalType;
use Softspring\MediaBundle\Helper\MediaSelectionChoices;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Type\ConfigMediaTypeProvider;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Routing\RouterInterface;

final class MediaVersionModalTypeTest extends TestCase
{
    private const DEFINITION = ['private' => false, 'type' => 'image', 'versions' => ['_original' => [], 'lg_avif' => []], 'pictures' => ['_default' => [], 'other' => []], 'video_sets' => ['cms_preview' => [], 'other' => []]];

    private function media(bool $video = false, string $type = 'content'): MediaInterface
    {
        $media = $this->createMock(MediaInterface::class);
        $media->method('getType')->willReturn($type);
        $media->method('isImage')->willReturn(!$video);
        $media->method('isVideo')->willReturn($video);
        $versions = [];
        foreach (['_original', 'lg_avif'] as $name) {
            $version = $this->createMock(MediaVersionInterface::class);
            $version->method('getVersion')->willReturn($name);
            $versions[] = $version;
        }
        $media->method('getVersions')->willReturn(new ArrayCollection($versions));

        return $media;
    }

    private function form(?array $allowed, ?MediaInterface $media): FormInterface
    {
        $types = new MediaTypesCollection([new ConfigMediaTypeProvider(['content' => self::DEFINITION])]);
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/media/search');
        $factory = Forms::createFormFactoryBuilder()->addExtension(new PreloadedExtension([
            new MediaModalType($this->createMock(EntityManagerInterface::class), $router, $types),
            new MediaVersionModalType($types),
        ], []))->getFormFactory();
        $builder = $factory->createBuilder(MediaVersionModalType::class, null, ['media_types' => $allowed]);
        // Isolate the selector's validation from the repository lookup.
        $builder->get('media')->resetModelTransformers()->resetViewTransformers()->addModelTransformer(new CallbackTransformer(static fn ($value): string => '', static fn ($value): ?MediaInterface => $value ? $media : null));

        return $builder->getForm();
    }

    public function testPictureOnlyConfigurationHidesIndividualVersions(): void
    {
        self::assertSame(['picture#_default' => '_default'], MediaSelectionChoices::forMedia($this->media(), ['content' => ['picture' => '_default']], self::DEFINITION));
    }

    public function testArraysKeepConfiguredOrderAndExcludeMissingRepresentations(): void
    {
        self::assertSame(['picture#other' => 'other', 'picture#_default' => '_default', 'image#lg_avif' => 'lg_avif'], MediaSelectionChoices::forMedia($this->media(), ['content' => ['picture' => ['other', 'missing', '_default'], 'image' => ['lg_avif', 'missing']]], self::DEFINITION));
    }

    public function testVideoSetsUseCanonicalSelectionPrefix(): void
    {
        self::assertSame(['videoSet#cms_preview' => 'cms_preview'], MediaSelectionChoices::forMedia($this->media(true), ['content' => ['video_sets' => 'cms_preview']], self::DEFINITION));
    }

    public function testNullConfigurationKeepsAllAvailableChoices(): void
    {
        $choices = MediaSelectionChoices::forMedia($this->media(), null, self::DEFINITION);
        self::assertArrayHasKey('image#_original', $choices);
        self::assertArrayHasKey('picture#other', $choices);
        self::assertSame([], MediaSelectionChoices::forMedia($this->media(), [], self::DEFINITION));
    }

    public function testServerRejectsManipulatedVersions(): void
    {
        foreach (['image#_original', 'picture#other', 'picture#missing', 'videoSet#cms_preview', '', '_default'] as $value) {
            $form = $this->form(['content' => ['picture' => '_default']], $this->media());
            $form->submit(['media' => 'existing-id', 'version' => $value]);
            self::assertFalse($form->isValid(), $value);
        }
    }

    public function testAllowedPictureIsValidAndViewIsRestricted(): void
    {
        $form = $this->form(['content' => ['picture' => '_default']], $this->media());
        $form->submit(['media' => 'existing-id', 'version' => 'picture#_default']);
        self::assertTrue($form->isValid());
        self::assertSame(['picture#_default' => '_default'], $form->createView()['media']->vars['media_version_choices']);
    }

    public function testServerRejectsDisallowedMediaType(): void
    {
        $form = $this->form(['content' => ['picture' => '_default']], $this->media(false, 'hero'));
        $form->submit(['media' => 'existing-id', 'version' => 'picture#_default']);
        self::assertFalse($form->isValid());
    }

    public function testAllowedVideoAndVideoSetAreValid(): void
    {
        foreach (['video#_original' => ['video' => '_original'], 'videoSet#cms_preview' => ['video_sets' => ['cms_preview']]] as $value => $config) {
            $form = $this->form(['content' => $config], $this->media(true));
            $form->submit(['media' => 'existing-id', 'version' => $value]);
            self::assertTrue($form->isValid());
        }
    }

    public function testOptionalFieldCanBeCleared(): void
    {
        $form = $this->form(['content' => ['picture' => '_default']], null);
        $form->submit(['media' => '', 'version' => '']);
        self::assertTrue($form->isValid());
    }

    public function testUnrestrictedLegacySelectionStillWorks(): void
    {
        $form = $this->form(null, $this->media());
        $form->submit(['media' => 'existing-id', 'version' => '_original']);
        self::assertTrue($form->isValid());
    }

    public function testUnknownMediaIdIsRejectedRatherThanSilentlyCleared(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('__call')->with('findOneById', ['missing-id'])->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $type = new MediaModalType($em, $this->createMock(RouterInterface::class), new MediaTypesCollection([]));
        $factory = Forms::createFormFactoryBuilder()->addExtension(new PreloadedExtension([$type], []))->getFormFactory();
        $form = $factory->create(MediaModalType::class);
        $form->submit('missing-id');
        self::assertFalse($form->isSynchronized());
        self::assertFalse($form->isValid());
    }

    public function testClearedMediaIsTransformedToNull(): void
    {
        $type = new MediaModalType($this->createMock(EntityManagerInterface::class), $this->createMock(RouterInterface::class), new MediaTypesCollection([]));
        $factory = Forms::createFormFactoryBuilder()->addExtension(new PreloadedExtension([$type], []))->getFormFactory();
        $form = $factory->create(MediaModalType::class);
        $form->submit('');
        self::assertTrue($form->isValid());
        self::assertNull($form->getData());
    }
}
