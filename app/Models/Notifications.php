<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifications extends Model
{
    protected $fillable = ['user_id', 'title', 'body', 'type', 'is_read', 'params'];

    public function user()
    {
        return $this->belongsTo(Staff::class);
    }
}
