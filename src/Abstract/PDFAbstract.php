<?php

namespace App\Abstract;

use App\Traits\Projectdetails\ProjectdetailsTrait;
use Knp\Snappy\Pdf;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

class PDFAbstract extends ControllerAbstract
{
    use ProjectdetailsTrait;

    // session variables
    protected static Pdf $pdf;
    protected static string $linkedPage; // page where the heading is linked to
    protected static bool $isPageLink; // type of page where the heading is linked to, e.g., application data
    protected static string $routeIDs = '';

    public function __construct(TranslatorInterface $translator, Pdf $pdf) {
        parent::__construct($translator);
        self::$pdf = $pdf;
    }

    // functions

    /** Translates \$string. If the pdf should not be saved on disk (i.e., if preview), \$string is then converted to a link.
     * @param string $string string to be translated and eventually converted
     * @param array $parameters if $string is a translation key, parameters for the translation
     * @param string $fragment fragment to be added to the link.
     * @return string converted string
     */
    protected function addHeadingLink(string $string, array $parameters = [], string $fragment = ''): string {
        $string = $this->translateStringPDF($string,$parameters);
        if (!self::$savePDF && self::$isPageLink && $fragment!==self::dummyString) {
            $string = $this->convertStringToLink($string,self::$linkedPage,self::$routeIDs,$fragment);
        }
        return $string;
    }

    // methods

    /** Creates a pdf in the temporary folder with the session ID added to the filename.
     * @param Session $session current session
     * @param string $html html string to be converted to pdf
     * @param string $name name of the pdf file
     * @return void
     */
    protected function generatePDF(Session $session, string $html, string $name): void {
        self::$pdf->generateFromHtml($html,self::tempFolder.'/'.$name.$session->getId().'.pdf',overwrite: true); // add session ID to avoid overwriting if multiple users generate simultaneously
    }
}