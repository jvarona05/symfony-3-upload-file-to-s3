<?php


namespace AppBundle\Service;


use AppBundle\Entity\FileInterface;
use AppBundle\Entity\UserFile;
use AppBundle\Utils\HeaderUtils;
use League\Flysystem\FilesystemInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileHelper
{
    /**
     * @var UploaderHelper
     */
    private $uploaderHelper;
    /**
     * @var string
     */
    private $uploadsBaseUrl;
    /**
     * @var FilesystemInterface
     */
    private $fileSystem;

    public function __construct(UploaderHelper $uploaderHelper, string $uploadsBaseUrl, FilesystemInterface $awsUploadFileSystem)
    {
        $this->uploaderHelper = $uploaderHelper;

        $this->uploadsBaseUrl = $uploadsBaseUrl;

        $this->fileSystem = $awsUploadFileSystem;
    }

    public function downloadFile(FileInterface $file)
    {
        return $this->getFileResponse($file, HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    public function openFile(FileInterface $file)
    {
        return $this->getFileResponse($file, HeaderUtils::DISPOSITION_INLINE);
    }

    private function getFileResponse(FileInterface $file, $dispositionType): StreamedResponse
    {
        $uploaderHelper = $this->uploaderHelper;

        $response = new StreamedResponse(function() use($file, $uploaderHelper){
            $outputStream = fopen('php://output', 'wp');
            $fileStream = $uploaderHelper->readStream($file->getPath());

            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $this->fileSystem->getMimetype($file->getPath()));
        $disposition = HeaderUtils::makeDisposition(
            $dispositionType,
            basename($file->getPath())
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}