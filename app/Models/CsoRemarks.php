<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CsoRemarks extends Model
{
    protected $fillable = ['complaint_id', 'user_id', 'remarks'];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user()
    {
        return $this->belongsTo(Staff::class);
    }
}
