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
    public function index()
    {
        $activity_logs = ActivityLog::where('created_by', auth()->user()->id)->get();
        return ActivityLogResource::customCollection($activity_logs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
      $rules = [
        'module_id' => 'required',
        'record_id' => 'required',
        'subject' => 'required',
        'type_id' => 'required',
        'date' => 'required',
        'status' => 'required',
        'remarks' => 'required',
      ];
      $messages = [
        'required' => 'The :attribute field is required.',
        'unique' => 'This :attribute has already been taken',
      ];
      $validator = Validator::make($request->all(), $rules, $messages);

      if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
      }

      $log = new ActivityLog;
      $log->module_id = $request->module_id;
      $log->record_id = $request->record_id;
      $log->log_type = $request->log_type;
      $log->subject = $request->subject;
      $log->type_id = $request->type_id;
      $log->date = $request->date;
      $log->status = $request->status;
      $log->remarks = $request->remarks;
      $log->created_by = auth()->user()->id;
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
    public function show(ActivityLog $activityLog)
    {
        //
    }

    /**
     * Display the specified resource by record_id field.
     */
    public function indexByRecord ($record_id) {
      $logs = ActivityLog::where('record_id', $record_id)->get();

      try {
        if (count($logs) > 0) {
          $message = 'Activity logs by record successfully fetched.';
          $status = 200;
        } else {
          $message = 'No records found';
          $status = 200;
        }
      } catch (\Throwable $th) {
        $logs = [];
        $message = 'Error fetching data';
        $status = 500;
      }

      $response = [
        'data' => $logs,
        'message' => $message,
        'status' => $status
      ];

      return response()->json($response, $status);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ActivityLog $activityLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ActivityLog $activityLog)
    {
        //
    }
}
