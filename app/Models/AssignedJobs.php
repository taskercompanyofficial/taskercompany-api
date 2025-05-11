<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignedJobs extends Model
{
    protected $fillable = ['job_id', 'assigned_by', 'assigned_to', 'branch_id', 'description', 'status', 'assigned_at', 'completed_at', 'remarks', 'customer_remarks', 'rating'];

    public function job()
    {
        return $this->belongsTo(Complaint::class, 'job_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(Staff::class, 'assigned_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    public function branch()
    {
        return $this->belongsTo(Branches::class, 'branch_id');
    }
}
