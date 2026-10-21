<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Admin;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Media\CreateFromMedia;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Request\FlashNotifier;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;

class CreateFromMediaController
{
    public function __construct(
        private MediaManagerInterface $manager,
        private CreateFromMedia $creator,
        private AuthorizationCheckerInterface $authorization,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $urls,
        private FlashNotifier $flash,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, string $media): RedirectResponse
    {
        if (!$this->authorization->isGranted('PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_CREATE')) {
            throw new AccessDeniedException();
        }
        $source = $this->manager->getRepository()->find($media);
        if (!$source instanceof MediaInterface) {
            throw new NotFoundHttpException();
        }
        if (!$this->authorization->isGranted('PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_DETAILS', $source)
            || !$this->csrf->isTokenValid(new CsrfToken('sfs_media_create_from_'.$media, $request->request->getString('_token')))) {
            throw new AccessDeniedException();
        }

        try {
            $created = $this->creator->create($source, $request->request->getString('type'));
        } catch (InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        } catch (Throwable $exception) {
            $this->logger->error('Cannot create media from original.', ['exception' => $exception, 'media' => $media]);
            $this->flash->addTrans('error', 'admin_medias.create_from.error_flash', [], 'sfs_media_admin');

            return new RedirectResponse($this->urls->generate('sfs_media_admin_medias_read', ['media' => $media]), 303);
        }
        $this->flash->addTrans('success', 'admin_medias.create_from.success_flash', ['%name%' => $created->getName()], 'sfs_media_admin');

        return new RedirectResponse($this->urls->generate('sfs_media_admin_medias_read', ['media' => $created]), 303);
    }
}
