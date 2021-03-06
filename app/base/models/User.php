<?php

use Illuminate\Auth\Reminders\RemindableInterface;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\UserTrait;
use Zizaco\Confide\ConfideUser;
use Zizaco\Entrust\HasRole;

class User extends ConfideUser implements UserInterface, RemindableInterface {

    use UserTrait, RemindableTrait, HasRole;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'username',
        'email',
        'mobile_number',
        'password',
        'password_confirmation',
        'organization_unit_id',
        'confirmation_code',
        'confirmed'
    ];

    /**
     * Validation Rules
     */
    static $_rules = [
        'store' => [
            'name'                  => 'required',
            'mobile_number'         => 'required',
            'username'              => 'required|alpha_dash|unique:users,username',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|min:4|confirmed',
            'password_confirmation' => 'min:4',
        ],
        'update' => [
            'name'          => 'required',
            'mobile_number' => 'required',
            'username'      => 'required|alpha_dash|unique:users,username',
            'email'         => 'required|email|unique:users,email',
        ],
        'setPassword' => [
            'password'              => 'required|min:4|confirmed',
            'password_confirmation' => 'min:4'
        ],
        'setConfirmation' => [
            'confirmed' => 'numeric|min:0|max:1',
        ],
        'changePassword' => [
            'password'              => 'required|min:4|confirmed',
            'password_confirmation' => 'min:4'
        ],
        'emailResetPassword' => [
            'token'                 => 'required',
            'password'              => 'required|min:4|confirmed',
            'password_confirmation' => 'min:4'
        ]
    ];

    static $rules = [];

    public static function setRules($name)
    {
        self::$rules = self::$_rules[$name];
    }

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = array('password', 'remember_token');


    /**
     * ACL
     */

    public static function canList()
    {
        return (Auth::user() && Auth::user()->ability(['Admin', 'User Admin'], ['User:list']));
    }

    public static function canCreate()
    {
        return (Auth::user() && Auth::user()->ability(['Admin', 'User Admin'], ['User:create']));
    }

    public function canShow()
    {
        return (Auth::user() && Auth::user()->ability(['Admin', 'User Admin'], ['User:show']));
    }

    public function canUpdate()
    {
        return (Auth::user() && Auth::user()->ability(['Admin', 'User Admin'], ['User:edit']));
    }

    public function canDelete()
    {
        return (Auth::user() && Auth::user()->ability(['Admin', 'User Admin'], ['User:delete']));
    }

    public function canSetPassword()
    {
        return (Auth::user() && Auth::user()->ability(['Admin', 'User Admin'], ['User:set_password']));
    }

    public function canSetConfirmation()
    {
        return (Auth::user() && Auth::user()->ability(['Admin', 'User Admin'], ['User:set_confirmation']));
    }

    /**
     * Other Functions
     */
    
    public function getFullName()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    public function status()
    {
        $statuses = [
            0 => 'Unconfirmed',
            1 => 'Confirmed'
        ];
        return $statuses[$this->confirmed];
    }

    /**
     * Decorators
     */

    /**
     * Boot
     */

    public static function boot()
    {
        parent::boot();

        // self::created(function ()
        // {
        //     Cache::tags('User')->flush();
        // });

        // self::updated(function ()
        // {
        //     Cache::tags('User')->flush();
        // });

        // self::deleted(function ()
        // {
        //     Cache::tags('User')->flush();
        // });
    }

}
