<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveApproval extends Model
{
    protected $fillable = [
        'leave_request_id',
        'approver_id',
        'level',
        'status',
        'comments',
        'reviewed_at',
    ];
}
