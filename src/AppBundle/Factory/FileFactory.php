<?php


namespace AppBundle\Factory;


use AppBundle\Entity\User;
use AppBundle\Entity\UserFile;
use AppBundle\Utils\FileUtils;
use AppBundle\Utils\UrlUtils;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class FileFactory
{
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;
    /**
     * @var CacheManager
     */
    private $cacheManager;
    /**
     * @var FileUtils
     */
    private $fileUtils;

    public function __construct(TokenStorageInterface $tokenStorage, CacheManager $cacheManager, FileUtils $fileUtils, EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->tokenStorage = $tokenStorage;
        $this->cacheManager = $cacheManager;
        $this->fileUtils = $fileUtils;
    }

    public function insertFile(File $uploadedFile, string $filePath, bool $isPrivate)
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();

        $file = new UserFile();
        $file->setPath($filePath);
        $file->setIsPrivate($isPrivate);
        $file->setThumbnail(
            $this->fileUtils->isImage($uploadedFile) ? UrlUtils::getPath($this->cacheManager->getBrowserPath($filePath, 'my_thumb')) : ""
        );

        $user->addFile($file);

        $this->em->persist($user);
        $this->em->persist($file);

        $this->em->flush();

        return $file;
    }
}