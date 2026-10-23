<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Admin;

use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Softspring\MediaBundle\Admin\RegenerateMediaController;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Request\FlashNotifier;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class RegenerateMediaControllerTest extends TestCase
{
    private function media(): Media
    {
        $media = new Media();
        $media->setType('content');
        $original = new MediaVersion('_original', $media);
        $original->setUrl('gs://test/original.png');

        return $media;
    }

    private function controller(?Media $media, MediaManagerInterface&MockObject $manager, bool $granted = true, bool $csrfValid = true, ?Environment $twig = null, ?FlashNotifier $flash = null): RegenerateMediaController
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($media);
        $manager->method('getRepository')->willReturn($repository);
        $types = $this->createMock(MediaTypesCollection::class);
        $types->method('getType')->willReturn(['versions' => ['small' => ['from' => '_original'], 'manual' => ['upload_requirements' => []]]]);
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn($granted);
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(static function (CsrfToken $token) use ($csrfValid): bool {
            self::assertSame('sfs_media_regenerate_source-id', $token->getId());

            return $csrfValid;
        });
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->with('sfs_media_admin_medias_read', ['media' => 'source-id'])->willReturn('/source');

        return new RegenerateMediaController($manager, $types, $authorization, $csrf, $urls, $flash ?? $this->createMock(FlashNotifier::class), $this->createMock(LoggerInterface::class), $twig ?? $this->createMock(Environment::class));
    }

    public function testGetOnlyShowsConfirmationAndGeneratedVersions(): void
    {
        $media = $this->media();
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->expects(self::never())->method('migrate');
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->with('@SfsMedia/admin/media/regenerate.html.twig', ['media' => $media, 'versions' => ['small']])->willReturn('Confirmation');
        $response = ($this->controller($media, $manager, twig: $twig))(Request::create('/source-id/regenerate'), 'source-id');
        self::assertSame('Confirmation', $response->getContent());
    }

    public function testPostRegeneratesAndRestoresMigrationFlag(): void
    {
        $media = $this->media();
        $states = [];
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->method('isMigrating')->willReturn(false);
        $manager->expects(self::exactly(2))->method('setMigrating')->willReturnCallback(static function (bool $state) use (&$states): void { $states[] = $state; });
        $manager->expects(self::once())->method('migrate')->with($media, null, true);
        $flash = $this->createMock(FlashNotifier::class);
        $flash->expects(self::once())->method('addTrans')->with('success', 'admin_medias.regenerate.success_flash', [], 'sfs_media_admin');
        $response = ($this->controller($media, $manager, flash: $flash))(Request::create('/source-id/regenerate', 'POST', ['_token' => 'valid']), 'source-id');
        self::assertSame([true, false], $states);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/source', $response->headers->get('Location'));
    }

    public function testInvalidCsrfDoesNotProcessMedia(): void
    {
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->expects(self::never())->method('migrate');
        $this->expectException(AccessDeniedException::class);
        ($this->controller($this->media(), $manager, csrfValid: false))(Request::create('/source-id/regenerate', 'POST'), 'source-id');
    }

    public function testPermissionIsRequired(): void
    {
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->expects(self::never())->method('migrate');
        $this->expectException(AccessDeniedException::class);
        ($this->controller($this->media(), $manager, granted: false))(Request::create('/source-id/regenerate', 'POST'), 'source-id');
    }

    public function testMissingMediaIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        ($this->controller(null, $this->createMock(MediaManagerInterface::class)))(Request::create('/source-id/regenerate'), 'source-id');
    }

    public function testMissingOriginalIsRejected(): void
    {
        $media = $this->media();
        $media->getVersion('_original')->setUrl(null);
        $this->expectException(BadRequestHttpException::class);
        ($this->controller($media, $this->createMock(MediaManagerInterface::class)))(Request::create('/source-id/regenerate', 'POST'), 'source-id');
    }

    public function testFailureShowsErrorAndRestoresMigrationFlag(): void
    {
        $states = [];
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->method('isMigrating')->willReturn(false);
        $manager->method('setMigrating')->willReturnCallback(static function (bool $state) use (&$states): void { $states[] = $state; });
        $manager->method('migrate')->willThrowException(new RuntimeException('Failed encoder'));
        $flash = $this->createMock(FlashNotifier::class);
        $flash->expects(self::once())->method('addTrans')->with('error', 'admin_medias.regenerate.error_flash', [], 'sfs_media_admin');
        $response = ($this->controller($this->media(), $manager, flash: $flash))(Request::create('/source-id/regenerate', 'POST', ['_token' => 'valid']), 'source-id');
        self::assertSame([true, false], $states);
        self::assertSame(303, $response->getStatusCode());
    }
}
