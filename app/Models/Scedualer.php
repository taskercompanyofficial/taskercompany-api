<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scedualer extends Model
{
    protected $table = 'scedular';
    protected $fillable = ['complaint_id','user_id','date','complaint_details'];
}
