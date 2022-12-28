<?php

namespace Softspring\MediaBundle\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Render\MediaRenderer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaChoiceType extends AbstractType
{
    protected EntityManagerInterface $em;
    protected MediaRenderer $mediaRenderer;

    public function __construct(EntityManagerInterface $em, MediaRenderer $mediaRenderer)
    {
        $this->em = $em;
        $this->mediaRenderer = $mediaRenderer;
    }

    public function getParent(): ?string
    {
        return EntityType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => MediaInterface::class,
            'em' => $this->em,
            'required' => false,
            'media_types' => [],
            'media_attr' => [],
            'image_attr' => [],
            'video_attr' => [],
            'picture_attr' => [],
            'query_builder' => fn (EntityRepository $entityRepository) => $entityRepository->createQueryBuilder('i'),
            'choice_label' => function (MediaInterface $media) {
                return $media->getName();
            },
            'choice_filter' => function (?MediaInterface $media = null) {
                return true;
            },
        ]);

        $resolver->setDefault('query_builder', function (Options $options) {
            return function (EntityRepository $er) use ($options) {
                return $er->createQueryBuilder('i')
                    ->orderBy('i.id', 'ASC')
                    ->andWhere('i.type IN (:types)')
                    ->setParameter('types', array_keys($options['media_types']));
            };
        });

        $resolver->setDefault('choice_attr', function (Options $options) {
            return function (?MediaInterface $media = null) use ($options) {
                if (empty($options['attr']['data-media-preview-input'])) {
                    return [];
                }

                $mediaTypes = $options['media_types'];
                $mediaType = $mediaTypes[$media->getType()];
                $attrs = [];

                foreach ($mediaType as $mode => $version) {
                    if ('image' == $mode) {
                        $attrs['data-media-preview-image'] = $this->mediaRenderer->renderImage($media, $version, $options['media_attr'] + $options['image_attr']);
                    } elseif ('video' == $mode) {
                        $attrs['data-media-preview-video'] = $this->mediaRenderer->renderVideo($media, $version, $options['media_attr'] + $options['video_attr']);
                    } elseif ('picture' == $mode) {
                        $attrs['data-media-preview-picture'] = $this->mediaRenderer->renderPicture($media, $version, $options['media_attr'] + $options['picture_attr']);
                    } else {
                        throw new \Exception("Bad $mode mode for media_type. Only 'image', 'video' and 'picture' are allowed");
                    }
                }

                return $attrs;
            };
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['image_attr'] = $options['media_attr'] + $options['image_attr'];
        $view->vars['video_attr'] = $options['media_attr'] + $options['video_attr'];
        $view->vars['picture_attr'] = $options['media_attr'] + $options['picture_attr'];
    }
}
