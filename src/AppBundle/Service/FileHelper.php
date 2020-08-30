<?php


namespace AppBundle\Service;


use AppBundle\Entity\FileInterface;
use AppBundle\Entity\UserFile;
use AppBundle\Utils\HeaderUtils;
use Aws\S3\S3Client;
use League\Flysystem\FilesystemInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    /**
     * @var S3Client
     */
    private $s3Client;
    /**
     * @var string
     */
    private $s3Bucket;

    public function __construct(
        UploaderHelper $uploaderHelper,
        FilesystemInterface $awsUploadFileSystem,
        S3Client $s3Client,
        string $uploadsBaseUrl,
        string $s3Bucket
    ){
        $this->uploaderHelper = $uploaderHelper;

        $this->uploadsBaseUrl = $uploadsBaseUrl;

        $this->fileSystem = $awsUploadFileSystem;

        $this->s3Client = $s3Client;

        $this->s3Bucket = $s3Bucket;
    }

    public function downloadFile(FileInterface $file)
    {
        return $this->getFileResponse($file, HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    public function openFile(FileInterface $file)
    {
        return $this->getFileResponse($file, HeaderUtils::DISPOSITION_INLINE);
    }

    public function downloadFileFromS3(FileInterface $file)
    {
        return $this->getFileResponseFromS3($file, HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    public function openFileFromS3(FileInterface $file)
    {
        return $this->getFileResponseFromS3($file, HeaderUtils::DISPOSITION_INLINE);
    }

    private function getFileResponse(FileInterface $file, $dispositionType): StreamedResponse
    {
        $uploaderHelper = $this->uploaderHelper;

        $response = new StreamedResponse(function() use($file, $uploaderHelper){
            $outputStream = fopen('php://output', 'wp');
            $fileStream = $uploaderHelper->readStream($file->getPath());

            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $this->getMime($file));
        $disposition = HeaderUtils::makeDisposition(
            $dispositionType,
            basename($file->getPath())
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    private function getFileResponseFromS3(FileInterface $file, $dispositionType)
    {
        $disposition = HeaderUtils::makeDisposition($dispositionType,basename($file->getPath()));

        $cmd = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $this->s3Bucket,
            'Key' => $file->getPath(),
            'ResponseContentType' => $this->getMime($file),
            'ResponseContentDisposition' => $disposition
        ]);

        $request = $this->s3Client->createPresignedRequest($cmd, '+20 minutes');

        return new RedirectResponse((string)$request->getUri());
    }

    private function getMime(FileInterface $file): string
    {
        return $this->fileSystem->getMimetype($file->getPath());
    }
}