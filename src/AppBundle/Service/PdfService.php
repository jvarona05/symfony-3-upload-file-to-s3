<?php


namespace AppBundle\Service;


use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    /**
     * @var object|null
     */
    private $templating;

    public function __construct(\Twig_Environment $templating)
    {
        $this->templating = $templating;
    }

    public function generateCustomerComparisonPdf()
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->templating->render('pdf/customer_comparison.html.twig', [
            'title' => "Welcome to our PDF Test ".uniqid()
        ]);

        // Load HTML to Dompdf
        $dompdf->loadHtml($html);

        // (Optional) Setup the paper size and orientation 'portrait' or 'portrait'
        $dompdf->setPaper('A4', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Store PDF Binary Data
        return $dompdf->output();
    }
}