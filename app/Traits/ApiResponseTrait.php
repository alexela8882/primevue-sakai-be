<?php

namespace App\Traits;

use Illuminate\Support\Facades\Input;
use Response;

trait ApiResponseTrait {

    protected $statusCode = 200;
    /**
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param mixed $statusCode
     * @return $this
     */

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function respondSuccessful($message = 'Request successful', $data = []) {
        return $this->respond([
            'message' => $message,
            'status_code' => 200,
            'data' => $data
        ]);
    }

    public function respondUnprocessable($message = 'Error. Unprocessable request') {
        return $this->setStatusCode(422)->respondWithError($message);
    }

    public function respondNotFound($message = 'Not Found!')
    {
        return $this->setStatusCode(404)->respondWithError($message);
    }

    public function respondInternalError($message = 'Internal Error!')
    {
        return $this->setStatusCode(500)->respondWithError($message);
    }

    public function respond($data, $headers = [])
    {
        return Response::json($data, $this->getStatusCode(), $headers);
    }

    public function respondWithError($message)
    {
        return $this->respond([
            'error' => [
                'message' => $message,
                'status_code' => $this->getStatusCode()
            ]
        ]);
    }

    public function respondFriendly(callable $callback, $enabled = true)
    {
        try {
            return $callback();
        } catch(\Exception $e) {
            if(!$enabled || !env('RESPOND_FRIENDLY', true))
                throw $e;
            return $this->respondUnprocessable($e->getMessage());
        }
    }

    public function confirm($message = null, $data = [], $callback = null, $yesLabel = 'Yes', $noLabel = 'No') {
        if(Input::exists('confirmed')) {
            if(is_callable($callback))
                $callback(Input::get('confirmed'));
            return Input::get('confirmed') != 'false';
        }
        else
            return $this->respond([
                'confirm' => [
                    'message' => $message ?? 'Are you sure you want to proceed?',
                    'data' => $data,
                    'yesLabel' => $yesLabel,
                    'noLabel' => $noLabel,
                    'status_code' => 226
                ]
            ]);
    }

    public function select($message = null, $choices = [], callable $callback, $selectionMin = 1, $selectionMax = 1, $required = false) {

        if(Input::exists('no-selection')) {
            if(!$required)
                $selectionMin = 0;
        }
        if(Input::exists('selected') || $selectionMin == 0)
            $callback(Input::get('selected'), $selectionMin);
        else
            return $this->respond([
               'select' => [
                   'message' => $message ?? 'Please select one ' . (($selectionMin > 1) ? ' or more ' : '') . ' of the following:',
                   'choices' => $choices,
                   'selectionMin' => $selectionMin,
                   'selectionMax' => $selectionMax,
                   'status_code' => 226
               ]
            ]);
    }

    public function getEnvironmentUser($user = null, $key = 'email') {
        \Auth::shouldUse('api');
        if(\Auth::guest() && env('APP_ENV') == 'local') {
            return \App\User::where($key, $user ?: env('DEV_USER', 'christia.l@escolifesciences.com'))->first();
        }
        else {
            return \Auth::guard('api')->user();
        }
    }

}
