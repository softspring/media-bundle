<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Admin;

use Psr\Log\LoggerInterface;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Request\FlashNotifier;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;
use Twig\Environment;

class RegenerateMediaController
{
    public function __construct(
        private MediaManagerInterface $manager,
        private MediaTypesCollection $types,
        private AuthorizationCheckerInterface $authorization,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $urls,
        private FlashNotifier $flash,
        private LoggerInterface $logger,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request, string $media): Response
    {
        $entity = $this->manager->getRepository()->find($media);
        if (!$entity instanceof MediaInterface) {
            throw new NotFoundHttpException();
        }
        if (!$this->authorization->isGranted('PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_MIGRATE', $entity)
            || !$this->authorization->isGranted('PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_DETAILS', $entity)) {
            throw new AccessDeniedException();
        }
        $config = $this->types->getType($entity->getType());
        $versions = array_keys(array_filter($config['versions'], static fn (array $options, string $key): bool => '_original' !== $key && !isset($options['upload_requirements']), ARRAY_FILTER_USE_BOTH));
        if (!$versions || !$entity->getVersion('_original')?->getUrl()) {
            throw new BadRequestHttpException('This media has no generated versions or no original file.');
        }

        if ($request->isMethod('GET')) {
            return new Response($this->twig->render('@SfsMedia/admin/media/regenerate.html.twig', ['media' => $entity, 'versions' => $versions]));
        }
        if (!$this->csrf->isTokenValid(new CsrfToken('sfs_media_regenerate_'.$media, $request->request->getString('_token')))) {
            throw new AccessDeniedException();
        }

        $wasMigrating = $this->manager->isMigrating();
        try {
            $this->manager->setMigrating(true);
            $this->manager->migrate($entity, null, true);
            $this->flash->addTrans('success', 'admin_medias.regenerate.success_flash', [], 'sfs_media_admin');
        } catch (Throwable $exception) {
            $this->logger->error('Cannot regenerate media versions.', ['exception' => $exception, 'media' => $media]);
            $this->flash->addTrans('error', 'admin_medias.regenerate.error_flash', [], 'sfs_media_admin');
        } finally {
            $this->manager->setMigrating($wasMigrating);
        }

        return new RedirectResponse($this->urls->generate('sfs_media_admin_medias_read', ['media' => $media]), 303);
    }
}
