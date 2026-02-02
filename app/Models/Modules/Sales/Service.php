<?php

namespace App\Models\Modules\Sales;

use App\Models\User;
use App\Models\Modules\Sales\Branch;
use App\Models\Modules\Sales\Category;
use App\Traits\HasBranchContext;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasBranchContext;
    protected $fillable = [
        'branch_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'price',
        'cost',
        'category_id',
        'created_by',
        'image_path',
        'pdf_path',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    protected $appends = [
        'image_url',
        'pdf_url',
    ];
    protected $hidden = [
        'image_path',
        'pdf_path'
    ];
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
 * Get full URL for service image
 */
public function getImageUrlAttribute(): ?string
{
    if (! $this->image_path) {
        return null;
    }

    // Already full URL
    if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
        return $this->image_path;
    }

    return asset('storage/' . $this->image_path);
}

/**
 * Get full URL for service PDF
 */
public function getPdfUrlAttribute(): ?string
{
    if (! $this->pdf_path) {
        return null;
    }

    // Already full URL
    if (filter_var($this->pdf_path, FILTER_VALIDATE_URL)) {
        return $this->pdf_path;
    }

    return asset('storage/' . $this->pdf_path);
}

}
