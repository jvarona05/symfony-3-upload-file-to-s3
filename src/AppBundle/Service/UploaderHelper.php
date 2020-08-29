<?php


namespace AppBundle\Service;


use Gedmo\Sluggable\Util\Urlizer;
use League\Flysystem\AdapterInterface;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UploaderHelper
{
    /**
     * @var FilesystemInterface
     */
    private $fileSystem;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * UploaderHelper constructor.
     * @param FilesystemInterface $publicUploadFileSystem
     * @param FilesystemInterface $privateUploadFileSystem
     * @param LoggerInterface $logger
     */
    public function __construct(FilesystemInterface $awsUploadFileSystem, ValidatorInterface $validator, LoggerInterface $logger)
    {
        $this->fileSystem = $awsUploadFileSystem;
        $this->logger = $logger;
        $this->validator = $validator;
    }

    public function uploadPublicFile(File $file, ?string $existingFileName = null)
    {
        $newFileName = $this->uploadFile($file, false);

        //para cuando ya existe un archivo
        if($existingFileName){
            try {
                $result = $this->fileSystem->delete($existingFileName);

                if($result === false){
                    throw new \Exception('Could not delete old uploaded file "%s"', $existingFileName);
                }
            }catch(FileNotFoundException $e){
                $this->logger->alert(sprintf('Old uploaded file "%s" as missing when trying to delete', $existingFileName));
            }
        }


        return $newFileName;
    }

    public function uploadPrivateFile(File $file, ?string $existingFileName = null)
    {
        return $this->uploadFile($file, true);
    }

    public function uploadFile(File $file, bool $isPrivate): string
    {
        $this->validateFile($file);

        $originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();

        $newFileName = Urlizer::urlize(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . uniqid() . '.' . $file->guessExtension();

        $stream = fopen($file->getPathname(), 'r');

        $result = $this->fileSystem->writeStream(
            $newFileName,
            $stream,
            [
                'visibility' => $isPrivate ? AdapterInterface::VISIBILITY_PRIVATE : AdapterInterface::VISIBILITY_PUBLIC
            ]
        );

        if($result === false){
            throw new \Exception('Could not write uploaded file "%s"', $newFileName);
        }

        if(is_resource($stream)){
            fclose($stream);
        }

        return $newFileName;
    }

    public function validateFile(File $file)
    {
        $violations = $this->validator->validate(
            $file,
            new FileConstraint([
                'maxSize' => '5M',
                'mimeTypes' => [
                    'image/*',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/plain'
                ]
            ])
        );

        if($violations->count() > 0){
            throw new \Exception("Not valid file");
        }
    }

    /**
     * @param string $path
     * @param bool $isPrivate
     * @return false|resource
     * @throws FileNotFoundException
     * @resource
     */
    public function readStream(string $path)
    {
        $resource = $this->fileSystem->readStream($path);

        if($resource === false){
            throw new \Exception('Error opening stream "%s"', $path);
        }

        return $resource;
    }

    public function deleteFile(string $path)
    {
        $result = $this->fileSystem->delete($path);

        if($result === false){
            throw new \Exception('Error file could be deleted "%s"', $path);
        }
    }
}