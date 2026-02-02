<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Modules\Sales\Lead;
use App\Models\Modules\Module;
use App\Models\User;
use App\Traits\HasBranchContext;

class Contract extends Model
{
    use HasFactory , HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'lead_id',
        'module_id',
        'document_name',
        'document_path',
        'notes',
        'uploaded_by',
    ];

    /**
     * Get the lead that owns the contract
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the module that owns the contract
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who uploaded the contract
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get document URL
     */
    public function getDocumentUrlAttribute()
    {
        if ($this->document_path) {
            return asset('storage/' . $this->document_path);
        }
        return null;
    }
}
