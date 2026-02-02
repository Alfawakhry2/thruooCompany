<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Landlord\Tenant;

class ListTenants extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenants:list {--status= : Filter by status (active, inactive, suspended)}';

    /**
     * The console command description.
     */
    protected $description = 'List all tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $status = $this->option('status');

        $query = Tenant::on('mysql');

        if ($status) {
            $query->where('status', $status);
        }

        $tenants = $query->orderBy('created_at', 'desc')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found!');
            return 0;
        }

        $headers = ['ID', 'Name', 'Subdomain', 'Status', 'Plan', 'Created At'];
        $rows = [];

        foreach ($tenants as $tenant) {
            $rows[] = [
                $tenant->id,
                $tenant->name,
                $tenant->subdomain,
                $tenant->status,
                $tenant->plan ?? 'N/A',
                $tenant->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
        $this->info("Total tenants: {$tenants->count()}");

        return 0;
    }
}
