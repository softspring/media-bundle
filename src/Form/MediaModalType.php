<?php

namespace Softspring\MediaBundle\Form;

use Doctrine\ORM\EntityManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class MediaModalType extends AbstractType
{
    protected EntityManagerInterface $em;
    protected RouterInterface $router;

    public function __construct(EntityManagerInterface $em, RouterInterface $router)
    {
        $this->em = $em;
        $this->router = $router;
    }

    public function getBlockPrefix(): string
    {
        return 'media_modal';
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => MediaInterface::class,
            'required' => false,
            'media_types' => [],
            'media_attr' => [],
            'show_thumbnail' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CallbackTransformer(function ($value) {
            return $value;
        }, function ($value) {
            return is_string($value) ? $this->em->getRepository(MediaInterface::class)->findOneById($value) : $value;
        }));
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['show_thumbnail'] = $options['show_thumbnail'];
        $view->vars['media_attr'] = $options['media_attr'];
        $view->vars['attr']['data-media-type-config'] = json_encode($options['media_types']);
        $view->vars['attr']['data-media-type-types'] = implode(',', array_keys($options['media_types']));
        $view->vars['modal_search_url'] = $this->router->generate('sfs_media_admin_medias_search_type', [
            'valid_types' => implode(',', array_keys($options['media_types'])),
        ]);
    }
}
