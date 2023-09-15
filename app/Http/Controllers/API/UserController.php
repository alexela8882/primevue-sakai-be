<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;

class UserController extends Controller
{
    public function all () {
      $users = User::all();

      return response()->json($users, 200);
    }

    public function store (Request $req) {
      $user = new User;
      $user->name = $req->name;
      $user->email = $req->email;
      $user->password = bcrypt($req->password);
      $user->save();

      $response = [
        'data' => $user,
        'message' => 'User successfully added'
      ];

      return response()->json($response, 200);
    }
}
