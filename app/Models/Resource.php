<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    protected $fillable = [
        "unique_id",
        "user_id",
        "name",
        "quantity",
        "branch_id",
        "price",
        "image",
    ];
    public function branch()
    {
        return $this->belongsTo(Branches::class, 'brand_id', 'unique_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'unique_id');
    }
    public function images()
    {
        return $this->hasMany(Image::class);
    }
}
