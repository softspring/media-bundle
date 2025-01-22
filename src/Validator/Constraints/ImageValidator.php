<?php

namespace Softspring\MediaBundle\Validator\Constraints;

use Softspring\MediaBundle\Tools\Apng;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ImageValidator as BaseImageValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ImageValidator extends BaseImageValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Image) {
            throw new UnexpectedTypeException($constraint, Image::class);
        }

        parent::validate($value, $constraint);

        if (!$value instanceof UploadedFile) {
            return;
        }

        $this->removeInappropriateApngViolation($value, $constraint);
    }

    protected function removeInappropriateApngViolation(UploadedFile $uploadedFile, Image $constraint): void
    {
        // if invalid mime type, it is an apng image and supported mimetypes has image/apng, remove violation.
        $mimeTypes = (array) $constraint->mimeTypes;
        foreach ($this->context->getViolations() as $v => $violation) {
            if (str_starts_with($violation->getMessage(), substr($constraint->mimeTypesMessage, 0, strpos($constraint->mimeTypesMessage, '({{')))) {
                $mime = $uploadedFile->getMimeType();
                if ('image/png' === $mime && Apng::is($uploadedFile->getRealPath()) && in_array('image/apng', $mimeTypes)) {
                    $this->context->getViolations()->remove($v);
                }
            }
        }
    }
}
