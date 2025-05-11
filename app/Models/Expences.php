<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expences extends Model
{
    protected $table = 'expences';
    protected $fillable = [
        'amount',
        'description',
        'date',
        'category',
        'user_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(Staff::class);
    }
}
