<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Form;

use Softspring\MediaBundle\Helper\MediaSelectionChoices;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaVersionModalType extends AbstractType
{
    public function __construct(private readonly MediaTypesCollection $mediaTypesCollection)
    {
    }

    public function getBlockPrefix(): string
    {
        return 'media_version_modal';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'media_types' => null,
            'media_attr' => [],
            'image_attr' => [],
            'video_attr' => [],
            'picture_attr' => [],
            'show_thumbnail' => false,
        ]);

        $resolver->setAllowedTypes('media_types', ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('media', MediaModalType::class, [
            'required' => $options['required'],
            'media_types' => $options['media_types'],
            'media_attr' => $options['media_attr'],
            'image_attr' => $options['image_attr'],
            'video_attr' => $options['video_attr'],
            'picture_attr' => $options['picture_attr'],
            'show_thumbnail' => $options['show_thumbnail'],
            'block_prefix' => 'media_modal_hidden',
        ]);

        $builder->add('version', HiddenType::class);
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();
            if (!$form->get('media')->isSynchronized() || !$form->get('version')->isSynchronized()) {
                return;
            }
            $media = $form->get('media')->getData();
            if (!$media instanceof MediaInterface || null === $options['media_types']) {
                return;
            }
            $choices = MediaSelectionChoices::forMedia($media, $options['media_types'], $this->mediaTypesCollection->getTypes()[$media->getType()] ?? []);
            if (!array_key_exists($form->get('version')->getData() ?? '', $choices)) {
                $form->addError(new FormError('Select an allowed media version, picture or video set.'));
            }
        });
    }
}
