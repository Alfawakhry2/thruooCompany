<?php

namespace App\Models\Modules\Sales;

use App\Traits\HasBranchContext;
use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    use HasBranchContext ;


    protected $fillable = [
        'branch_id',
        'team_id',
        'user_id',
        'module_id',
    ];
    
}
