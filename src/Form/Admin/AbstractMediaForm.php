<?php

namespace Softspring\MediaBundle\Form\Admin;

use Softspring\MediaBundle\Form\MediaTypeUploadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractMediaForm extends AbstractType
{
    public function getParent(): string
    {
        return MediaTypeUploadType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'sfs_media_admin',
            'label_format' => 'admin_medias.form.%name%.label',
        ]);
    }
}
