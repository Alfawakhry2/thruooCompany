<?php

use App\Models\Landlord\Company;
use App\Multitenancy\TenantFinder\CompanyTenantFinder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\CallQueuedClosure;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;

return [
    /*
     * This class is responsible for determining which tenant should be current
     * for the given request.
     *
     * IMPORTANT: Changed to CompanyTenantFinder to resolve by Company subdomain
     */
    'tenant_db_prefix' => env('TENANT_DB_PREFIX', ''),


    'tenant_finder' => CompanyTenantFinder::class,

    /*
     * These fields are used by tenant:artisan command to match one or more tenant.
     */
    'tenant_artisan_search_fields' => [
        'id',
        'subdomain',
    ],

    /*
     * These tasks will be performed when switching tenants.
     */
    'switch_tenant_tasks' => [
        \Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask::class,
    ],

    /*
     * This class is the model used for storing configuration on tenants.
     *
     * IMPORTANT: Changed to Company model (not Tenant)
     */
    'tenant_model' => Company::class,

    /*
     * If there is a current tenant when dispatching a job, the id of the current tenant
     * will be automatically set on the job.
     */
    'queues_are_tenant_aware_by_default' => true,

    /*
     * The connection name to reach the tenant database.
     */
    'tenant_database_connection_name' => 'tenant',

    /*
     * The connection name to reach the landlord database.
     */
    'landlord_database_connection_name' => 'mysql',

    /*
     * This key will be used to bind the current tenant in the container.
     * IMPORTANT: Changed to 'companyId' and 'currentCompany'
     */
    'current_tenant_context_key' => 'companyId',
    'current_tenant_container_key' => 'currentCompany',

    /*
     * Set it to `true` if you like to cache the tenant(s) routes
     * in a shared file using the `SwitchRouteCacheTask`.
     */
    'shared_routes_cache' => false,

    /*
     * You can customize some of the behavior of this package by using your own custom action.
     */
    'actions' => [
        'make_tenant_current_action' => MakeTenantCurrentAction::class,
        'forget_current_tenant_action' => ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => MakeQueueTenantAwareAction::class,
        'migrate_tenant' => MigrateTenantAction::class,
    ],

    /*
     * You can customize the way in which the package resolves the queueable to a job.
     */
    'queueable_to_job' => [
        SendQueuedMailable::class => 'mailable',
        SendQueuedNotifications::class => 'notification',
        CallQueuedClosure::class => 'closure',
        CallQueuedListener::class => 'class',
        BroadcastEvent::class => 'event',
    ],

    /*
     * Interface that once implemented, will make the job tenant aware
     */
    'tenant_aware_interface' => TenantAware::class,

    /*
     * Interface that once implemented, will make the job not tenant aware
     */
    'not_tenant_aware_interface' => NotTenantAware::class,

    /*
     * Jobs tenant aware even if these don't implement the TenantAware interface.
     */
    'tenant_aware_jobs' => [
        // ...
    ],

    /*
     * Jobs not tenant aware even if these don't implement the NotTenantAware interface.
     */
    'not_tenant_aware_jobs' => [
        // ...
    ],
];
