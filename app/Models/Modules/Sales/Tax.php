<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Modules\Module;
use App\Traits\HasBranchContext;

class Tax extends Model
{
    use HasFactory, SoftDeletes , HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'name',
        'name_ar',
        'rate',
        'module_id',
        'created_by',
        'is_active',
        'is_default',
        'description',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
