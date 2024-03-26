<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Inquiry;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;
use App\Services\ModuleDataCollector;

class InquiryController extends Controller
{

    use ApiResponseTrait;

    public function __construct(private ModuleDataCollector $mdc)
    {
        $this->mdc->setUser()->setModule('inquiries');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return $this->mdc->getIndex($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $inquiry = $this->mdc->postStore($request);
        return $this->respondSuccessful('Inquiry successfully saved', ['_id' => $inquiry->_id]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Inquiry $inquiry, Request $request)
    {
        return $this->mdc->getShow($inquiry, $request);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Inquiry $inquiry)
    {
        return $this->mdc->patchUpdate($inquiry, $request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Inquiry $inquiry)
    {
        //
    }
}
