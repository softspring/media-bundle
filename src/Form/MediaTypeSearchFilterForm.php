<?php

namespace Softspring\MediaBundle\Form;

use Softspring\Component\CrudlController\Form\EntityListFilterForm;
use Softspring\Component\CrudlController\Form\FormOptionsInterface;
use Softspring\MediaBundle\Manager\MediaTypeManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class MediaTypeSearchFilterForm extends EntityListFilterForm implements FormOptionsInterface
{
    protected MediaTypeManagerInterface $typeManager;
    protected RouterInterface $router;

    public function __construct(MediaTypeManagerInterface $typeManager, RouterInterface $router)
    {
        $this->typeManager = $typeManager;
        $this->router = $router;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'valid_types' => null,
        ]);
        $resolver->setAllowedTypes('valid_types', ['array', 'null']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('text', TextType::class, [
            'property_path' => '[name_like]',
        ]);

        $validTypes = $options['valid_types'];
        $filteredTypes = array_intersect_key($this->typeManager->getTypes(), array_flip($validTypes));

        if (count($filteredTypes) > 1) {
            $builder->add('type', ChoiceType::class, [
                'choices' => array_flip(array_map(fn ($v) => $v['name'], $filteredTypes)),
                'multiple' => true,
                'property_path' => '[type_in]',
            ]);
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['valid_types'] = $options['valid_types'];
    }

    public function formOptions($object, Request $request): array
    {
        return [
            'valid_types' => explode(',', $request->attributes->get('valid_types')),
            'action' => $this->router->generate('sfs_media_admin_medias_search_type', ['valid_types' => $request->attributes->get('valid_types')]),
        ];
    }
}
