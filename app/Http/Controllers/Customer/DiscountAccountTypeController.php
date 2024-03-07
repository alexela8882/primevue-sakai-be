<?php

namespace App\Http\Controllers\Customer;

use App\Actions\DiscountAccountType\Create;
use App\Actions\DiscountAccountType\Destroy;
use App\Actions\DiscountAccountType\Index;
use App\Actions\DiscountAccountType\Update;
use App\Http\Controllers\Controller;
use App\Models\Customer\DiscountAccountType;
use Illuminate\Http\Request;

class DiscountAccountTypeController extends Controller
{
    protected $user;

    public function __construct()
    {
        $this->user = request()->user('api');
    }

    public function index()
    {
        return (new Index)->handle();
    }

    public function store(Request $request)
    {
        return (new Create)->handle($request);
    }

    public function update(DiscountAccountType $discountaccounttype, Request $request)
    {
        return (new Update)->handle($discountaccounttype, $request);
    }

    public function destroy(DiscountAccountType $discountaccounttype)
    {
        return (new Destroy)->handle($discountaccounttype, $this->user->_id);
    }
}
