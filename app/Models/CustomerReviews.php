<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReviews extends Model
{
    protected $fillable = ['complaint_id', 'user_id', 'rating', 'reason', 'comment'];

    public function user()
    {
        return $this->belongsTo(Staff::class);
    }

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }
}
