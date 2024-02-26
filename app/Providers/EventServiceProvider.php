<?php

namespace App\Providers;

use App\Models\Model\Base;
use App\Models\User;
use App\Observers\BaseObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

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
        Base::observe(BaseObserver::class);

        // listen to saml2 login event
        Event::listen(\Slides\Saml2\Events\SignedIn::class, function (\Slides\Saml2\Events\SignedIn $event) {
            $messageId = $event->getAuth()->getLastMessageId();

            // your own code preventing reuse of a $messageId to stop replay attacks
            $samlUser = $event->getSaml2User();

            $userData = [
                'id' => $samlUser->getUserId(),
                'attributes' => $samlUser->getAttributes(),
                'assertion' => $samlUser->getRawSamlAssertion(),
                'session' => $samlUser->getSessionIndex(),
            ];

            // get email from attributes
            $xuser = implode($userData['attributes']['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress']);

            // dd($samlUser);
            // dd($userData);

            // get user from database
            $user = User::where('email', $xuser)->first();
            // dd($user);

            if ($user) {
                // Password-less login
                $success['token'] = $user->createToken('MyAppUsingPasswordLessAuth')->accessToken;
                $success['name'] = $user->name;
                $success['_id'] = $user->_id;
                $success['email'] = $user->email;

                // save into session
                session(['xaccessToken' => $success['token']]);
                session(['xuser_id' => $success['_id']]);
                session(['xuser_email' => $success['email']]);
            } else {
                // $message = "Your microsoft account" . $xuser . " not found in our records. Please contact the administrator.";
                // dd($message);

                // save into session
                session(['xaccessToken' => null]);
                session(['xuser_id' => null]);
                session(['xuser_email' => null]);
            }
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
