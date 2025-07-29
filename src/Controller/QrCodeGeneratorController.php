<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TCPDF;

class QrCodeGeneratorController extends AbstractController
{
    /**
     * @Route("/admin/qrCodeGenerator", name="qr_code_generator")
     */
    public function index(
        string $appName,
        BikeRepository $bikeRepository,
        StandRepository $standRepository
    ): Response {
        $pdf = new TCPDF('L', PDF_UNIT, 'A5', true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($appName);
        $pdf->SetTitle('OpenSourceBikeShare QR codes');
        $pdf->SetSubject('QR codes for bikes and stands');

        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, 0);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // set font
        $pdf->SetFont('helvetica', 'B', 50);

        $bikes = $bikeRepository->findAll();
        foreach ($bikes as $bike) {
            $this->addPageQrCode(
                $pdf,
                (string)$bike["bikeNum"],
                $this->generateUrl(
                    'scan_bike',
                    ['bikeNumber' => $bike["bikeNum"]],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            );
        }

        $stands = $standRepository->findAll();
        foreach ($stands as $stand) {
            if ($stand["serviceTag"] != 0) {
                continue;
            }

            $this->addPageQrCode(
                $pdf,
                $stand["standName"],
                $this->generateUrl(
                    'scan_stand',
                    ['standName' => $stand["standName"]],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            );
        }

        return new StreamedResponse(
            function () use ($pdf) {
                $pdf->Output('qrcodes.pdf', 'D');
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="qrcodes.pdf"'
            ]
        );
    }

    private function addPageQrCode(TCPDF $pdf, string $text, string $url)
    {
        // set style for barcode
        $style = array(
            'border' => 0,
            'vpadding' => 1,
            'hpadding' => 0,
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => false, //array(255,255,255)
            'module_width' => 1, // width of a single module in points
            'module_height' => 1, // height of a single module in points
            'position' => 'C'
        );

        $pdf->AddPage();
        // QRCODE,M : QR-CODE Medium error correction
        $pdf->write2DBarcode($url, 'QRCODE,M', '', 18, '', 90, $style, 'N');
        $pdf->MultiCell(0, 0, $text, 0, 'C', false, 0);
    }
}
