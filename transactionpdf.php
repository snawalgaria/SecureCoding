<?php

require_once('tcpdf/tcpdf.php');

// extend TCPF with custom functions
class TransactionPDF extends TCPDF {

    /**
     * Use this function to create a TransactionPDF object
     * Returns: The TransactionPDF object
     */
    public static function create($header, $data)
    {
        // create new PDF document
        $pdf = new TransactionPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        //$pdf->SetAuthor('');
        $pdf->SetTitle('Transaction History');
        $pdf->SetSubject('Transaction History');
        //$pdf->SetKeywords('Transaction, History');

        // set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

        // set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        $pdf->SetFont('helvetica', '', 12);

        $pdf->AddPage();

        // print colored table
        //$pdf->ColoredTable($header, $data);
        TransactionPDF::createTable($pdf, $header, $data);

        return $pdf;
    }
    
    /**
     * Fills the pdf with a table of transactions
     * TODO: Make it pretty
     */
    public static function createTable($pdf, $header, $data)
    {
        $dimensions = $pdf->getPageDimensions();
        $cellWidth = 45;
        $needsHeader = true; // The header should be on every page's top
        $currentPage = 1;

        foreach($data as $row) {

            // Height of the text in lines
            $lineCount = TransactionPDF::getLineCount($pdf, $row, $cellWidth);

            $startY = $pdf->GetY();

            if (($startY + $lineCount * 6) + $dimensions['bm'] > ($dimensions['hk'])) {
                //this row would cause a page break, so go to the next page
                $pdf->AddPage();
                $currentPage++;
                $pdf->SetPage($currentPage);
                
                $needsHeader = true;
            }

            // Add the header if needed
            if ($needsHeader) {
                $borders = 'LRTB';
                $lineCount = TransactionPDF::getLineCount($pdf, $header, $cellWidth);
                
                for ($i = 0; $i < sizeof($row); $i++)
                    $pdf->MultiCell($cellWidth, $lineCount * 6, $header[$i], $borders, 'L', 0, 0);
                $pdf->Ln();
                
                $needsHeader = false;
            }

            // Now draw the row
            $borders = 'LRB';
            $lineCount = TransactionPDF::getLineCount($pdf, $row, $cellWidth);

            for ($i = 0; $i < sizeof($row); $i++)
                $pdf->MultiCell($cellWidth, $lineCount * 6, $row[$i], $borders, 'L', 0, 0);
            $pdf->Ln();
        }

    }
    
    public static function getLineCount($pdf, $row, $cellWidth)
    {
        $lineCount = 0;
        for ($i = 0; $i < sizeof($row); $i++)
            $lineCount = max($pdf->getNumLines($row[$i], $cellWidth), $lineCount);
        return $lineCount;
    }
    
    //Page header
    public function Header() {
        // Logo
        //$image_file = K_PATH_IMAGES.'logo_example.jpg';
        //$this->Image($image_file, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        // Set font
        $this->SetFont('helvetica', 'B', 20);
        // Title
        $this->Cell(0, 15, 'Transaction History', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}



?>