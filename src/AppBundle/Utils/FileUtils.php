<?php


namespace AppBundle\Utils;


use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\File;

class FileUtils
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function isImage(File $file){
        $violations = $this->validator->validate(
            $file,
            new FileConstraint([
                'mimeTypes' => ['image/*']
            ])
        );

        return $violations->count() === 0;
    }
}