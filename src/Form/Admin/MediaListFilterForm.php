<?php

namespace Softspring\MediaBundle\Form\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Softspring\CmsBundle\Entity\ContentVersion;
use Softspring\CmsBundle\Model\ContentInterface;
use Softspring\Component\DoctrinePaginator\Form\PaginatorForm;
use Softspring\Component\DoctrinePaginator\Form\QueryBuilderProcessorInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaListFilterForm extends PaginatorForm implements MediaListFilterFormInterface, QueryBuilderProcessorInterface
{
    protected MediaTypesCollection $mediaTypesCollection;

    public function __construct(EntityManagerInterface $em, MediaTypesCollection $mediaTypesCollection)
    {
        parent::__construct($em);
        $this->mediaTypesCollection = $mediaTypesCollection;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'translation_domain' => 'sfs_media_admin',
            'label_format' => 'admin_medias.list.filter_form.%name%.label',
            'class' => MediaInterface::class,
            'rpp_valid_values' => [20],
            'rpp_default_value' => 20,
            'order_valid_fields' => ['name', 'createdAt'],
            'order_default_value' => 'createdAt',
            'order_direction_default_value' => 'desc',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        //        $builder->add('name', TextType::class, [
        //            'property_path' => '[name__like]',
        //        ]);
        //
        //        $builder->add('description', TextType::class, [
        //            'property_path' => '[description__like]',
        //        ]);

        $builder->add('text', TextType::class, [
            'property_path' => '[name__like___or___description__like]',
        ]);

        $builder->add('type', ChoiceType::class, [
            'required' => false,
            'choice_translation_domain' => false,
            'choices' => array_flip(array_map(fn ($v) => $v['name'], $this->mediaTypesCollection->getTypes())),
            'multiple' => true,
            'property_path' => '[type__in]',
            'expanded' => true,
        ]);

        $builder->remove($options['order_field_name']);
        $builder->add($options['order_field_name'], ChoiceType::class, [
            'mapped' => false,
            'choices' => array_combine($options['order_valid_fields'], $options['order_valid_fields']),
            'default_value' => $options['order_default_value'],
        ]);

        $builder->add($options['order_direction_field_name'], ChoiceType::class, [
            'mapped' => false,
            'choices' => array_combine($options['order_direction_valid_fields'], $options['order_direction_valid_fields']),
            'default_value' => $options['order_direction_default_value'],
        ]);

        $builder->add('content', EntityType::class, [
            'em' => $this->em,
            'class' => ContentInterface::class,
            'required' => false,
            'choice_label' => 'name',
            'property_path' => '[content__in]',
            'expanded' => true,
            'multiple' => true,
        ]);
    }

    public function preProcessQueryBuilder(QueryBuilder $qb, array &$filters, array &$orderSort, int &$filtersMode): QueryBuilder
    {
        if (empty($filters['content__in'])) {
            return $qb;
        }

        $contentIds = array_map(function (ContentInterface $content) {
            return $content->getId();
        }, $filters['content__in']->toArray());

        if (!empty($contentIds)) {
            $query = $qb->getEntityManager()->createQuery('SELECT cvm FROM '.ContentVersion::class.' cv LEFT JOIN cv.medias cvm WHERE cv.content IN (:contentIds)')->getDQL();
            $qb->where($qb->expr()->in(
                'm',
                $query
            ))
                ->setParameter('contentIds', $contentIds)
            ;
        }

        unset($filters['content__in']);

        return $qb;
    }
}
