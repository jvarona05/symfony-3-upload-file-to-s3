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
     * @param FilesystemInterface $awsUploadFileSystem
     * @param ValidatorInterface $validator
     * @param LoggerInterface $logger
     */
    public function __construct(FilesystemInterface $awsUploadFileSystem, ValidatorInterface $validator, LoggerInterface $logger)
    {
        $this->fileSystem = $awsUploadFileSystem;
        $this->logger = $logger;
        $this->validator = $validator;
    }

    public function uploadFile(File $file, bool $isPrivate, $path = "", ?string $existingFilePath = null)
    {
        try {
            $this->validateFile($file);

            $filePath = $this->getFilePath($file, $path);

            $this->storeFile($file, $filePath, $isPrivate);

            $this->deleteFile($existingFilePath);

            return $filePath;

        }catch (\Exception $e){
            $this->logger->alert($e->getMessage());

            return false;
        }
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

    private function getFilePath(File $file, string $path): string
    {
        $originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();

        $newFileName = Urlizer::urlize(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . uniqid() . '.' . $file->guessExtension();

        return $path.'/'.$newFileName;
    }

    public function storeFile(File $file, string $filePath, bool $isPrivate)
    {
        $stream = fopen($file->getPathname(), 'r');

        $result = $this->fileSystem->writeStream(
            $filePath,
            $stream,
            [
                'visibility' => $isPrivate ? AdapterInterface::VISIBILITY_PRIVATE : AdapterInterface::VISIBILITY_PUBLIC
            ]
        );

        if($result === false){
            throw new \Exception('Could not write uploaded file "%s"', $filePath);
        }

        if(is_resource($stream)){
            fclose($stream);
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

    public function deleteFile(?string $path)
    {
        if(!$path) return null;

        $result = $this->fileSystem->delete($path);

        if($result === false){
            throw new \Exception('Error file could not be deleted "%s"', $path);
        }
    }
}