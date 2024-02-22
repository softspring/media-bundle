<?php

namespace Softspring\MediaBundle\Form;

use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\SubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaTypeUploadType extends AbstractType
{
    protected MediaTypesCollection $mediaTypesCollection;

    protected MediaManagerInterface $mediaManager;

    public function __construct(MediaTypesCollection $mediaTypesCollection, MediaManagerInterface $mediaManager)
    {
        $this->mediaTypesCollection = $mediaTypesCollection;
        $this->mediaManager = $mediaManager;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->mediaManager->getEntityClass(),
            'media_type' => null,
            'required_uploads' => true,
        ]);

        $resolver->setRequired('media_type');
        $resolver->setAllowedTypes('media_type', 'string');
    }

    /**
     * @throws InvalidTypeException
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeDefinition = $this->mediaTypesCollection->getType($options['media_type']);

        $builder->add('name');
        $builder->add('description');

        $builder->add('_original', MediaVersionUploadType::class, [
            'property_path' => 'version__original',
            'upload_requirements' => $typeDefinition['upload_requirements'],
            'required_upload' => $options['required_uploads'],
        ]);

        foreach ($typeDefinition['versions'] as $key => $config) {
            if (!isset($config['upload_requirements'])) {
                continue;
            }
            $builder->add($key, MediaVersionUploadType::class, [
                'property_path' => "version_$key)",
                'upload_requirements' => $config['upload_requirements'],
                'required_upload' => $options['required_uploads'],
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
