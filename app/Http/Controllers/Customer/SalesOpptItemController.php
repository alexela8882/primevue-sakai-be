<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Viber\CreateDiscountRequest;
use App\Http\Controllers\Controller;
use App\Models\Customer\SalesOpptItem;
use Illuminate\Http\Request;

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
