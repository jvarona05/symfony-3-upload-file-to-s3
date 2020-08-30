<?php
// src/AppBundle/Entity/User.php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="fos_user")
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity=UserFile::class, mappedBy="user")
     */
    private $files;


    public function __construct()
    {
        parent::__construct();
        // your own logic
        $this->files = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Collection|UserFile[]
     */
    public function getDocuments(): Collection
    {
        return $this->files;
    }

    public function addFile(UserFile $document): self
    {
        if (!$this->files->contains($document)) {
            $this->files[] = $document;
            $document->setUser($this);
        }

        return $this;
    }

    public function removeFile(UserFile $document): self
    {
        if ($this->files->contains($document)) {
            $this->files->removeElement($document);
            // set the owning side to null (unless already changed)
            if ($document->getUser() === $this) {
                $document->setUser(null);
            }
        }

        return $this;
    }
}