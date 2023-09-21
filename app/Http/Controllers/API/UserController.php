<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;

use Validator;

class UserController extends Controller
{

    public function get ($id) {
      $user = User::where('_id', $id)->with('branch')->first();
      return response()->json($user);
    }

    public function all () {
      $users = User::where('email', '!=', 'super@admin.com')
              ->with('branch')
              ->get();

      return response()->json($users, 200);
    }

    public function store (Request $req) {
      $rules = [
        'email' => 'required|email|unique:users,email',
        'firstName' => 'required',
        'lastName' => 'required',
        'middleName' => 'required',
        'branch_id' => 'required',
      ];
      $messages = [
        'required' => 'The :attribute field is required.',
        'branch_id.required' => 'Please select a branch',
        'unique' => 'This :attribute has already been taken'
      ];
      $validator = Validator::make($req->all(), $rules, $messages);

      if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
      }

      $user = new User;
      $user->name = $req->firstName . " " . $req->lastName;
      $user->fullName = $req->firstName . " " . $req->middleName . " " . $req->lastName;
      $user->firstName = $req->firstName;
      $user->lastName = $req->lastName;
      $user->middleName = $req->middleName;
      $user->email = $req->email;
      $user->branch_id = $req->branch_id;
      $user->password = bcrypt($req->password);
      $user->save();

      // stored user
      $storedUser = User::where('_id', $user->id)
                    ->with('branch')
                    ->first();

      $response = [
        'data' => $storedUser,
        'message' => 'User successfully added'
      ];

      return response()->json($response, 200);
    }

    public function update ($id, Request $req) {
      $rules = [
        'email' => 'required|email|unique:users,email,'.$id.',_id',
        'firstName' => 'required',
        'lastName' => 'required',
        'middleName' => 'required',
        'branch_id' => 'required',
      ];
      $messages = [
        'required' => 'The :attribute field is required.',
        'branch_id.required' => 'Please select a branch',
        'unique' => 'This :attribute has already been taken'
      ];
      $validator = Validator::make($req->all(), $rules, $messages);

      if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
      }

      $user = User::where('_id', $id)->first();
      $user->name = $req->firstName . " " . $req->lastName;
      $user->fullName = $req->firstName . " " . $req->middleName . " " . $req->lastName;
      $user->firstName = $req->firstName;
      $user->lastName = $req->lastName;
      $user->middleName = $req->middleName;
      $user->active = $req->active;
      $user->email = $req->email;
      $user->branch_id = $req->branch_id;
      if ($req->password) $user->password = bcrypt($req->password);
      $user->update();

      // updated user
      $updatedUser = User::where('_id', $user->id)
                    ->with('branch')
                    ->first();

      $response = [
        'data' => $updatedUser,
        'message' => 'User successfully updated'
      ];

      return response()->json($response, 200);
    }

    public function delete ($id) {
      $user = User::where('_id', $id)->first();

      // prevent deleting superadmin
      if ($user->email === 'super@admin.com') return abort(403);
    
      $response = [
        'data' => $user,
        'message' => 'User successfully deleted'
      ];

      $user->delete(); // delete collection

      return response()->json($response, 200);
    }
}
