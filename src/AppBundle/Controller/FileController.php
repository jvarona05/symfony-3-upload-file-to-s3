<?php

namespace AppBundle\Controller;

use AppBundle\Factory\FileFactory;
use AppBundle\Service\FileHelper;
use AppBundle\Utils\UrlUtils;
use AppBundle\Entity\UserFile;
use AppBundle\Service\UploaderHelper;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Utils\HeaderUtils;

class FileController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/file/upload", name="upload_file")
     * @param Request $request
     * @param UploaderHelper $uploaderHelper
     * @param FileFactory $fileFactory
     */
    public function uploadAction(Request $request, UploaderHelper $uploaderHelper, FileFactory $fileFactory)
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get("file");
        $isPrivate = $request->get("type") == "private";

        $filePath = $uploaderHelper->uploadFile($uploadedFile, $isPrivate, "files");

        $file = $fileFactory->insertFile($uploadedFile, $filePath, $isPrivate);

        return $this->json($file);
    }

    /**
     * @Route("/file/{id}/open", name="open_file")
     * @IsGranted("MANAGE", subject="file")
     * @param UserFile $file
     * @param FileHelper $fileHelper
     * @return Response|StreamedResponse
     */
    public function openFile(UserFile $file, FileHelper $fileHelper)
    {
        return $fileHelper->openFile($file);
    }

    /**
     * @Route("/file/{id}/download", name="download_file")
     * @IsGranted("MANAGE", subject="file")
     * @param UserFile $file
     * @param FileHelper $fileHelper
     * @return Response|StreamedResponse
     */
    public function downloadFile(UserFile $file, FileHelper $fileHelper)
    {
        return $fileHelper->downloadFile($file);
    }

    /**
     * @Route("/file/{id}/from_s3/download", name="uploaded_file_directly_from_s3")
     * @IsGranted("MANAGE", subject="file")
     * @param UserFile $file
     * @param FileHelper $fileHelper
     * @return Response|StreamedResponse
     */
    public function downloadFileFromS3(UserFile $file, FileHelper $fileHelper)
    {
        return $fileHelper->downloadFileFromS3($file);
    }

    /**
     * @Route("/file/{id}/from_s3/open", name="open_file_directly_from_s3")
     * @IsGranted("MANAGE", subject="file")
     * @param UserFile $file
     * @param FileHelper $fileHelper
     * @return Response|StreamedResponse
     */
    public function openFileFromS3(UserFile $file, FileHelper $fileHelper)
    {
        return $fileHelper->openFileFromS3($file);
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
