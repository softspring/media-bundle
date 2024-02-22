<?php

namespace Softspring\MediaBundle\Form;

use Softspring\MediaBundle\EntityManager\MediaVersionManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class MediaVersionUploadType extends AbstractType
{
    protected MediaVersionManagerInterface $mediaVersionManager;

    public function __construct(MediaVersionManagerInterface $mediaVersionManager)
    {
        $this->mediaVersionManager = $mediaVersionManager;
    }

    public function getBlockPrefix(): string
    {
        return 'media_version_upload';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->mediaVersionManager->getEntityClass(),
            'upload_requirements' => null,
            'required_upload' => true,
        ]);

        $resolver->setAllowedTypes('upload_requirements', 'array');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('upload', FileType::class, [
            'required' => $options['required_upload'],
            'constraints' => new Image($options['upload_requirements']),
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['upload_requirements'] = $options['upload_requirements'];
    }
}
