<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;

use Validator;

class UserController extends Controller
{

    public function get ($id) {
      $user = User::where('_id', $id)->first();
      return response()->json($user);
    }

    public function all () {
      $users = User::where('email', '!=', 'super@admin.com')->get();

      return response()->json($users, 200);
    }

    public function store (Request $req) {
      $validator = Validator::make($req->all(), [
        'name' => 'required',
        'email' => 'required|email|unique:users,email',
        'password' => 'required',
      ]);

      if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
      }

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

    public function update ($id, Request $req) {
      $validator = Validator::make($req->all(), [
        'email' => 'required|email|unique:users,email,'.$id.',_id'
      ]);

      if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
      }

      $user = User::where('_id', $id)->first();
      $user->email = $req->email;
      if ($req->password) $user->password = bcrypt($req->password);
      $user->update();

      $response = [
        'data' => $user,
        'message' => 'User successfully updated'
      ];

      return response()->json($response, 200);
    }
}
