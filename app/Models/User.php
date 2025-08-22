<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // 'name',
        // 'email',
        // 'password',

        'uuid',
        'salutation',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'employee_id',
        'personal_email',
        'office_email',
        'phone_no',
        'alt_phone_no',
        'role_id',
        'department_id',
        'designation_id',
        'date_of_joining',
        'probation_end_date',
        'date_of_birth',
        'marital_status',
        'about',
        'current_location',
        'blood_grp',
        'specially_abled',
        'employee_type_id',
        'reporting_manager_id',
        'reporting_TL_id',
        'is_disable',
        'profile_picture',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey(); //returns the primary key (usually id) of the user.
    }

    public function getJWTCustomClaims()
    {
        return []; //This method lets you add custom data (claims) to the JWT payload.
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
    public function employeeType()
    {
        return $this->belongsTo(EmployeeType::class, 'employee_type_id');
    }

    public function employeeOfMonth()
    {
        return $this->hasMany(Reward::class, 'user_id')->where('reward_category_id', 1);
    }
}
