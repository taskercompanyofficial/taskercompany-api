<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Model
{
    use HasFactory, Notifiable, HasApiTokens;
    protected $fillable = [
        'full_name',
        'username',
        'father_name',
        'contact_email',
        'phone_number',
        'secondary_phone_number',
        'password',
        'full_address',
        'state',
        'city',
        'salary',
        'branch_id',
        'cnic_front',
        'cnic_back',
        'account_maintanance_certificate',
        'blank_check',
        'reference_1_name',
        'reference_1_number',
        'reference_1_cnic',
        'reference_2_name',
        'reference_2_number',
        'reference_2_cnic',
        'profile_image',
        'role',
        'status',
        'has_crm_access',
        'notification',
    ];
    protected $hidden = ['password'];
    public function jobs()
    {
        return $this->hasMany(AssignedJobs::class, 'assigned_to', 'id');
    }
}
