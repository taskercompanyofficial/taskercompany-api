<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Services extends Model
{
    protected $fillable = ['unique_id', 'category_id', 'name', 'slug', 'description', 'keywords', 'status', 'image', 'hero_image'];
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
