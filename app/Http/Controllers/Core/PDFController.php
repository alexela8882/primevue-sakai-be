<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Customer\QuotationPDF;
use App\Models\Customer\SalesQuote;
use App\Services\PDFService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use PDF;

class PDFController extends Controller
{
    use ApiResponseTrait;

    protected $user;

    public function __construct(private PDFService $pdf)
    {

        $this->user = $this->getEnvironmentUser();

    }

    public function pdfview(Request $request)
    {

        if (! Input::exists('sales_quote_id')) {
            return $this->respondUnprocessable('Error. Quotation source must be defined.');
        }

        $quote = SalesQuote::find(request('sales_quote_id'));

        if (! $quote) {
            return $this->respondUnprocessable('Error. Invalid quotation ID.');
        }

        $name = $this->pdf->generatePDFname($quote->_id, $quote->quoteNo, 'sales_quote_id');
        $data = ['header' => $request['header'],
            'body' => $request['body'],
            'footer' => $request['footer'],
            'configFooter' => $request['config']['footer'],
            'configHeader' => $request['config']['header'],
            'configRight' => $request['config']['right'],
            'configLeft' => $request['config']['left'],
            'configBottom' => $request['config']['bottom'],
            'configTop' => $request['config']['top'],
            'bodystyle' => $request['bodystyle'],
            'headerstyle' => $request['headerstyle'],
            'footerstyle' => $request['footerstyle'],
            'class' => $request['class'],
        ];

        $data['sales_quote_id'] = request('sales_quote_id');
        $data['name'] = $name;

        $data['salesAmount'] = $quote->grandTotal_all ?? 0.00;
        $data['amount_converted'] = $quote->grandTotal_all_converted ?? 0.00;
        $item = QuotationPDF::create($data);

        $item = null;
        $body = preg_replace("/\xEF\xBB\xBF/", '', request('body'));

        view()->share('header', request('header'));
        view()->share('body', $body);
        view()->share('footer', request('footer'));
        view()->share('configFooter', request('config')['footer']);
        view()->share('configHeader', request('config')['header']);
        view()->share('configRight', request('config')['right']);
        view()->share('configLeft', request('config')['left']);
        view()->share('configBottom', request('config')['bottom']);
        view()->share('configTop', request('config')['top']);
        view()->share('bodystyle', request('bodystyle'));
        view()->share('headerstyle', request('headerstyle'));
        view()->share('footerstyle', request('footerstyle'));
        view()->share('class', request('class'));

        $loc = resource_path().'/file_storage//';

        if (! \File::exists($loc)) {
            \File::makeDirectory($loc);
        }

        $loc = $loc.'/tmppdfs//';

        if (! \File::exists($loc)) {
            \File::makeDirectory($loc);
        }

        $len = strlen($this->user->email);

        $filename = $name.'_'.$this->user->email.'_'.$len.'.pdf';
        $file_to_save = $loc.$filename;

        if (\File::exists($file_to_save)) {
            \File::delete($file_to_save);
        }

        $file_to_save = $loc.$filename;

        $pdf = PDF::loadView('PDFView');
        $pdf->setOptions(['isPhpEnabled' => true]);

        if (Input::exists('email') || Input::exists('download')) {
            if (Input::get('email') || Input::get('download')) {
                $pdf->save($file_to_save);
            }
        }

        return ['savedpdf' => $item, 'message' => 'Quotation has been converted to pdf.', 'filename' => $filename];
    }

