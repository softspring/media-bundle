<?php

namespace Softspring\MediaBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaVersionModalType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'media_version_modal';
    }

    public function configureOptions(OptionsResolver $resolver)
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

    public function buildForm(FormBuilderInterface $builder, array $options)
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
    }
}
