<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\User\Permission;
// use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Services\AccessService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\HasApiTokens;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $connection = 'mongodb';

    protected $collection = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Find the user instance for the given email.
     *
     * @param  string  $email
     * @return \App\User
     */
    public function findForPassport($email)
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Validate the password of the user for the Passport password grant.
     *
     * @param  string  $password
     * @return bool
     */
    public function validateForPassportPasswordGrant($password)
    {
        return Hash::check($password, $this->password);
    }

    // relationships
    public function branch()
    {
        return $this->belongsTo('App\Models\Company\Branch', 'branch_id', '_id');
    }

    public function handledBranches()
    {
        return $this->belongsToMany('App\Models\Company\Branch', null, 'handling_user_ids', 'handled_branch_ids');
    }

    public function roles()
    {
        return $this->belongsToMany('App\Models\User\Role', null, 'user_id', 'role_id');
    }

    public function position()
    {
        return $this->belongsTo('App\Models\Employee\Position', 'position_id', '_id');
    }

    public function loginHistories()
    {
        return $this->hasMany('App\Models\Auth\LoginHistory');
    }

    public function contactInformation()
    {
        return $this->hasOne('App\Models\Customer\Contact', 'employee_id');
    }

    public function canRead($moduleName)
    {

        $has = Permission::query()
            ->whereIn('role_id', $this->role_id)
            ->where('name', $moduleName.'.show')->first();

        return $has ? true : false;
    }

    public function canView($moduleName)
    {

        $has = Permission::query()
            ->whereIn('role_id', $this->role_id)
            ->where('name', $moduleName.'.index')->first();

        return $has ? true : false;
    }

    public function canDelete($moduleName)
    {

        $has = Permission::query()
            ->whereIn('role_id', $this->role_id)
            ->where('name', $moduleName.'.delete')->first();

        return $has ? true : false;
    }

    public function canUpdate($moduleName)
    {

        $has = Permission::query()
            ->whereIn('role_id', $this->role_id)
            ->where('name', $moduleName.'.update')->first();

        return $has ? true : false;

    }

    public function getPeople()
    {

        $people = (new AccessService)->hasUnder($this->role_id, $this->handled_branch_ids);
        $people[] = $this->_id;

        return $people;
    }
}