    public function pdfviewgeneral()
    {

        $item = null;
        $body = preg_replace("/\xEF\xBB\xBF/", '', request('body'));
        view()->share('header', request('header'));
        view()->share('body', $body);
        view()->share('footer', request('footer'));
        view()->share('configFooter', request('config')['footer']);
        view()->share('configHeader', request('config')['header']);
        view()->share('configRight', request('config')['right']);
        view()->share('configLeft', request('config')['left']);
        view()->share('configBottom', request('config')['bottom']);
        view()->share('configTop', request('config')['top']);
        view()->share('bodystyle', request('bodystyle'));
        view()->share('headerstyle', request('headerstyle'));
        view()->share('footerstyle', request('footerstyle'));
        view()->share('coverlabel', request('coverlabel'));
        view()->share('class', request('class'));

        $loc = resource_path().'/file_storage//';

        if (! \File::exists($loc)) {
            \File::makeDirectory($loc);
        }

        $loc = $loc.'/tmppdfs//';

        if (! \File::exists($loc)) {
            \File::makeDirectory($loc);
        }

        $len = strlen($this->user->email);

        $filename = time().'.pdf';
        $file_to_save = $loc.$filename;

        if (\File::exists($file_to_save)) {
            \File::delete($file_to_save);
        }

        $file_to_save = $loc.$filename;

        if (request('taiwantestreport')) {
            $pdf = PDF::loadView('TaiwanTestReportPDF');
        } elseif (request('globaltestreportwithcover')) {
            $pdf = PDF::loadView('GlobalTestReportWithCoverPDF');
            $pdf->getDomPDF()->set_option('isHtml5ParserEnabled', true);
            $pdf->getDomPDF()->set_option('isPhpEnabled', true);
            $pdf->getDomPDF()->set_option('isPhpEnabled', true);
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
            ]);
        } elseif (request('globaltestreportwithcover2')) {
            $pdf = PDF::loadView('GlobalTestReportWithCoverPDF2');
            $pdf->getDomPDF()->set_option('isHtml5ParserEnabled', true);
            $pdf->getDomPDF()->set_option('isPhpEnabled', true);
            $pdf->getDomPDF()->set_option('isPhpEnabled', true);
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
            ]);
        } elseif (request('globaltestreport')) {
            $pdf = PDF::loadView('GlobalTestReportPDF');
            $pdf->setOptions(['isPhpEnabled' => true]);
        } elseif (request('page_num_loc')) {
            $pdf = PDF::loadView('ServiceReportPDF');
            $pdf->setOptions(['isPhpEnabled' => true]);
        } else {
            $pdf = PDF::loadView('PDFView');
            $pdf->setOptions(['isPhpEnabled' => true]);
        }

        $pdf->save($file_to_save);

        return ['message' => 'Service Report has been converted to pdf.', 'filename' => $filename];
    }

    public function download($filename)
    {

        $loc = resource_path().'/file_storage/tmppdfs//';
        $file = $loc.$filename;

        if (Input::exists('reportCode') && Input::get('reportCode')) {
            ob_end_clean();
            $fname = Input::get('reportCode').'.pdf';
        } else {
            $len1 = strrpos($filename, '_') + 1;
            $len2 = strlen(substr($filename, $len1)) + 3;
            $len = ((int) substr($filename, $len1)) + $len2;
            ob_end_clean();

            $fname = substr($filename, 0, strlen($filename) - $len + 1).'.pdf';
        }

        if (! \File::exists($file)) {
            throw new \Exception('Error. Filename not found.', 422);
        }

        return response()->download($file, urldecode(rawurlencode($fname)));
    }

    public function generalDownload($filename, $code = null)
    {

        $loc = resource_path().'/file_storage/tmppdfs//';
        $file = $loc.$filename;

        if (! \File::exists($file)) {
            throw new \Exception('Error. Filename not found.', 422);
        }

        if ($code) {
            return response()->download($file, urldecode(rawurlencode($code.'pdf')));
        }

        return response()->download($file, urldecode(rawurlencode($filename)));

    }

    public function redownload($id)
    {

        $pdfConfig = QuotationPDF::find($id);

        if (! $pdfConfig) {
            throw new \Exception('Error. Invalid pdf ID.', 422);
        }

        $body = preg_replace("/\xEF\xBB\xBF/", '', $pdfConfig->body);
        view()->share('header', $pdfConfig->header);
        view()->share('body', $body);
        view()->share('footer', $pdfConfig->footer);
        view()->share('configFooter', $pdfConfig->configFooter);
        view()->share('configHeader', $pdfConfig->configHeader);
        view()->share('configRight', $pdfConfig->configRight);
        view()->share('configLeft', $pdfConfig->configLeft);
        view()->share('configBottom', $pdfConfig->configBottom);
        view()->share('configTop', $pdfConfig->configTop);
        view()->share('bodystyle', $pdfConfig->bodystyle);
        view()->share('headerstyle', $pdfConfig->headerstyle);
        view()->share('footerstyle', $pdfConfig->footerstyle);
        view()->share('class', $pdfConfig->class);

        $loc = resource_path().'/file_storage//';
        if (! \File::exists($loc)) {
            \File::makeDirectory($loc);
        }

        $loc = $loc.'/tmppdfs//';

        if (! \File::exists($loc)) {
            \File::makeDirectory($loc);
        }

        $len = strlen($this->user->email);

        $filename = $pdfConfig->name.'_'.$this->user->email.'_'.$len.'.pdf';
        $file_to_save = $loc.$filename;

        if (\File::exists($file_to_save)) {
            \File::delete($file_to_save);
        }

        $file_to_save = $loc.$filename;
        $pdf = PDF::loadView('PDFView');
        $pdf->save($file_to_save);

        return ['filename' => $filename];

    }

    public function deleteQuotePDF($id)
    {
        return $this->respondFriendly(function () use ($id) {
            $file = QuotationPDF::find($id);

            if (! $file) {
                return $this->respondUnprocessable('Unable to find ID.');
            }

            $file->update(['deleted_by' => $this->user->_id]);
            $file->delete();

            return $this->respondSuccessful('Successfully deleted quote PDF file.');
        });
    }

    public function getPdfViewer()
    {
        return view('PDFView');
    }
}
