<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class CrmUser extends Model
{
    use HasFactory, HasApiTokens, Notifiable;
    protected $fillable = ['username', 'email', 'phone', 'password'];
    protected $hidden = ['password'];
}
