<?php

namespace Softspring\MediaBundle\Form\Admin;

use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaUpdateForm extends AbstractMediaForm implements MediaUpdateFormInterface
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'required_uploads' => false,
        ]);
    }
}
