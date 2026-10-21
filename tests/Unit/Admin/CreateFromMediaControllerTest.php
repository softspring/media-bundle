<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Admin;

use Doctrine\ORM\EntityRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Softspring\MediaBundle\Admin\CreateFromMediaController;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Media\CreateFromMedia;
use Softspring\MediaBundle\Request\FlashNotifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CreateFromMediaControllerTest extends TestCase
{
    private function controller(?Media $source, CreateFromMedia $creator, bool $createPermission = true, bool $readPermission = true, bool $validCsrf = true, ?FlashNotifier $flash = null, ?LoggerInterface $logger = null): CreateFromMediaController
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with('source-id')->willReturn($source);
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturnCallback(static fn (mixed $permission): bool => 'PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_CREATE' === $permission ? $createPermission : $readPermission);
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(static function (CsrfToken $token) use ($validCsrf): bool {
            self::assertSame('sfs_media_create_from_source-id', $token->getId());
            self::assertSame('csrf-value', $token->getValue());

            return $validCsrf;
        });
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => $parameters['media'] instanceof Media ? '/created' : '/source');

        return new CreateFromMediaController($manager, $creator, $authorization, $csrf, $urls, $flash ?? $this->createMock(FlashNotifier::class), $logger ?? $this->createMock(LoggerInterface::class));
    }

    private function request(): Request
    {
        return Request::create('/source-id/create-from', 'POST', ['type' => 'target', '_token' => 'csrf-value']);
    }

    public function testRedirectsToNewMediaAfterCreation(): void
    {
        $source = new Media();
        $created = new Media();
        $created->setName('Copied name');
        $creator = $this->createMock(CreateFromMedia::class);
        $creator->expects(self::once())->method('create')->with($source, 'target')->willReturn($created);
        $flash = $this->createMock(FlashNotifier::class);
        $flash->expects(self::once())->method('addTrans')->with('success', 'admin_medias.create_from.success_flash', ['%name%' => 'Copied name'], 'sfs_media_admin');
        $response = ($this->controller($source, $creator, flash: $flash))($this->request(), 'source-id');
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/created', $response->getTargetUrl());
    }

    public function testCreationPermissionIsRequired(): void
    {
        $creator = $this->createMock(CreateFromMedia::class);
        $creator->expects(self::never())->method('create');
        $this->expectException(AccessDeniedException::class);
        ($this->controller(new Media(), $creator, createPermission: false))($this->request(), 'source-id');
    }

    public function testSourceReadPermissionIsRequired(): void
    {
        $creator = $this->createMock(CreateFromMedia::class);
        $creator->expects(self::never())->method('create');
        $this->expectException(AccessDeniedException::class);
        ($this->controller(new Media(), $creator, readPermission: false))($this->request(), 'source-id');
    }

    public function testCsrfIsRequired(): void
    {
        $creator = $this->createMock(CreateFromMedia::class);
        $creator->expects(self::never())->method('create');
        $this->expectException(AccessDeniedException::class);
        ($this->controller(new Media(), $creator, validCsrf: false))($this->request(), 'source-id');
    }

    public function testMissingSourceIsNotFound(): void
    {
        $creator = $this->createMock(CreateFromMedia::class);
        $creator->expects(self::never())->method('create');
        $this->expectException(NotFoundHttpException::class);
        ($this->controller(null, $creator))($this->request(), 'source-id');
    }

    public function testIncompatibleDestinationIsRejected(): void
    {
        $creator = $this->createMock(CreateFromMedia::class);
        $creator->method('create')->willThrowException(new InvalidArgumentException('Invalid destination'));
        $this->expectException(BadRequestHttpException::class);
        ($this->controller(new Media(), $creator))($this->request(), 'source-id');
    }

    public function testProcessingFailureLogsAndRedirectsToSource(): void
    {
        $creator = $this->createMock(CreateFromMedia::class);
        $creator->method('create')->willThrowException(new RuntimeException('Encoder failure'));
        $flash = $this->createMock(FlashNotifier::class);
        $flash->expects(self::once())->method('addTrans')->with('error', 'admin_medias.create_from.error_flash', [], 'sfs_media_admin');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $response = ($this->controller(new Media(), $creator, flash: $flash, logger: $logger))($this->request(), 'source-id');
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/source', $response->getTargetUrl());
    }
}
