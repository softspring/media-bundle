<?php

namespace Softspring\MediaBundle\Form\Admin;

use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaDeleteForm extends AbstractType implements MediaDeleteFormInterface
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MediaInterface::class,
            'translation_domain' => 'sfs_media_admin',
            'label_format' => 'admin_medias.delete.form.%name%.label',
        ]);
    }
}
