<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageUploader extends Model
{
    protected $fillable = [
        'id',
        'document_type',
        'document_path',
        'user_id',
        'file_name',
        'file_size',
        'mime_type',
        'status',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'is_public' => 'boolean',
        'tags' => 'array'
    ];

    protected $dates = [
        'uploaded_at'
    ];
}
