<?php

namespace AppBundle\Controller;

use AppBundle\Entity\UrlUtils;
use AppBundle\Entity\UserFile;
use AppBundle\Entity\User;
use AppBundle\Service\UploaderHelper;
use AppBundle\Utils\FileUtils;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Sluggable\Util\Urlizer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use PhpParser\Comment\Doc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use AppBundle\Utils\HeaderUtils;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/document/upload", name="upload_document")
     * @param Request $request
     * @param UploaderHelper $uploaderHelper
     * @param CacheManager $cacheManager
     * @param TokenStorageInterface $tokenStorage
     * @param FileUtils $fileUtils
     * @throws \Exception
     */
    public function uploadAction(Request $request, UploaderHelper $uploaderHelper, CacheManager $cacheManager, TokenStorageInterface $tokenStorage, FileUtils $fileUtils)
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get("document");
        $imageType = $request->get("type");

        $fileName = $imageType == "private"
                        ? $uploaderHelper->uploadPrivateFile($uploadedFile)
                        : $uploaderHelper->uploadPublicFile($uploadedFile);

        /** @var User $user */
        $user = $tokenStorage->getToken()->getUser();

        $file = new UserFile();
        $file->setPath($fileName);
        $file->setName($fileName);
        $file->setIsPrivate($imageType === "private");
        $file->setMimeType($uploadedFile->getMimeType() ?? 'application/octet-stream');
        $file->setThumbnail(
            $fileUtils->isImage($uploadedFile) ? UrlUtils::getPath($cacheManager->getBrowserPath($fileName, 'my_thumb')) : ""
        );

        $user->addFile($file);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->persist($file);

        $em->flush();

        dump($file);
        die;
    }

    /**
     * @Route("/document/{id}", name="get_uploaded_file")
     * @IsGranted("MANAGE", subject="document")
     * @param File $document
     * @param UploaderHelper $uploaderHelper
     * @return Response|StreamedResponse
     */
    public function getFile(UserFile $file, UploaderHelper $uploaderHelper)
    {
        $response = new StreamedResponse(function() use($file, $uploaderHelper){
            $outputStream = fopen('php://output', 'wp');
            $fileStream = $uploaderHelper->readStream($file->getPath());

            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $file->getMimeType());
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE, //you can use DISPOSITION_INLINE to only show the file
            $file->getName()
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * @Route("/upload/{id}/download_from_s3", name="get_uploaded_file_directly_from_s3")
     * @IsGranted("MANAGE", subject="document")
     * @param File $document
     * @param S3Client $s3Client
     * @param string $s3Bucket
     * @return Response|StreamedResponse
     */
    public function getFileFromS3(UserFile $file, S3Client $s3Client)
    {
        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,$file->getName());

        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $this->getParameter("aws_bucket"),
            'Key' => $file->getPath(),
            'ResponseContentType' => $file->getMimeType(),
            'ResponseContentDisposition' => $disposition
        ]);

        $request = $s3Client->createPresignedRequest($cmd, '+20 minutes');

        return new RedirectResponse((string)$request->getUri());
    }

    /**
     * @Route("/upload/{id}/delete", name="delete_uploaded_file")
     * @IsGranted("MANAGE", subject="document")
     * @param File $document
     * @param UploaderHelper $uploaderHelper
     * @throws \Exception
     */
    public function deleteFile(File $document, UploaderHelper $uploaderHelper, EntityManagerInterface $em)
    {
        $em->remove($document);
        $em->flush();

        $uploaderHelper->deleteFile($document->getPath());

        return new Response(null, 204);
    }

    public function generatePdfAndUpload()
    {
        $tmpPath = sys_get_temp_dir().'/uploads/'.uniqid();

        //generate pdf
        $pdf = "";

        file_put_contents($tmpPath, $pdf);

        $uploadedFile = new File($tmpPath);

        //upload file to s3

        unlink($tmpPath);
    }
}
