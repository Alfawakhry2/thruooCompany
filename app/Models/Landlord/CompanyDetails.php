<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Company Details Model
 * Stores additional information about companies for better management
 */
class CompanyDetails extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The connection name for the model (Landlord database)
     */
    protected $connection = 'mysql';

    /**
     * The table associated with the model
     */
    protected $table = 'company_details';

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'company_id',
        'description',
        'founded_year',
        'employee_count',
        'annual_revenue',
        'currency',
        'facebook',
        'instagram',
        'linkedin',
        'twitter',
        'youtube',
        'tiktok',
        'snapchat',
        'whatsapp',
        'secondary_email',
        'secondary_phone',
        'fax',
        'business_hours',
        'additional_settings',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast
     */
    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'additional_settings' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Relationship with Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}

