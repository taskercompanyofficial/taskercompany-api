<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendence extends Model
{
    protected $fillable = [
        'staff_id',
        'check_in',
        'check_in_location',
        'check_in_longitude',
        'check_in_latitude',
        'check_out',
        'check_out_location',
        'check_out_longitude',
        'check_out_latitude',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
    public function jobs()
    {
        return $this->hasMany(AssignedJobs::class, 'assigned_to', 'id');
    }
}
