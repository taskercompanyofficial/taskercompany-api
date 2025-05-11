<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintDetails extends Model
{
    protected $fillable = [
        'complaint_id',
        'branch_id',
        'brand_id',
        'product',
        'model',
        'serial_number_ind',
        'serial_number_oud',
        'mq_nmb',
        'p_date',
        'complete_date',
        'technician',
        'amount',
        
        'complaint_type',
        'provided_services',
        'extra',
        'files',
    ];
}
