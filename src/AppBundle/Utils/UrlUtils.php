<?php


namespace AppBundle\Utils;


class UrlUtils
{
    static function getPath(string $url)
    {
        $urlData = parse_url($url);

        return $urlData["path"];
    }
}