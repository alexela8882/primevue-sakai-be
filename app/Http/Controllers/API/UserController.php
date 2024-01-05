<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;

use DB;
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

      // get collection fields
      $defaultKeys = ['name', 'email', 'branch'];
      $rawFields = getCollectionRawFields('users');
      $excludedKeys = ['password', 'created_at', 'updated_at', 'active'];
      $fields = generateSelectableCollectionFields($rawFields, $defaultKeys, $excludedKeys);

      $response = [
        'table' => $users,
        'fields' => $fields,
      ];

      return response()->json($response, 200);
    }

    public function store (Request $req) {
      $rules = [
        'email' => 'required|email|unique:users,email',
        'first_name' => 'required',
        'last_name' => 'required',
        'middle_name' => 'required',
        // 'branch_id' => 'required',
      ];
      $messages = [
        'required' => 'The :attribute field is required.',
        // 'branch_id.required' => 'Please select a branch',
        'unique' => 'This :attribute has already been taken'
      ];
      $validator = Validator::make($req->all(), $rules, $messages);

      if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
      }

      $user = new User;
      $user->name = $req->first_name . " " . $req->last_name;
      $user->full_name = $req->first_name . " " . $req->middle_name . " " . $req->last_name;
      $user->first_name = $req->first_name;
      $user->last_name = $req->last_name;
      $user->middle_name = $req->middle_name;
      $user->email = $req->email;
      // $user->branch_id = $req->branch_id;
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
        'first_name' => 'required',
        'last_name' => 'required',
        'middle_name' => 'required',
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
      $user->name = $req->first_name . " " . $req->last_name;
      $user->full_name = $req->first_name . " " . $req->middle_name . " " . $req->last_name;
      $user->first_name = $req->first_name;
      $user->last_name = $req->last_name;
      $user->middle_name = $req->middle_name;
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
