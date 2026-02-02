<?php

namespace App\Models\Modules\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasBranchContext;
class ContractTemplate extends Model
{
    use HasBranchContext;
    protected $connection = 'tenant';
    protected $fillable = [
        "branch_id",
        'module_id',
        'title',
        'content',
        'notes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
