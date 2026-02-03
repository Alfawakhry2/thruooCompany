<?php

namespace App\Models\Modules\Sales;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $connection = 'tenant';   // IMPORTANT
}
