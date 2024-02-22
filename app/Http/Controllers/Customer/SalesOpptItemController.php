<?php

namespace App\Http\Controllers\Customer;

use Illuminate\Http\Request;
use App\Actions\Viber\CreateDiscountRequest;
use App\Http\Controllers\Controller;
use App\Models\Customer\SalesOpptItem;

class SalesOpptItemController extends Controller
{
    protected $user;

    public function __construct()
    {
        $this->user = request()->user('api');
    }

    public function storeDiscountRequest(SalesOpptItem $item, Request $request)
    {
        return (new CreateDiscountRequest)->handle($item, $request->all(), $request->user('api'));
    }
}
