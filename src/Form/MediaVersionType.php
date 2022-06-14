<?php

namespace Softspring\MediaBundle\Form;

use Softspring\MediaBundle\Manager\MediaVersionManagerInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\SubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class MediaVersionType extends AbstractType
{
    protected MediaVersionManagerInterface $mediaVersionManager;

    public function __construct(MediaVersionManagerInterface $mediaVersionManager)
    {
        $this->mediaVersionManager = $mediaVersionManager;
    }

    public function getBlockPrefix(): string
    {
        return 'media_version';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->mediaVersionManager->getEntityClass(),
            'upload_requirements' => null,
        ]);

        $resolver->setAllowedTypes('upload_requirements', 'array');
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('upload', FileType::class, [
            'required' => true,
            'constraints' => new Image($options['upload_requirements']),
        ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (SubmitEvent $event) {
            /** @var MediaVersionInterface $mediaVersion */
            $mediaVersion = $event->getData();
            $form = $event->getForm();

            if (!$mediaVersion) {
                return;
            }

            $mediaVersion->setVersion('_original');
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['upload_requirements'] = $options['upload_requirements'];
    }
}
