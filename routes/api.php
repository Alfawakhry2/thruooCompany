<?php

use Illuminate\Http\Request;
use App\Models\Modules\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GlobalAuthController;
use App\Http\Controllers\Modules\Sales\Api\TaxController;
use App\Http\Controllers\Modules\Sales\Api\LeadController;
use App\Http\Controllers\Modules\Sales\Api\TeamController;
use App\Http\Controllers\Modules\Sales\Api\UnitController;
use App\Http\Controllers\Modules\Sales\Api\UserController;
use App\Http\Controllers\Modules\Sales\Api\BranchController;
use App\Http\Controllers\Modules\Sales\Api\ModuleController;
use App\Http\Controllers\Modules\Sales\Api\TargetController;
use App\Http\Controllers\Modules\Sales\Api\VendorController;
use App\Http\Controllers\Modules\Sales\Api\ProductController;
use App\Http\Controllers\Modules\Sales\Api\ServiceController;
use App\Http\Controllers\Modules\Sales\Api\CategoryController;
use App\Http\Controllers\Modules\Sales\Api\ContractController;
use App\Http\Controllers\Modules\Sales\Api\CurrencyController;
use App\Http\Controllers\Modules\Sales\Api\AttributeController;
use App\Http\Controllers\Modules\Sales\Api\DepartmentController;
use App\Http\Controllers\Modules\Sales\Api\LeadSourceController;
use App\Http\Controllers\Modules\Sales\Api\LeadStatusController;
use App\Http\Controllers\Modules\Sales\Api\TenantAuthController;
use App\Http\Controllers\Modules\Sales\Api\PaymentMethodController;
use App\Http\Controllers\Modules\Sales\Api\ProductVariantController;
use App\Http\Controllers\Modules\Sales\Api\RolePermissionController;
use App\Http\Controllers\Modules\Sales\Api\UserInvitationController;
use App\Http\Controllers\Modules\Sales\Api\ContractTemplateController;
use App\Http\Controllers\Modules\Sales\Api\TenantRegistrationController;
use App\Http\Controllers\Modules\Sales\Api\Account\AccountSettingsController;

/*
|--------------------------------------------------------------------------
| Landlord Routes (No Company Context)
|--------------------------------------------------------------------------
| These routes work on the main domain without company slug prefix
| Pattern: thruoo.com/api/... or thruoo.com/registration/...
*/

// Global Authentication Routes
Route::prefix('auth')->group(function () {
    // Get companies by email (without password validation)
    Route::post('/companies-by-email', [GlobalAuthController::class, 'getCompaniesByEmail']);

    // Global login - validates password and returns companies or auto-logs in
    Route::post('/global-login', [GlobalAuthController::class, 'globalLogin']);

    // Login with company selection (company_slug + email + password)
    Route::post('/login-with-company', [GlobalAuthController::class, 'loginWithCompany']);

    // Direct company login (alias for loginWithCompany)
    Route::post('/company-login', [GlobalAuthController::class, 'companyLogin']);
});

// Registration Routes
Route::prefix('registration')->group(function () {
    // Get registration options
    Route::get('/options', [TenantRegistrationController::class, 'getOptions']);

    // Suggest subdomain from company name
    Route::post('/suggest-subdomain', [TenantRegistrationController::class, 'suggestSubdomain']);

    // Check subdomain availability
    Route::post('/check-subdomain', [TenantRegistrationController::class, 'checkSubdomain']);

    // NEW: Slug validation endpoints
    Route::post('/suggest-slug', [TenantRegistrationController::class, 'suggestSlug']);
    Route::post('/check-slug', [TenantRegistrationController::class, 'checkSlug']);

    // Register new tenant + company
    Route::post('/register', [TenantRegistrationController::class, 'register']);
});

// Legacy route (keep for backward compatibility)
Route::post('/tenants/register', [TenantRegistrationController::class, 'register']);

/*
|--------------------------------------------------------------------------
| Tenant Routes (With Company Slug Prefix)
|--------------------------------------------------------------------------
| These routes require company slug in the URL path
| Pattern: thruoo.com/{companySlug}/api/...
*/

