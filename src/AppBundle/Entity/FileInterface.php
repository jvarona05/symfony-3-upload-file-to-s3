<?php


namespace AppBundle\Entity;


interface FileInterface
{
    public function getPath();

    public function setPath(string $path);
}