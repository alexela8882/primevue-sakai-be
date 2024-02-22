<?php

namespace App\Http\Controllers\API;

use App\Models\Company\Branch;
use Illuminate\Http\Request;
use Validator;

class BranchController extends BaseController
{
    public function all()
    {
        $branches = Branch::with('country')
            ->get();

        // get collection fields
        $defaultKeys = ['name', 'address', 'country'];
        $rawFields = getCollectionRawFields('branches');
        $excludedKeys = ['created_at', 'updated_at'];
        $fields = generateSelectableCollectionFields($rawFields, $defaultKeys, $excludedKeys);

        $response = [
            'table' => $branches,
            'fields' => $fields,
        ];

        return response()->json($response, 200);
    }

    public function store(Request $req)
    {
        $rules = [
            'name' => 'required|unique:branches,name',
            'address' => 'required',
            'country_id' => 'required',
        ];

        $messages = [
            'required' => 'The :attribute field is required.',
            'country_id.required' => 'Please select a country',
            'unique' => 'The :attribute field must be unique',
        ];
        $validator = Validator::make($req->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $branch = new Branch;
        $branch->name = $req->name;
        $branch->address = $req->address;
        $branch->country_id = $req->country_id;
        $branch->save();

        // stored branch
        $storedBranch = Branch::where('_id', $branch->id)->with('country')->first();

        $response = [
            'data' => $storedBranch,
            'message' => 'New branch has been successfully added into our records',
        ];

        return response()->json($response, 200);
    }

    public function update($id, Request $req)
    {
        $rules = [
            'name' => 'required|unique:branches,name,'.$id.',_id',
            'address' => 'required',
            'country_id' => 'required',
        ];
        $messages = [
            'required' => 'The :attribute field is required.',
            'country_id.required' => 'Please select a country',
            'unique' => 'The :attribute field must be unique',
        ];
        $validator = Validator::make($req->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $branch = Branch::where('_id', $id)->first();
        $branch->name = $req->name;
        $branch->address = $req->address;
        $branch->country_id = $req->country_id;
        $branch->update();

        // updated branch
        $updatedBranch = Branch::where('_id', $id)->with('country')->first();

        $response = [
            'data' => $updatedBranch,
            'message' => 'Branch has been successfully updated',
        ];

        return response()->json($response, 200);
    }

    public function delete($id)
    {
        $branch = Branch::where('_id', $id)->first();

        $response = [
            'data' => $branch,
            'message' => 'Branch successfully deleted',
        ];

        $branch->delete(); // delete collection

        return response()->json($response, 200);
    }
}
