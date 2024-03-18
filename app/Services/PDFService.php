<?php

namespace App\Services;

use App\Models\Customer\QuotationPDF;

class PDFService
{
    public function generatePDFname($id, $name, $field)
    {
        $n = $this->generateSlugFrom($name);
        $str = $n.'_v';
        $pdf = '';
        for ($i = 1; $i > 0; $i++) {
            $pdf = $str.$i;
            $check = QuotationPDF::where($field, $id)->where('name', $pdf)->first();
            if (! $check) {
                break;
            }
        }

        return $pdf;
    }

    public function generateSlugFrom($string)
    {
        $string = preg_replace('/\//i', '-', $string);
        $string = preg_replace('/\\\/i', '-', $string);
        $string = preg_replace('/\s/', '-', $string);
        $string = preg_replace('/\-\-+/', '-', $string);
        $string = trim($string, '-');

        return $string;
    }

    public function getLast($field, $id)
    {
        $check = QuotationPDF::where($field, $id)->orderBy('name', 'desc')->first();
        if ($check) {
            return $check->name;
        }

        return null;
    }
}