Route::prefix('{companySlug}')->middleware([
    // 'resolve.tenant.from.path',
    'ensure.subscription'
])->group(function () {

    // Company-specific Authentication (no auth required)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [TenantAuthController::class, 'login']);
    });

    // User Invitation Routes (Public - no auth required)
    Route::prefix('invitations')->group(function () {
        Route::post('/verify', [UserInvitationController::class, 'verifyInvitation']);
        Route::post('/complete', [UserInvitationController::class, 'completeRegistration']);
    });

    // Authenticated Tenant Routes
    Route::middleware(['auth:sanctum', 'ensure.user.belongs.to.company'])->group(function () {

        // Auth routes
        Route::prefix('auth')->group(function () {
            Route::get('/me', [TenantAuthController::class, 'me']);
            Route::post('/logout', [TenantAuthController::class, 'logout']);
        });

        // Account Settings Routes
        Route::prefix('account')->group(function () {
            // Get all account settings
            Route::get('/settings', [AccountSettingsController::class, 'index']);

            // Update personal information
            Route::get('/personal-info', [AccountSettingsController::class, 'getPersonalInfo']);
            Route::put('/personal-info', [AccountSettingsController::class, 'updatePersonalInfo']);
            Route::patch('/personal-info', [AccountSettingsController::class, 'updatePersonalInfo']);

            // Update company details (owner/admin only)
            Route::get('/company-details', [AccountSettingsController::class, 'getCompanyInfo']);
            Route::put('/company-details', [AccountSettingsController::class, 'updateCompanyDetails']);
            Route::patch('/company-details', [AccountSettingsController::class, 'updateCompanyDetails']);

            // Upload avatar
            Route::post('personal-info/avatar', [AccountSettingsController::class, 'uploadAvatar']);

            // Upload company logo (owner/admin only)
            Route::post('company-info/logo', [AccountSettingsController::class, 'uploadLogo']);
        });

        // User Invitation Management Routes (Owner/Admin only)
        Route::prefix('invitations')->group(function () {
            Route::get('/', [UserInvitationController::class, 'listInvitations']);
            Route::post('/', [UserInvitationController::class, 'invite']);
            Route::post('/{userId}/resend', [UserInvitationController::class, 'resendInvitation']);
            Route::delete('/{userId}', [UserInvitationController::class, 'cancelInvitation']);
        });

        // ========================================
        // MODULE MANAGEMENT (Company-level)
        // ========================================

        // Modules Management (Owner/Admin only)
        Route::prefix('modules')->group(function () {
            Route::get('/', [ModuleController::class, 'index']);
            Route::get('/all', [ModuleController::class, 'all']);
            Route::post('/', [ModuleController::class, 'store']);
            Route::get('/{id}', [ModuleController::class, 'show']);
            Route::put('/{id}', [ModuleController::class, 'update']);
            Route::delete('/{id}', [ModuleController::class, 'destroy']);
            Route::post('/{id}/toggle-status', [ModuleController::class, 'toggleStatus']);
        });

        // ========================================
        // MODULE-SPECIFIC ROUTES
        // ========================================
        Route::prefix('branches')->group(function () {
            Route::get('/', [BranchController::class, 'index']);
            Route::get('/all', [BranchController::class, 'all']);
            Route::get('/{id}', [BranchController::class, 'show']);
            Route::post('/', [BranchController::class, 'store']);
            Route::put('/{id}', [BranchController::class, 'update']);
            Route::delete('/{id}', [BranchController::class, 'destroy']);
            Route::post('/batch-delete', [BranchController::class, 'batchDelete']);
            Route::post('/{id}/toggle-status', [BranchController::class, 'toggleStatus']);
        });

        Route::prefix('modules/{moduleId}')->group(function () {




            // Roles Management
            Route::prefix('roles')->group(function () {
                Route::get('/', [RolePermissionController::class, 'getRoles']);
                Route::get('/{roleId}', [RolePermissionController::class, 'getRole']);
                Route::put('/{roleId}/permissions', [RolePermissionController::class, 'updateRolePermissions']);
                Route::get('/{roleName}/access-details', [RolePermissionController::class, 'getRoleAccessDetails']);
            });

            // Permissions Management
            Route::prefix('permissions')->group(function () {
                Route::get('/', [RolePermissionController::class, 'getPermissions']);
                Route::post('/toggle', [RolePermissionController::class, 'togglePermission']);
                Route::post('/bulk-update', [RolePermissionController::class, 'bulkUpdatePermissions']);
            });

            // Teams, Targets - Moved to branch specific routes


            // ========================================
            // BRANCH-SPECIFIC DATA ROUTES
            // ========================================
            // Pattern: /modules/{moduleId}/branches/{branchId}/...
            // All data here is filtered by branch_id

            Route::prefix('branches/{branchId}')->middleware(['ensure.branch.access'])->group(function () {

                // Contract Templates
                Route::prefix('contract-templates')->group(function () {
                    Route::get('/', [ContractTemplateController::class, 'index']);
                    Route::post('/', [ContractTemplateController::class, 'store']);
                    Route::get('/{id}', [ContractTemplateController::class, 'show']);
                    Route::put('/{id}', [ContractTemplateController::class, 'update']);
                    Route::delete('/{id}', [ContractTemplateController::class, 'destroy']);
                });
                // Leads Management (Branch-specific)
                Route::prefix('leads')->group(function () {
                    // Statistics
                    Route::get('/stats', [LeadController::class, 'stats']);

                    // CRUD operations
                    Route::get('/', [LeadController::class, 'index']);
                    Route::post('/', [LeadController::class, 'store']);
                    Route::get('/{leadId}', [LeadController::class, 'show']);
                    Route::put('/{leadId}', [LeadController::class, 'update']);
                    Route::delete('/{leadId}', [LeadController::class, 'destroy']);

                    // Special actions
                    Route::post('/{leadId}/reassign', [LeadController::class, 'reassign']);
                    Route::post('/{leadId}/dismiss', [LeadController::class, 'dismiss']);
                    Route::post('/{leadId}/convert', [LeadController::class, 'convert']);
                    Route::post('/batch-delete', [LeadController::class, 'batchDelete']);
                    Route::post('/batch-reassign', [LeadController::class, 'batchReassign']);

                    // Contracts for specific lead
                    Route::prefix('{leadId}/contracts')->group(function () {
                        Route::get('/', [ContractController::class, 'index']);
                        Route::post('/', [ContractController::class, 'store']);
                        Route::get('/{contractId}', [ContractController::class, 'show']);
                        Route::put('/{contractId}', [ContractController::class, 'update']);
                        Route::delete('/{contractId}', [ContractController::class, 'destroy']);
                    });
                });

                // User/Staff Management (Branch-specific)
                Route::prefix('users')->group(function () {
                    Route::get('/', [UserController::class, 'index']);
                    Route::get('/all', [UserController::class, 'all']);
                    Route::get('/roles', [UserController::class, 'getRoles']);
                    Route::post('/', [UserController::class, 'store']);
                    Route::get('/{userId}', [UserController::class, 'show']);
                    Route::put('/{userId}', [UserController::class, 'update']);
                    Route::patch('/{userId}', [UserController::class, 'update']);
                    Route::delete('/{userId}', [UserController::class, 'destroy']);
                    Route::post('/{userId}/toggle-status', [UserController::class, 'toggleStatus']);
                });

                // ========================================
                // MOVED FROM MODULE SCOPE
                // ========================================

                // Lead Sources
                Route::prefix('lead-sources')->group(function () {
                    Route::get('/', [LeadSourceController::class, 'index']);
                    Route::get('/all', [LeadSourceController::class, 'all']);
                    Route::get('/{id}', [LeadSourceController::class, 'show']);
                    Route::post('/', [LeadSourceController::class, 'store']);
                    Route::put('/{id}', [LeadSourceController::class, 'update']);
                    Route::delete('/{id}', [LeadSourceController::class, 'destroy']);
                    Route::post('/batch-delete', [LeadSourceController::class, 'batchDelete']);
                    Route::post('/{id}/toggle-status', [LeadSourceController::class, 'toggleStatus']);
                });

                // Lead Statuses
                Route::prefix('lead-statuses')->group(function () {
                    Route::get('/', [LeadStatusController::class, 'index']);
                    Route::get('/all', [LeadStatusController::class, 'all']);
                    Route::get('/{id}', [LeadStatusController::class, 'show']);
                    Route::post('/', [LeadStatusController::class, 'store']);
                    Route::put('/{id}', [LeadStatusController::class, 'update']);
                    Route::delete('/{id}', [LeadStatusController::class, 'destroy']);
                    Route::post('/reorder', [LeadStatusController::class, 'reorder']);
                    Route::post('/batch-delete', [LeadStatusController::class, 'batchDelete']);
                    Route::post('/{id}/toggle-status', [LeadStatusController::class, 'toggleStatus']);
                });

                // Departments
                Route::prefix('departments')->group(function () {
                    Route::get('/', [DepartmentController::class, 'index']);
                    Route::get('/all', [DepartmentController::class, 'all']);
                    Route::get('/{id}', [DepartmentController::class, 'show']);
                    Route::post('/', [DepartmentController::class, 'store']);
                    Route::put('/{id}', [DepartmentController::class, 'update']);
                    Route::delete('/{id}', [DepartmentController::class, 'destroy']);
                    Route::post('/batch-delete', [DepartmentController::class, 'batchDelete']);
                    Route::post('/{id}/toggle-status', [DepartmentController::class, 'toggleStatus']);
                });

                // Taxes
                Route::prefix('taxes')->group(function () {
                    Route::get('/', [TaxController::class, 'index']);
                    Route::get('/all', [TaxController::class, 'all']);
                    Route::post('/', [TaxController::class, 'store']);
                    Route::get('/{taxId}', [TaxController::class, 'show']);
                    Route::put('/{taxId}', [TaxController::class, 'update']);
                    Route::delete('/{taxId}', [TaxController::class, 'destroy']);
                });

                // Currencies
                Route::prefix('currencies')->group(function () {
                    Route::get('/', [CurrencyController::class, 'index']);
                    Route::get('/all', [CurrencyController::class, 'all']);
                    Route::post('/', [CurrencyController::class, 'store']);
                    Route::get('/{currencyId}', [CurrencyController::class, 'show']);
                    Route::put('/{currencyId}', [CurrencyController::class, 'update']);
                    Route::delete('/{currencyId}', [CurrencyController::class, 'destroy']);
                    Route::post('/convert', [CurrencyController::class, 'convert']);
                });

                // Payment Methods
                Route::prefix('payment-methods')->group(function () {
                    Route::get('/', [PaymentMethodController::class, 'index']);
                    Route::get('/all', [PaymentMethodController::class, 'all']);
                    Route::post('/', [PaymentMethodController::class, 'store']);
                    Route::get('/{paymentMethodId}', [PaymentMethodController::class, 'show']);
                    Route::put('/{paymentMethodId}', [PaymentMethodController::class, 'update']);
                    Route::delete('/{paymentMethodId}', [PaymentMethodController::class, 'destroy']);
                });

                // Categories
                Route::prefix('categories')->group(function () {
                    Route::get('/', [CategoryController::class, 'index']);
                    Route::get('/all', [CategoryController::class, 'all']);
                    Route::post('/', [CategoryController::class, 'store']);
                    Route::get('/{categoryId}', [CategoryController::class, 'show']);
                    Route::put('/{categoryId}', [CategoryController::class, 'update']);
                    Route::delete('/{categoryId}', [CategoryController::class, 'destroy']);
                    Route::post('/batch-delete', [CategoryController::class, 'batchDelete']);
                    Route::post('/{categoryId}/assign-teams', [CategoryController::class, 'assignTeams']);
                    Route::post('/{categoryId}/toggle-status', [CategoryController::class, 'toggleStatus']);
                });

                // Services
                Route::prefix('services')->group(function () {
                    Route::get('/', [ServiceController::class, 'index']);
                    Route::get('/all', [ServiceController::class, 'all']);
                    Route::post('/', [ServiceController::class, 'store']);
                    Route::get('/{id}', [ServiceController::class, 'show']);
                    Route::put('/{id}', [ServiceController::class, 'update']);
                    Route::delete('/{id}', [ServiceController::class, 'destroy']);
                    Route::post('/{id}/toggle-status', [ServiceController::class, 'toggleStatus']);
                    Route::post('/batch-delete', [ServiceController::class, 'batchDelete']);
                });

                // Products
                Route::prefix('products')->group(function () {
                    Route::get('/', [ProductController::class, 'index']);
                    Route::post('/', [ProductController::class, 'store']);
                    Route::get('/{productId}', [ProductController::class, 'show']);
                    Route::put('/{productId}', [ProductController::class, 'update']);
                    Route::delete('/{productId}', [ProductController::class, 'destroy']);
                    Route::post('/{productId}/toggle-status', [ProductController::class, 'toggleStatus']);
                    Route::post('/batch-delete', [ProductController::class, 'batchDelete']);

                    // Product Variants
                    Route::prefix('{productId}/variants')->group(function () {
                        Route::get('/', [ProductVariantController::class, 'index']);
                        Route::post('/', [ProductVariantController::class, 'store']);
                        Route::get('/{variantId}', [ProductVariantController::class, 'show']);
                        Route::put('/{variantId}', [ProductVariantController::class, 'update']);
                        Route::delete('/{variantId}', [ProductVariantController::class, 'destroy']);
                        Route::post('/{variantId}/toggle-status', [ProductVariantController::class, 'toggleStatus']);
                    });
                });

                // Units
                Route::prefix('units')->group(function () {
                    Route::get('/', [UnitController::class, 'index']);
                    Route::get('/all', [UnitController::class, 'all']);
                    Route::get('/{id}', [UnitController::class, 'show']);
                    Route::post('/', [UnitController::class, 'store']);
                    Route::put('/{id}', [UnitController::class, 'update']);
                    Route::delete('/{id}', [UnitController::class, 'destroy']);
                    Route::post('/batch-delete', [UnitController::class, 'batchDelete']);
                    Route::post('/{id}/toggle-status', [UnitController::class, 'toggleStatus']);
                });

                // Vendors
                Route::prefix('vendors')->group(function () {
                    Route::get('/', [VendorController::class, 'index']);
                    Route::get('/all', [VendorController::class, 'all']);
                    Route::post('/', [VendorController::class, 'store']);
                    Route::get('/{vendorId}', [VendorController::class, 'show']);
                    Route::put('/{vendorId}', [VendorController::class, 'update']);
                    Route::delete('/{vendorId}', [VendorController::class, 'destroy']);
                });

                // Attributes
                Route::prefix('attributes')->group(function () {
                    Route::get('/', [AttributeController::class, 'index']);
                    Route::get('/all', [AttributeController::class, 'all']);
                    Route::post('/', [AttributeController::class, 'store']);
                    Route::get('/{attributeId}', [AttributeController::class, 'show']);
                    Route::put('/{attributeId}', [AttributeController::class, 'update']);
                    Route::delete('/{attributeId}', [AttributeController::class, 'destroy']);

                    // Attribute Values
                    Route::post('/{attributeId}/values', [AttributeController::class, 'addValue']);
                    Route::put('/{attributeId}/values/{valueId}', [AttributeController::class, 'updateValue']);
                    Route::delete('/{attributeId}/values/{valueId}', [AttributeController::class, 'deleteValue']);
                });

                // Teams Management (Branch-specific)
                Route::prefix('teams')->group(function () {
                    Route::get('/', [TeamController::class, 'index']);
                    Route::post('/', [TeamController::class, 'store']);
                    Route::get('/{teamId}', [TeamController::class, 'show']);
                    Route::put('/{teamId}', [TeamController::class, 'update']);
                    Route::delete('/{teamId}', [TeamController::class, 'destroy']);
                    Route::post('/{teamId}/members', [TeamController::class, 'addMember']);
                    Route::delete('/{teamId}/members/{userId}', [TeamController::class, 'removeMember']);
                    Route::get('/{teamId}/performance', [TeamController::class, 'performance']);
                    Route::get('/my-teams', [TeamController::class, 'myTeams']);
                });

                // Targets Management (Branch-specific)
                Route::prefix('targets')->group(function () {
                    Route::get('/stats', [TargetController::class, 'stats']);
                    Route::get('/my-targets', [TargetController::class, 'myTargets']);
                    Route::get('/', [TargetController::class, 'index']);
                    Route::post('/', [TargetController::class, 'store']);
                    Route::get('/{targetId}', [TargetController::class, 'show']);
                    Route::put('/{targetId}', [TargetController::class, 'update']);
                    Route::delete('/{targetId}', [TargetController::class, 'destroy']);
                    Route::post('/{targetId}/refresh', [TargetController::class, 'refreshProgress']);
                });


            }); // End of branch-specific routes

        }); // End of module-specific routes

    }); // End of authenticated routes

}); // End of tenant routes
