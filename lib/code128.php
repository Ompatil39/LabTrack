<?php
require_once __DIR__ . '/fpdf.php';

class PDF_Code128 extends FPDF
{
    protected $T128;
    protected $ABCset = '';
    protected $Aset = '';
    protected $Bset = '';
    protected $Cset = '';
    protected $SetFrom;
    protected $SetTo;
    protected $JStart;
    protected $JSwap;

    function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->init128();
    }

    function init128()
    {
        $this->T128 = array(
            array(2, 1, 2, 2, 2, 2),
            array(2, 2, 2, 1, 2, 2),
            array(2, 2, 2, 2, 2, 1),
            array(1, 2, 1, 2, 2, 3),
            array(1, 2, 1, 3, 2, 2),
            array(1, 3, 1, 2, 2, 2),
            array(1, 2, 2, 2, 1, 3),
            array(1, 2, 2, 3, 1, 2),
            array(1, 3, 2, 2, 1, 2),
            array(2, 2, 1, 2, 1, 3),
            array(2, 2, 1, 3, 1, 2),
            array(2, 3, 1, 2, 1, 2),
            array(1, 1, 2, 2, 3, 2),
            array(1, 2, 2, 1, 3, 2),
            array(1, 2, 2, 2, 3, 1),
            array(1, 1, 3, 2, 2, 2),
            array(1, 2, 3, 1, 2, 2),
            array(1, 2, 3, 2, 2, 1),
            array(2, 2, 3, 2, 1, 1),
            array(2, 2, 1, 1, 3, 2),
            array(2, 2, 1, 2, 3, 1),
            array(2, 1, 3, 2, 1, 2),
            array(2, 2, 3, 1, 1, 2),
            array(3, 1, 2, 1, 3, 1),
            array(3, 1, 1, 2, 2, 2),
            array(3, 2, 1, 1, 2, 2),
            array(3, 2, 1, 2, 2, 1),
            array(3, 1, 2, 2, 1, 2),
            array(3, 2, 2, 1, 1, 2),
            array(3, 2, 2, 2, 1, 1),
            array(2, 1, 2, 1, 2, 3),
            array(2, 1, 2, 3, 2, 1),
            array(2, 3, 2, 1, 2, 1),
            array(1, 1, 1, 3, 2, 3),
            array(1, 3, 1, 1, 2, 3),
            array(1, 3, 1, 3, 2, 1),
            array(1, 1, 2, 3, 1, 3),
            array(1, 3, 2, 1, 1, 3),
            array(1, 3, 2, 3, 1, 1),
            array(2, 1, 1, 3, 1, 3),
            array(2, 3, 1, 1, 1, 3),
            array(2, 3, 1, 3, 1, 1),
            array(1, 1, 2, 1, 3, 3),
            array(1, 1, 2, 3, 3, 1),
            array(1, 3, 2, 1, 3, 1),
            array(1, 1, 3, 1, 2, 3),
            array(1, 1, 3, 3, 2, 1),
            array(1, 3, 3, 1, 2, 1),
            array(3, 1, 3, 1, 2, 1),
            array(2, 1, 1, 3, 2, 2),
            array(2, 3, 1, 1, 2, 2),
            array(2, 3, 1, 3, 2, 0),
            array(2, 1, 2, 1, 2, 2),
            array(2, 1, 2, 2, 2, 1),
            array(2, 2, 2, 1, 2, 1),
            array(2, 1, 1, 2, 2, 3),
            array(2, 1, 1, 3, 2, 2),
            array(2, 3, 1, 1, 2, 1),
            array(2, 1, 2, 1, 1, 3),
            array(2, 1, 2, 3, 1, 1),
            array(2, 3, 2, 1, 1, 1),
            array(3, 1, 2, 1, 1, 2),
            array(3, 1, 1, 2, 1, 2),
            array(3, 1, 1, 2, 2, 1),
            array(3, 1, 1, 1, 2, 2),
            array(3, 1, 1, 2, 2, 0)
        );
        $this->ABCset = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~";
        $this->Aset = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_";
        $this->Bset = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~";
        $this->Cset = '0123456789';
    }

    function Code128($x, $y, $code, $w, $h)
    {
        $Aguid = '';
        for ($i = 0; $i < strlen($code); $i++) $Aguid .= (strpos($this->Aset, $code[$i]) === false) ? 'N' : 'O';
        $SminiC = 'OOOO';
        $IminiC = 4;
        $crypt = '';
        while ($code != '') {
            $i = strpos($code, $SminiC);
            if ($i !== false) {
                $tmp = substr($code, 0, $i + $IminiC);
            } else {
                $tmp = $code;
            }
            if (strlen($tmp) >= $IminiC) { // start with C
                $crypt .= chr(210);
                while (strlen($code) > 0) {
                    if (strlen($code) < 2 || preg_match('/\D/', substr($code, 0, 2))) break;
                    $crypt .= chr(intval(substr($code, 0, 2)));
                    $code = substr($code, 2);
                }
            } else { // start with B
                $crypt .= chr(104);
                $len = strlen($code);
                for ($i = 0; $i < $len; $i++) {
                    $c = ord($code[$i]);
                    $crypt .= chr($c - 32);
                }
                $code = '';
            }
        }
        // checksum
        $sum = ord($crypt[0]);
        $wght = 1;
        for ($i = 1; $i < strlen($crypt); $i++) {
            $sum += ord($crypt[$i]) * $i;
            $wght++;
        }
        $checksum = $sum % 103;
        $crypt .= chr($checksum) . chr(106) . chr(107);

        // Draw bars
        $mod = $w / (11 * strlen($crypt) + 2);
        $xpos = $x;
        for ($i = 0; $i < strlen($crypt); $i++) {
            $c = ord($crypt[$i]);
            $seq = $this->T128[$c - 100];
            $bar = true;
            for ($j = 0; $j < 6; $j++) {
                $val = $seq[$j];
                $wBar = $val * $mod;
                if ($bar) $this->Rect($xpos, $y, $wBar, $h, 'F');
                $xpos += $wBar;
                $bar = !$bar;
            }
        }
    }
}
