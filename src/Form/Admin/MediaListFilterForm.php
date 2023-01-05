<?php

namespace Softspring\MediaBundle\Form\Admin;

use Softspring\Component\DoctrinePaginator\Form\PaginatorForm;
use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaListFilterForm extends PaginatorForm implements MediaListFilterFormInterface
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'translation_domain' => 'sfs_media_admin',
            'label_format' => 'admin_medias.list.filter_form.%name%.label',
            'class' => MediaInterface::class,
            'rpp_valid_values' => [50],
            'rpp_default_value' => 50,
            'order_valid_fields' => ['name', 'createdAt'],
            'order_default_value' => 'createdAt',
            'order_direction_default_value' => 'desc',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('name', TextType::class, [
            'property_path' => '[name__like]',
        ]);
    }
}
