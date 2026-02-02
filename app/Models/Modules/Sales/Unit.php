<?php

namespace App\Models\Modules\Sales;

use App\Models\User;
use App\Traits\HasBranchContext;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasBranchContext;
    protected $fillable = [
    
        'title',
        'address',
        'location',
        'city',
        'area',

        'property_type',
        'listing_type',
        'unit_type',

        'size',
        'price',
        'description',

        'branch_id',
        'category_id',
        'created_by',

        'image_path',
        'document_path',

        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'size' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
        'document_url',
    ];
    protected $hidden = [
        'image_path',
        'document_path',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

        return asset('storage/' . $this->image_path);
    }

    public function getDocumentUrlAttribute(): ?string
    {
        if (! $this->document_path) {
            return null;
        }

        if (filter_var($this->document_path, FILTER_VALIDATE_URL)) {
            return $this->document_path;
        }

        return asset('storage/' . $this->document_path);
    }
}
