<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['name', 'image', 'quantity_type', 'quantity', 'model', 'price', 'description', 'branch_id'];

    public function branch()
    {
        return $this->belongsTo(Branches::class);
    }
}
