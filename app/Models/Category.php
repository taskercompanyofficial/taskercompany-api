<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ["unique_id", "name", "slug", "description", "keywords", "image", "hero_image", "status"];
}
