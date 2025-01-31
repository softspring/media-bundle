<?php

namespace Softspring\MediaBundle\Form\Admin;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Softspring\CmsBundle\Entity\ContentVersion;
use Softspring\CmsBundle\Model\ContentInterface;
use Softspring\Component\DoctrinePaginator\Form\PaginatorForm;
use Softspring\Component\DoctrinePaginator\Form\QueryBuilderProcessorInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
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
            'choice_label' => fn ($label) => "admin_medias.list.filter_form.order_field.$label",
            'default_value' => $options['order_default_value'],
        ]);

        $builder->add($options['order_direction_field_name'], ChoiceType::class, [
            'mapped' => false,
            'choices' => array_combine($options['order_direction_valid_fields'], $options['order_direction_valid_fields']),
            'choice_label' => fn ($label) => "admin_medias.list.filter_form.ordir_field.$label",
            'default_value' => $options['order_direction_default_value'],
        ]);

        if (interface_exists(ContentInterface::class)) {
            $builder->add('no_content_entity', CheckboxType::class, [
                'required' => false,
                'property_path' => '[content__empty]',
            ]);

            $builder->add('content_entity', EntityType::class, [
                'em' => $this->em,
                'class' => ContentInterface::class,
                'required' => false,
                'choice_label' => 'name',
                'property_path' => '[content__in]',
                'expanded' => false,
                'multiple' => false,
            ]);
        }

        $builder->add('duplicates', HiddenType::class, [
            'property_path' => '[duplicates]',
        ]);

        $builder->get('duplicates')->addModelTransformer(new CallbackTransformer(
            function ($value) {
                return $value;
            },
            function (?string $value): ?MediaInterface {
                return $value ? $this->em->getRepository(MediaInterface::class)->findOneById($value) : null;
            }
        ));
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $mediaId = $view->children['duplicates']->vars['value'];
        $view->children['duplicates']->vars['media'] = $mediaId ? $this->em->getRepository(MediaInterface::class)->findOneById($mediaId) : null;
    }

    public function preProcessQueryBuilder(QueryBuilder $qb, array &$filters, array &$orderSort, int &$filtersMode): QueryBuilder
    {
        if (!empty($filters['content__in'])) {
            $contentIds = array_map(function (ContentInterface $content) {
                return $content->getId();
            }, $filters['content__in'] instanceof Collection ? $filters['content__in']->toArray() : [$filters['content__in']]);

            if (!empty($contentIds)) {
                $query = $qb->getEntityManager()->createQuery('SELECT cvm_in FROM '.ContentVersion::class.' cv_in LEFT JOIN cv_in.medias cvm_in WHERE cv_in.content IN (:contentIds)')->getDQL();
                $qb->andWhere($qb->expr()->in('m', $query))
                    ->setParameter('contentIds', $contentIds);
            }

            unset($filters['content__in']);
        }

        if (!empty($filters['content__empty'])) {
            $query = $qb->getEntityManager()->createQuery('SELECT DISTINCT cvm_not FROM '.ContentVersion::class.' cv_not LEFT JOIN cv_not.medias cvm_not WHERE cvm_not IS NOT NULL')->getDQL();
            $qb->andWhere($qb->expr()->notIn('m', $query));

            unset($filters['content__empty']);
        }

        if (!empty($filters['duplicates'])) {
            $sha1 = $filters['duplicates']->getVersion('_original')->getSha1();
            $type = $filters['duplicates']->getType();

            $query = $qb->getEntityManager()->createQuery('SELECT media FROM '.MediaVersionInterface::class.' mv LEFT JOIN mv.media media  WHERE mv.version = :version AND mv.sha1 = :sha1')->getDQL();
            $qb->andWhere($qb->expr()->in('m', $query))
                ->andWhere('m.type = :media_type')
                ->setParameter('version', '_original')
                ->setParameter('sha1', $sha1)
                ->setParameter('media_type', $type);

            unset($filters['duplicates']);
        }

        return $qb;
    }
}
