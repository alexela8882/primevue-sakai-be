<?php

namespace App\Http\Controllers\Static;

use App\Http\Controllers\Controller;
use App\Models\Static\ActivityLog;
use App\Http\Resources\Static\ActivityLogResource;
use Illuminate\Http\Request;

use Validator;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return ActivityLogResource::collection(ActivityLog::where('id', auth()->user()->id)->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
      $rules = [
        'module_id' => 'required',
        'link_id' => 'required',
        'subject' => 'required',
        'type_id' => 'required',
        'date' => 'required',
        'status' => 'required',
        'remarks' => 'required'
      ];
      $messages = [
        'required' => 'The :attribute field is required.',
        'unique' => 'This :attribute has already been taken'
      ];
      $validator = Validator::make($request->all(), $rules, $messages);

      if ($validator->fails()) return response()->json($validator->errors(), 422);

      $log = new ActivityLog;
      $log->module_id = $request->module_id;
      $log->link_id = $request->link_id;
      $log->subject = $request->subject;
      $log->type_id = $request->type_id;
      $log->date = $request->date;
      $log->status = $request->status;
      $log->remarks = $request->remarks;
      $log->save();

      $response = [
        'data' => $log,
        'message' => 'New log was successfully added.',
        'status' => 200
      ];

      return response()->json($response, $response['status']);
    }

    /**
     * Display the specified resource.
     */
    public function show(ActivityLog $activityLog) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ActivityLog $activityLog) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ActivityLog $activityLog) {
        //
    }
}
