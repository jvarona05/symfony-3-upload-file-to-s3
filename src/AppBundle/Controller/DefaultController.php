<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Document;
use AppBundle\Entity\User;
use AppBundle\Service\UploaderHelper;
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
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use AppBundle\Utils\HeaderUtils;

class DefaultController extends Controller
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/create_article", name="create_article")
     */
    public function createArticle(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/upload", name="upload_file")
     * @param Request $request
     * @param UploaderHelper $uploaderHelper
     * @param CacheManager $imagineCacheManager
     * @throws \Exception
     */
    public function uploadAction(Request $request, UploaderHelper $uploaderHelper, CacheManager $cacheManager)
    {
        $uploadedFile = $request->files->get("image");
        $imageType = $request->get("type");

        $fileName = $imageType == "private"
                        ? $uploaderHelper->uploadPrivateFile($uploadedFile)
                        : $uploaderHelper->uploadPublicFile($uploadedFile);

        $resolvedPath = $cacheManager->getBrowserPath($fileName, 'my_thumb');

        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();

        $document = new Document();
        $document->setPath($fileName);
        $document->setName($fileName);
        $document->setIsPrivate($imageType == "private");
        $document->setMimeType($uploadedFile->getMimeType() ?? 'application/octet-stream');

        $user->addDocument($document);

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->persist($document);

        $em->flush();

        dump([
            "original" => $fileName,
            "thumbnail" => $resolvedPath
        ]);
        die;
    }

    /**
     * @Route("/upload/{id}", name="get_uploaded_file")
     * @IsGranted("MANAGE", subject="document")
     * @param Document $document
     * @param UploaderHelper $uploaderHelper
     * @return Response|StreamedResponse
     */
    public function getFile(Document $document, UploaderHelper $uploaderHelper)
    {
        $response = new StreamedResponse(function() use($document, $uploaderHelper){
            $outputStream = fopen('php://output', 'wp');
            $fileStream = $uploaderHelper->readStream($document->getPath());

            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $document->getMimeType());
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE, //you can use DISPOSITION_INLINE to only show the file
            $document->getName()
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * @Route("/upload/{id}/download_from_s3", name="get_uploaded_file_directly_from_s3")
     * @IsGranted("MANAGE", subject="document")
     * @param Document $document
     * @param S3Client $s3Client
     * @param string $s3Bucket
     * @return Response|StreamedResponse
     */
    public function getFileFromS3(Document $document, S3Client $s3Client)
    {
        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,$document->getName());

        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $this->getParameter("aws_bucket"),
            'Key' => $document->getPath(),
            'ResponseContentType' => $document->getMimeType(),
            'ResponseContentDisposition' => $disposition
        ]);

        $request = $s3Client->createPresignedRequest($cmd, '+20 minutes');

        return new RedirectResponse((string)$request->getUri());
    }

    /**
     * @Route("/upload/{id}/delete", name="delete_uploaded_file")
     * @IsGranted("MANAGE", subject="document")
     * @param Document $document
     * @param UploaderHelper $uploaderHelper
     * @throws \Exception
     */
    public function deleteFile(Document $document, UploaderHelper $uploaderHelper, EntityManagerInterface $em)
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
