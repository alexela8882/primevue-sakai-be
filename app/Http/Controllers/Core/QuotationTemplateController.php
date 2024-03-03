<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\QuotationTemplate;
use App\Services\SalesModuleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class QuotationTemplateController extends Controller
{
    use ApiResponseTrait;

    private $user;

    public function __construct()
    {
        $this->user = Auth::guard('api')->user();
    }

    public function index(Request $request)
    {
        return $this->respondFriendly(function () use ($request) {
            $qtFor = $request->input('qtFor', null);
            if (! $qtFor) {
                $this->respondUnprocessable('Error. Missing qtFor input for fetching Quotation Template');
            }

            return QuotationTemplate::where('qtFor', $qtFor)
                ->whereIn('branch_id', $this->user->handled_branch_ids)
                ->get();

        });
    }

    public function show(QuotationTemplate $quotationTemplate)
    {
        return (new SalesModuleService)->transform($quotationTemplate);
    }
}
