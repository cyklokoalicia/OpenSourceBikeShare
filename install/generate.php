<?php

require_once '../vendor/autoload.php';
if (file_exists("../config.php")) {
    require "../config.php";
} else {
    require "../config.php.example";
}

/**
 * @var \Bikeshare\Db\DbInterface
 */
$db=new \Bikeshare\Db\MysqliDb($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();

// create new PDF document
$pdf = new TCPDF('L', PDF_UNIT, 'A5', true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('OpenSourceBikeShare');
$pdf->SetTitle('OpenSourceBikeShare QR codes');
$pdf->SetSubject('QR codes for bikes and stands');

// remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(0,0,0);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(0);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 0);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set font
$pdf->SetFont('helvetica', 'B', 50);

// set style for barcode
$style = array(
	'border' => 0,
	'vpadding' => 1,
	'hpadding' => 0,
	'fgcolor' => array(0,0,0),
	'bgcolor' => false, //array(255,255,255)
	'module_width' => 1, // width of a single module in points
	'module_height' => 1, // height of a single module in points
	'position' => 'C'
);

$result=$db->query("SELECT bikeNum FROM bikes ORDER BY bikeNum");
while ($row=$result->fetch_assoc())
   {
   $pdf->AddPage();
   // QRCODE,M : QR-CODE Medium error correction
   $pdf->write2DBarcode($systemURL.'scan.php/rent/'.$row["bikeNum"], 'QRCODE,M', '', 18, '', 90, $style, 'N');
   $pdf->MultiCell(0, 0, $row["bikeNum"], 0, 'C', false, 0);
   }

$result=$db->query("SELECT standName FROM stands WHERE serviceTag='0' ORDER BY standName");
while ($row=$result->fetch_assoc())
   {
   $pdf->AddPage();
   // QRCODE,M : QR-CODE Medium error correction
   $pdf->write2DBarcode($systemURL.'scan.php/return/'.$row["standName"], 'QRCODE,M', '', 18, '', 90, $style, 'N');
   $pdf->MultiCell(0, 0, $row["standName"], 0, 'C', false, 0);
   }

//Close and output PDF document
$pdf->Output('qrcodes.pdf', 'D');

?>