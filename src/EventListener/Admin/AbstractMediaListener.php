<?php

namespace Softspring\MediaBundle\EventListener\Admin;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Softspring\Component\CrudlController\Event\CreateEntityEvent;
use Softspring\Component\CrudlController\Event\ExceptionEvent;
use Softspring\Component\CrudlController\Event\FailureEvent;
use Softspring\Component\CrudlController\Event\FormPrepareEvent;
use Softspring\Component\CrudlController\Event\SuccessEvent;
use Softspring\Component\Events\ViewEvent;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Request\FlashNotifier;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractMediaListener implements EventSubscriberInterface
{
    public const ACTION_NAME = 'unknown';

    public function __construct(
        protected UrlGeneratorInterface $urlGenerator,
        protected MediaManagerInterface $mediaManager,
        protected MediaTypesCollection $mediaTypesCollection,
        protected FlashNotifier $flashNotifier,
    ) {
    }

    public function onViewAddMediaTypes(ViewEvent $event): void
    {
        $event->getData()['media_types'] = $this->mediaTypesCollection->getTypes(false);
    }

    public function onCreateEntityCreateWithType(CreateEntityEvent $event): void
    {
        $type = $event->getRequest()->attributes->get('type');
        $media = $this->mediaManager->createEntityForType($type);
        $event->setEntity($media);
    }

    public function onCreateFormPrepareAddMediaTypeOption(FormPrepareEvent $event): void
    {
        $type = $event->getRequest()->attributes->get('type');
        $event->setFormOptions($event->getFormOptions() + ['media_type' => $type]);
    }

    /**
     * @throws InvalidTypeException
     */
    public function onViewAddTypeConfigFromRequest(ViewEvent $event): void
    {
        $event->getData()['type_config'] = $this->mediaTypesCollection->getType($event->getRequest()->attributes->get('type'));
    }

    /**
     * @throws InvalidTypeException
     */
    public function onViewAddTypeConfigFromEntity(ViewEvent $event): void
    {
        /** @var MediaInterface $media */
        $media = $event->getData()['media'];
        $event->getData()['type_config'] = $this->mediaTypesCollection->getType($media->getType());
    }

    public function onFormFailureAddError(FailureEvent $event): void
    {
        $event->getForm()->addError(new FormError($event->getException()->getMessage()));
    }

    public function onSuccessShowFlash(SuccessEvent $event): void
    {
        /** @var MediaInterface $media */
        $media = $event->getEntity();

        $this->flashNotifier->addTrans('success', 'admin_medias.'.get_called_class()::ACTION_NAME.'.success_flash', ['%name%' => $media->getName()], 'sfs_media_admin');
    }

    public function onSuccessRedirectToMediaRead(SuccessEvent $event): void
    {
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('sfs_media_admin_medias_read', ['media' => $event->getEntity()])));
    }

    public function onFailureShowFlash(FailureEvent $event): void
    {
        /** @var MediaInterface $media */
        $media = $event->getEntity();

        $params = $this->checkException($event, get_called_class()::ACTION_NAME);

        $this->flashNotifier->addTrans('error', $params['reason'], [
            '%name%' => $media->getName(),
            '%exception%' => $params['exception'],
            '%previous%' => $params['previous'],
        ], 'sfs_media_admin');
    }

    public function onFailureRedirectToMediaRead(FailureEvent $event): void
    {
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('sfs_media_admin_medias_read', ['media' => $event->getEntity()])));
    }

    public function onExceptionShowFlash(ExceptionEvent $event): void
    {
        $params = $this->checkException($event, get_called_class()::ACTION_NAME);

        $this->flashNotifier->addTrans('error', $params['reason'], [
            '%exception%' => $params['exception'],
            '%previous%' => $params['previous'],
        ], 'sfs_media_admin');
    }

    public function onExceptionRedirectToMediaRead(ExceptionEvent $event): void
    {
        $mediaId = $event->getRequest()->attributes->get('media');
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('sfs_media_admin_medias_read', ['media' => $mediaId])));
    }

    private function checkException(mixed $event, string $action = 'default'): array
    {
        $exception = $event->getException();

        $transParams = [
            'exception' => $exception->getMessage(),
            'previous' => $exception->getPrevious()?->getMessage(),
        ];

        $transParams['reason'] = match (true) {
            $exception instanceof ForeignKeyConstraintViolationException => 'admin_medias.' . $action . '.error_reason_flash.foreign_key',
            $exception instanceof UniqueConstraintViolationException => 'admin_medias.' . $action . '.error_reason_flash.unique_constraint',
            $exception instanceof NotNullConstraintViolationException => 'admin_medias.' . $action . '.error_reason_flash.not_null',
            default => 'admin_medias.' . $action . '.error_reason_flash.default',
        };

        return $transParams;
    }
}
