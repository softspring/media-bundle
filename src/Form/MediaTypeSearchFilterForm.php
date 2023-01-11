<?php

namespace Softspring\MediaBundle\Form;

use Doctrine\ORM\EntityManagerInterface;
use Softspring\Component\DoctrinePaginator\Form\PaginatorForm;
use Softspring\MediaBundle\EntityManager\MediaTypeManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class MediaTypeSearchFilterForm extends PaginatorForm
{
    protected MediaTypeManagerInterface $typeManager;
    protected RouterInterface $router;
    protected RequestStack $requestStack;

    public function __construct(MediaTypeManagerInterface $typeManager, RouterInterface $router, RequestStack $requestStack, EntityManagerInterface $em)
    {
        parent::__construct($em);
        $this->typeManager = $typeManager;
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'class' => MediaInterface::class,
            'valid_types' => null,
        ]);
        $resolver->setAllowedTypes('valid_types', ['array', 'null']);

        $resolver->setNormalizer('valid_types', function () {
            return explode(',', $this->requestStack->getCurrentRequest()->attributes->get('valid_types'));
        });

        $resolver->setNormalizer('action', function () {
            return $this->router->generate('sfs_media_admin_medias_search_type', ['valid_types' => $this->requestStack->getCurrentRequest()->attributes->get('valid_types')]);
        });
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('text', TextType::class, [
            'property_path' => '[name__like]',
        ]);

        $validTypes = $options['valid_types'];
        $filteredTypes = array_intersect_key($this->typeManager->getTypes(), array_flip($validTypes));

        if (count($filteredTypes) > 1) {
            $builder->add('type', ChoiceType::class, [
                'choices' => array_flip(array_map(fn ($v) => $v['name'], $filteredTypes)),
                'multiple' => true,
                'property_path' => '[type__in]',
            ]);
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['valid_types'] = $options['valid_types'];
    }
}
