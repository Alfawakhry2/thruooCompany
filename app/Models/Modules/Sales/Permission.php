<?php

namespace App\Models\Modules\Sales;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $connection = 'tenant';   // IMPORTANT
}
