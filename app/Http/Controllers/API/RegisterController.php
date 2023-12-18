<?php
     
namespace App\Http\Controllers\API;
     
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Models\UserConfig;
use Illuminate\Support\Facades\Auth;
use Validator;
use Illuminate\Http\JsonResponse;
     
class RegisterController extends BaseController
{
    /**
     * Register api
     *
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request): JsonResponse
    {
      $validator = Validator::make($request->all(), [
        'name' => 'required',
        'email' => 'required|email',
        'password' => 'required',
        'c_password' => 'required|same:password',
      ]);
    
      if($validator->fails()){
        return $this->sendError('Validation Error.', $validator->errors());       
      }
    
      $input = $request->all();
      $input['password'] = bcrypt($input['password']);
      $user = User::create($input);
      $success['token'] =  $user->createToken('MyApp')->accessToken;
      $success['name'] =  $user->name;
  
      return $this->sendResponse($success, 'User register successfully.');
    }
     
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request): JsonResponse
    {
      if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){ 
        $user = Auth::user(); 
        $success['token'] =  $user->createToken('MyApp')-> accessToken; 
        $success['name'] =  $user->name;
        $success['_id'] =  $user->_id;

        // save user config on first time login
        $userConfig = UserConfig::where('user_id', $user->_id)->first();
        if (!$userConfig) {
          $newUserConfig = new UserConfig;
          $newUserConfig->user_id = $user->_id;
          $newUserConfig->app_theme = "esco";
          $newUserConfig->app_theme_dark = "light";
          $newUserConfig->app_theme_scale = 14;
          $newUserConfig->app_theme_ripple = false;
          $newUserConfig->app_theme_menu_type = "static";
          $newUserConfig->app_theme_input_style = "outlined";
          $newUserConfig->save();
        }

        return $this->sendResponse($success, 'User login successfully.');
      } 
      else{ 
        return $this->sendError('Unauthorised.', ['error'=>'Unauthorised']);
      } 
    }

    public function passwordLessLogin (Request $request) {
      $data = [
        'success' => true,
        'data' => ['token' => $request->session()->get('xaccessToken')],
        'message' => 'User login successfully.'
      ];

      return response()->json($data, 200);
    }

    public function logout (Request $request) {
      $request->session()->forget(['xaccessToken']);
      return response()->json('User logged out.');
    }
}