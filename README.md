# Installation

1. `git clone https://github.com/alexela8882/primevue-sakai-be.git <project_name>`
2. `cd <project_name>`
3. `git checkout <your_designated_branch>`
4. `composer install`

## Project Setup
1. `cd <project_name>`
2. Make sure you already have mysql or mongodb setup and your `.env` file configuration is ready.
3. `php artisan migrate`
4. `php artisan db:seed`
5. `php artisan passport:install`
6. `php artisan serve` or setup linux nginx/apache.

#

# SAML2 integration

Please refer to this documentation https://github.com/24Slides/laravel-saml2

## Configuration in MongoDB
**Note: Since MongoDB has different approach to database we need to do this steps**
1. In `vendor/24slides/src/Models/Tenant.php` file, replace this line `use Illuminate\Database\Eloquent\Model;` to `use MongoDB\Laravel\Eloquent\Model;`
2. After executing `php artisan migrate` make sure there's a table named `saml2_tenants`
3. Execute this command (replace with AAD credentials which will be given to you by Microsoft Azure Administrator)
```
php artisan saml2:create-tenant --key=rdv2 --entityId=https://sts.windows.net/ee2fc0c6-4a49-4dfe-bcd0-a9b735f30129/ --loginUrl=https://login.microsoftonline.com/ee2fc0c6-4a49-4dfe-bcd0-a9b735f30129/saml2 --logoutUrl=https://login.microsoftonline.com/ee2fc0c6-4a49-4dfe-bcd0-a9b735f30129/saml2 --x509cert=MIIC8DCCAdigAwIBAgIQRVO6pK8YOZtEbtF29v+T5DANBgkqhkiG9w0BAQsFADA0MTIwMAYDVQQDEylNaWNyb3NvZnQgQXp1cmUgRmVkZXJhdGVkIFNTTyBDZXJ0aWZpY2F0ZTAeFw0yMzEyMTIwMTI5MDlaFw0yNjEyMTIwMTI5MTRaMDQxMjAwBgNVBAMTKU1pY3Jvc29mdCBBenVyZSBGZWRlcmF0ZWQgU1NPIENlcnRpZmljYXRlMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqFaLgWwBCB/Urjm5CNuKWtCDzTSQ45SBx/ghj2Koc5ykbAurdcVJxm85f5cAphn2q1PSKf2kYFDEIg+SkQg9yhPHq8xse/ubx+qcZGOn0C0djZvXgzI16mk8+0sNP5oGU03ULCbzTu90ucGFvJG+vw9cPWvdQdFgNofYrd9dGwiLObj+3+Of+G8kGCR9oJ9u8Jc98RtZSaATUcfNwAlDvUlNJmQjImFP/PzTPT83FpizH33XqGFYkaTPKnq+j+PGWhBnD8Yn26FypdsuxAhpjvp5q2J6uCcRkHAV6AHH4lPu3JnjnOcMhfI+PDduCIdfENCF3htwUZHX+zDNbNpaTQIDAQABMA0GCSqGSIb3DQEBCwUAA4IBAQBFSfpwqJv2ShAahYjegXnBn5yl1WPGSwQXGH2Pt/wjOenHd/P2ozj8h0lKyLPPB+RGqAw9d0kAJ53xH9SgjPCZcWFJFYP3dd7dDTJmVqNGrb2dgd7S78HtgCQgugkhhwWHXLZAH+UKZ4HYnLKWHi9jf9lOQfDgtwwlaVSkXRA3qkmI1gK0TZ357EBYtIEDdhpTGSuS67SCBWqIL0WRmnNyk5q0Y8kxaqRPpHPMI6kOpLsQ5O5gyuMS7jBR4oi2kHUoqf4ccPyBXqqXJEwtUwEiV1ApjFc55xGclUb1FHXwGtN+Jt28VgfTBMyPWLbIminkLTu6WM++GTKKO/
```
5. This command will generate sets of credentials
```
The tenant #657c0b1f910310336c0b5ae2 (67cf9876-2fe5-43f4-b07f-8c97690a1a53) was successfully created.

Credentials for the tenant
--------------------------

 Identifier (Entity ID): http://localhost:8000/saml2/67cf9876-2fe5-43f4-b07f-8c97690a1a53/metadata
 Reply URL (Assertion Consumer Service URL): http://localhost:8000/saml2/67cf9876-2fe5-43f4-b07f-8c97690a1a53/acs
 Sign on URL: http://localhost:8000/saml2/67cf9876-2fe5-43f4-b07f-8c97690a1a53/login
 Logout URL: http://localhost:8000/saml2/67cf9876-2fe5-43f4-b07f-8c97690a1a53/logout
 Relay State: http://localhost/saml2/51f9178c-f750-445a-8e55-e86826e85a01/login (optional)
```
  - The string inside `()` will be the `uuid` column
  - Give `Entity ID` and `Reply URL` to Microsoft Azure Administrator to make a configuration in the Microsoft AAD
4. Check `saml2_tenants` table if filled
5. Execute this command: `php artisan saml2:tenant-credentials <id>`
  - When you see errors it is because filled columns in the `saml2_tenants` are not correct and that `id` column is missing in the table
6. To fix this, you need to update `saml2_tenants` columns with the generated tenant
7. Execute this command to update `saml2_tenants` table:
```
mongo
use <db_name>
db.saml2_tenants.update("key": {<key eg. rdv2>}, {$set: {"id": 1}})
db.saml2_tenants.update("key": {<key eg. rdv2>}, {$set: {"uuid": "67cf9876-2fe5-43f4-b07f-8c97690a1a53"}})
```
8. Execute `php artisan saml2:tenant-credentials 1` again to confirm
9. When you encounter `AADSTS50011: The Reply URL does not match the reply URL configure in metadata` error when logging into Microsoft Account, in `config/saml2.php` set:
```
'proxyVars' => true, // to allow ssl
'assertionConsumerService' => [
  'url' => 'https://your.domain.com/saml2/{uui}/acs',
],
'singleLogoutService' => [
  'url' => 'https://your.domain.com/saml2/{uui}/acs'
],
```
Note: We need to change `assertionConsumerService` and `singleLogoutService` because by sometimes metadata generates `http` instead of `https`
10. If everything is setup correctly, you can now login using this laravel route `saml2.login`

# MS GRAPH

Please refer to this documentation https://github.com/dcblogdev/laravel-microsoft-graph/tree/2.0.0

## Customizing MS GRAPH package

1. Open `vendor/daveismyname/src/MsGraph.php` and in **connect** method, comment out the line starts with `if (auth()->check())` the replace it with this instead (since we are not using the default `auth` for authentication):

```
$this->storeToken(
    $accessToken->getToken(),
    $accessToken->getRefreshToken(),
    $accessToken->getExpires(),
    $id,
    $response['mail']
);
```
2. Protect all routes using msgraph with `web` and `saml2`
```
Route::group(['prefix' => 'msgraph', 'middleware' => ['web', 'saml2']], function(){
  Route::get('/', function(){
    if ((string) MsGraph::getAccessToken()) {
      return redirect(env('MSGRAPH_OAUTH_URL'));
    } else {
      //display your details
      return MsGraph::get('me');
    }
  })->middleware(['web']);

  Route::get('oauth', function() {
    return MsGraph::connect();
  });
});
```
3. `saml2` middleware is a custom middleware created to check user logged in using `24slides saml` package
