<?php

namespace Softspring\MediaBundle\Form;

use Doctrine\ORM\EntityManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
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
    protected MediaTypesCollection $mediaTypesCollection;

    public function __construct(EntityManagerInterface $em, RouterInterface $router, MediaTypesCollection $mediaTypesCollection)
    {
        $this->em = $em;
        $this->router = $router;
        $this->mediaTypesCollection = $mediaTypesCollection;
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
        $builder->addModelTransformer(new CallbackTransformer(function ($value) {
            return $value;
        }, function ($value) {
            return is_string($value) ? $this->em->getRepository(MediaInterface::class)->findOneById($value) : $value;
        }));
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (null === $options['media_types']) {
            foreach ($this->mediaTypesCollection->getTypes() as $type => $typeConfig) {
                if ('image' == $typeConfig['type']) {
                    $options['media_types'][$type]['image'] = array_keys($typeConfig['versions']);
                } elseif ('video' == $typeConfig['type']) {
                    $options['media_types'][$type]['video'] = array_keys($typeConfig['versions']);
                }
                if (!empty($typeConfig['pictures'])) {
                    $options['media_types'][$type]['picture'] = array_keys($typeConfig['pictures']);
                }
            }
        } else {
            foreach ($options['media_types'] as $type => $typeConfig) {
                foreach ($typeConfig as $t => $v) {
                    $options['media_types'][$type][$t] = is_string($v) ? [$v] : $v;
                }
            }
        }

        $view->vars['media_types'] = $options['media_types'];
        $view->vars['show_thumbnail'] = $options['show_thumbnail'];
        $view->vars['image_attr'] = $options['media_attr'] + $options['image_attr'];
        $view->vars['video_attr'] = $options['media_attr'] + $options['video_attr'];
        $view->vars['picture_attr'] = $options['media_attr'] + $options['picture_attr'];
        $view->vars['attr']['data-media-type-config'] = json_encode($options['media_types']);
        $view->vars['attr']['data-media-type-types'] = implode(',', array_keys($options['media_types']));
        $view->vars['modal_search_url'] = $this->router->generate('sfs_media_admin_medias_search_type', [
            'valid_types' => implode(',', array_keys($options['media_types'])),
        ]);
    }
}
