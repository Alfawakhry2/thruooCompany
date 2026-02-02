<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The connection name for the model.
     * Uses tenant connection when tenant is resolved
     *
     * @var string|null
     */
    protected $connection = 'tenant';

    /**
     * Get the database connection for the model.
     * Dynamically uses tenant connection when tenant is resolved
     *
     * @return \Illuminate\Database\Connection
     */
    public function getConnection()
    {
        // Check if tenant connection has a database set
        $tenantDb = Config::get('database.connections.tenant.database');

        if ($tenantDb && $tenantDb !== null && $tenantDb !== '') {
            // Ensure connection is configured
            Config::set('database.connections.tenant.database', $tenantDb);
            DB::purge('tenant');
            DB::reconnect('tenant');
            $this->connection = 'tenant';
        }

        return parent::getConnection();
    }
}
