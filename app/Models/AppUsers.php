<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppUsers extends Model
{
    use HasFactory, Notifiable, HasApiTokens;
    protected $fillable = ['name', 'phone', 'password', 'email_verified_at', 'phone_verified_at', 'status'];
    protected $hidden = ['password'];
}

