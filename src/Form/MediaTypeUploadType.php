<?php

namespace Softspring\MediaBundle\Form;

use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\EntityManager\MediaTypeManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\SubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaTypeUploadType extends AbstractType
{
    protected MediaTypeManagerInterface $mediaTypeManager;

    protected MediaManagerInterface $mediaManager;

    public function __construct(MediaTypeManagerInterface $mediaTypeManager, MediaManagerInterface $mediaManager)
    {
        $this->mediaTypeManager = $mediaTypeManager;
        $this->mediaManager = $mediaManager;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->mediaManager->getEntityClass(),
            'media_type' => null,
        ]);

        $resolver->setRequired('media_type');
        $resolver->setAllowedTypes('media_type', 'string');
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $typeDefinition = $this->mediaTypeManager->getType($options['media_type']);

        $builder->add('name');
        $builder->add('description');

        $builder->add('_original', MediaVersionUploadType::class, [
            'property_path' => 'versions[_original]',
            'upload_requirements' => $typeDefinition['upload_requirements'],
        ]);

        foreach ($typeDefinition['versions'] as $key => $config) {
            if (!isset($config['upload_requirements'])) {
                continue;
            }
            $builder->add($key, MediaVersionUploadType::class, [
                'property_path' => "versions[$key]",
                'upload_requirements' => $config['upload_requirements'],
            ]);
        }

        $builder->addEventListener(FormEvents::SUBMIT, function (SubmitEvent $event) use ($options) {
            /** @var ?MediaInterface $media */
            $media = $event->getData();
            $form = $event->getForm();

            if (!$media) {
                return;
            }

            $media->setType($options['media_type']);
        });
    }
}
