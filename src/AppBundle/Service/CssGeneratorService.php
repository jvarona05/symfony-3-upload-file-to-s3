<?php


namespace AppBundle\Service;


class CssGeneratorService
{
    /**
     * @var object|null
     */
    private $templating;

    private $color = [
      "blue",
      "red",
      "green"
    ];

    public function __construct(\Twig_Environment $templating)
    {
        $this->templating = $templating;
    }

    public function generatePoolCss()
    {
        $color = $this->color[array_rand($this->color)];

        return $this->templating->render('css/pool_styles.css.twig', [
            'color' => $color
        ]);
    }
}