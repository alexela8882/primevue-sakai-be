<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;

use App\Models\User;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Event::listen(\Slides\Saml2\Events\SignedIn::class, function (\Slides\Saml2\Events\SignedIn $event) {
          $messageId = $event->getAuth()->getLastMessageId();
          
          // your own code preventing reuse of a $messageId to stop replay attacks
          $samlUser = $event->getSaml2User();
          
          $userData = [
            'id' => $samlUser->getUserId(),
            'attributes' => $samlUser->getAttributes(),
            'assertion' => $samlUser->getRawSamlAssertion()
          ];

          // get email from attributes
          $xuser = implode($userData['attributes']['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress']);

          // get user from database
          $user = User::where('email', $xuser)->first();

          // Password-less login
          $success['token'] =  $user->createToken('MyAppUsingPasswordLessAuth')-> accessToken; 
          $success['name'] =  $user->name;
          $success['_id'] =  $user->_id;

          // save into session
          session(['xaccessToken', $success['token']]);

          return response()->json('User login successfully.', 200);
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
