<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasBranchContext;
use Illuminate\Database\Eloquent\Model;
use App\Models\Modules\Sales\Lead;
use App\Models\User;

class ActivityLog extends Model
{
    use HasFactory, HasBranchContext;
    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',  // ← Add to fillable
        'lead_id',
        'user_id',
        'user_name',
        'activity',
        'details',
    ];

    /**
     * Get the lead that owns the activity log
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the user who performed the activity
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
