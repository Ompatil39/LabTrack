<?php
if (class_exists('FPDF')) return;

class FPDF
{
    // Basic state
    protected $page = 0;
    protected $pages = [];
    protected $k = 72 / 25.4; // mm units
    protected $w = 210;
    protected $h = 297;
    protected $lMargin = 10;
    protected $rMargin = 10;
    protected $tMargin = 10;
    protected $bMargin = 10;
    protected $x = 10;
    protected $y = 10;
    protected $title = '';
    protected $author = '';
    protected $creator = '';

    function __construct($orientation = 'P', $unit = 'mm', $size = 'A4') {}

    // Metadata
    function SetTitle($title)
    {
        $this->title = $title;
    }
    function SetAuthor($author)
    {
        $this->author = $author;
    }
    function SetCreator($creator)
    {
        $this->creator = $creator;
    }

    // Layout and margins
    function SetMargins($left, $top, $right = null)
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        $this->rMargin = $right === null ? $left : $right;
    }
    function SetAutoPageBreak($auto, $margin = 0)
    {
        $this->bMargin = $margin;
    }

    // Page and drawing
    function AddPage($orientation = '', $size = '', $rotation = 0)
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
    }
    function SetTextColor($r, $g = null, $b = null) {}
    function SetDrawColor($r, $g = null, $b = null) {}
    function SetFillColor($r, $g = null, $b = null) {}
    function SetLineWidth($width) {}
    function Line($x1, $y1, $x2, $y2) {}
    function Rect($x, $y, $w, $h, $style = '') {}
    function SetFont($family, $style = '', $size = 0) {}
    function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $this->x += $w;
        if ($ln > 0) $this->Ln($h);
    }
    function MultiCell($w, $h, $txt, $border = 0, $align = 'L', $fill = false)
    {
        $this->Ln($h);
    }
    function Ln($h = null)
    {
        if ($h === null) $h = 5;
        $this->x = $this->lMargin;
        $this->y += $h;
    }
    function GetY()
    {
        return $this->y;
    }
    function GetX()
    {
        return $this->x;
    }
    function SetXY($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    function Output($dest = 'I', $name = 'doc.pdf', $isUTF8 = false)
    {
        // Minimal blank PDF content
        $content = "%PDF-1.3\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R>>endobj\n4 0 obj<</Length 0>>stream\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f \n0000000010 00000 n \n0000000061 00000 n \n0000000118 00000 n \n0000000212 00000 n \ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n290\n%%EOF";
        if ($dest === 'S') {
            return $content;
        }
        echo $content;
        return '';
    }
}
