# 🚀 COMPLETE BRANCH ISOLATION - IMPLEMENTATION CHECKLIST

## ✅ PHASE 1: SETUP & INFRASTRUCTURE (15 minutes)

### Step 1: Copy Files to Your Project

```bash
# 1. Create Trait
cp HasBranchContext.php E:\ThruooTest\app\Traits\HasBranchContext.php

# 2. Create Helper
cp BranchHelper.php E:\ThruooTest\app\Helpers\BranchHelper.php

# 3. Update Middleware
cp EnsureBranchAccess.php E:\ThruooTest\app\Http\Middleware\EnsureBranchAccess.php
```

### Step 2: Update composer.json

Add to `autoload` section:

```json
"autoload": {
    "files": [
        "app/Helpers/RouteHelpers.php",
        "app/Helpers/BranchHelper.php"
    ]
}
```

### Step 3: Run composer dump-autoload

```bash
composer dump-autoload
```

### Step 4: Update User Model

Open `app/Models/User.php` and add the methods from `UserModel_BranchMethods.php`

---

## ✅ PHASE 2: UPDATE ALL MODELS (30 minutes)

### Pattern for Each Model:

```php
<?php

namespace App\Models\Modules\Sales;

use App\Traits\HasBranchContext; // ← Add this
use Illuminate\Database\Eloquent\Model;

class ModelName extends Model
{
    use HasBranchContext; // ← Add this trait
    
    protected $connection = 'tenant';
    
    protected $fillable = [
        'branch_id',  // ← Ensure this is in fillable
        // ... other fields
    ];
    
    // Trait automatically provides:
    // - branch() relationship
    // - Auto-set branch_id on create
    // - forBranch($id) scope
    // - forBranches(array $ids) scope
    // - forUserBranches($user) scope
}
```

### Models to Update (Check each one):

- [ ] 1. `app/Models/Modules/Sales/Lead.php`
- [ ] 2. `app/Models/Modules/Sales/Contract.php`
- [ ] 3. `app/Models/Modules/Sales/ContractTemplate.php`
- [ ] 4. `app/Models/Modules/Sales/Team.php`
- [ ] 5. `app/Models/Modules/Sales/TeamMember.php` (if exists)
- [ ] 6. `app/Models/Modules/Sales/Target.php`
- [ ] 7. `app/Models/Modules/Sales/Category.php`
- [ ] 8. `app/Models/Modules/Sales/Tax.php`
- [ ] 9. `app/Models/Modules/Sales/Currency.php`
- [ ] 10. `app/Models/Modules/Sales/PaymentMethod.php`
- [ ] 11. `app/Models/Modules/Sales/Vendor.php`
- [ ] 12. `app/Models/Modules/Sales/Unit.php`
- [ ] 13. `app/Models/Modules/Sales/Attribute.php`
- [ ] 14. `app/Models/Modules/Sales/AttributeValue.php`
- [ ] 15. `app/Models/Modules/Sales/LeadSource.php`
- [ ] 16. `app/Models/Modules/Sales/LeadStatus.php`
- [ ] 17. `app/Models/Modules/Sales/Department.php`
- [ ] 18. `app/Models/Modules/Sales/Service.php`
- [ ] 19. `app/Models/Modules/Sales/Product.php`
- [ ] 20. `app/Models/Modules/Sales/ProductVariant.php`

---

## ✅ PHASE 3: UPDATE ALL CONTROLLERS (45 minutes)

### Standard Controller Pattern:

```php
public function index($moduleId, $branchId, Request $request)
{
    // Branch is already validated by middleware
    // Branch context is already set
    
    $user = auth()->user();
    
    // Use scope to filter by branch
    $query = ModelName::forBranch($branchId);
    
    // Apply additional filters...
    if ($search = $request->query('search')) {
        $query->where('name', 'like', "%{$search}%");
    }
    
    // Paginate
    $items = $query->latest()->paginate($request->query('per_page', 15));
    
    return response()->json([
        'success' => true,
        'data' => $items,
    ]);
}

public function store($moduleId, $branchId, Request $request)
{
    // Validate...
    $validated = $request->validate([
        // ... validation rules
    ]);
    
    // branch_id is automatically set by HasBranchContext trait
    // But you can explicitly set it if needed:
    $validated['branch_id'] = $branchId;
    $validated['module_id'] = $moduleId;
    $validated['created_by'] = auth()->id();
    
    $item = ModelName::create($validated);
    
    return response()->json([
        'success' => true,
        'data' => $item,
    ], 201);
}

public function show($moduleId, $branchId, $id)
{
    $item = ModelName::forBranch($branchId)->findOrFail($id);
    
    // Double-check user access (extra security)
    if (!$item->isAccessibleByUser()) {
        return response()->json([
            'success' => false,
            'message' => 'Access denied.',
        ], 403);
    }
    
    return response()->json([
        'success' => true,
        'data' => $item,
    ]);
}

public function update($moduleId, $branchId, $id, Request $request)
{
    $item = ModelName::forBranch($branchId)->findOrFail($id);
    
    // Validate...
    $validated = $request->validate([
        // ... validation rules
    ]);
    
    $item->update($validated);
    
    return response()->json([
        'success' => true,
        'data' => $item,
    ]);
}

public function destroy($moduleId, $branchId, $id)
{
    $item = ModelName::forBranch($branchId)->findOrFail($id);
    $item->delete();
    
    return response()->json([
        'success' => true,
        'message' => 'Item deleted successfully.',
    ]);
}
```

### Controllers to Update:

- [ ] 1. `LeadController` (already partially done)
- [ ] 2. `ContractController`
- [ ] 3. `TeamController`
- [ ] 4. `TargetController`
- [ ] 5. `CategoryController`
- [ ] 6. `TaxController`
- [ ] 7. `CurrencyController`
- [ ] 8. `PaymentMethodController`
- [ ] 9. `VendorController`
- [ ] 10. `UnitController`
- [ ] 11. `AttributeController`
- [ ] 12. `LeadSourceController`
- [ ] 13. `LeadStatusController`
- [ ] 14. `DepartmentController`
- [ ] 15. `ServiceController`
- [ ] 16. `ProductController`
- [ ] 17. `ProductVariantController`

---

## ✅ PHASE 4: DATABASE MIGRATION (10 minutes)

### Step 1: Run Branch ID Migration

```bash
php artisan migrate --path=database/migrations/tenant --database=tenant
```

### Step 2: Assign Existing Data to Default Branch

```bash
php artisan tinker
```

```php
// Get or create default branch
$defaultBranch = \App\Models\Modules\Sales\Branch::first();

if (!$defaultBranch) {
    $defaultBranch = \App\Models\Modules\Sales\Branch::create([
        'name' => 'Main Branch',
        'name_ar' => 'الفرع الرئيسي',
        'is_default' => true,
        'is_active' => true,
    ]);
}

// Update all tables with NULL branch_id
$tables = ['leads', 'contracts', 'teams', 'targets', 'categories', 'taxes', 
           'currencies', 'payment_methods', 'vendors', 'units', 'attributes',
           'lead_sources', 'lead_statuses', 'departments', 'services', 
           'products', 'product_variants'];

foreach ($tables as $table) {
    DB::table($table)->whereNull('branch_id')->update(['branch_id' => $defaultBranch->id]);
    echo "Updated {$table}\n";
}

// Verify no NULL branch_id remains
foreach ($tables as $table) {
    $count = DB::table($table)->whereNull('branch_id')->count();
    echo "{$table}: {$count} NULL records\n";
}
```

### Step 3: Assign Users to Default Branch

```php
$defaultBranch = \App\Models\Modules\Sales\Branch::first();
$users = \App\Models\User::all();

foreach ($users as $user) {
    if ($user->branches()->count() === 0) {
        $user->branches()->attach($defaultBranch->id);
        echo "Assigned {$user->name} to {$defaultBranch->name}\n";
    }
}
```

---

## ✅ PHASE 5: TESTING (20 minutes)

### Test 1: Branch Access Control

```bash
# Test as regular user (should only see their branch)
curl -X GET "http://localhost:8000/ahmed/api/modules/1/branches/1/leads" \
  -H "Authorization: Bearer USER_TOKEN" \
  -H "Accept: application/json"

# Test accessing different branch (should get 403)
curl -X GET "http://localhost:8000/ahmed/api/modules/1/branches/2/leads" \
  -H "Authorization: Bearer USER_TOKEN" \
  -H "Accept: application/json"

# Test as Super Admin (should see all branches)
curl -X GET "http://localhost:8000/ahmed/api/modules/1/branches/1/leads" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Accept: application/json"
```

### Test 2: Auto-Assign Branch ID

```bash
# Create lead - branch_id should be auto-set
curl -X POST "http://localhost:8000/ahmed/api/modules/1/branches/1/leads" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test Lead",
    "email": "test@example.com",
    "phone": "+1234567890",
    "source_id": 1,
    "status_id": 1
  }'

# Verify branch_id is set to 1
```

### Test 3: Data Isolation

```bash
# Create lead in Branch 1
curl -X POST ".../branches/1/leads" -d '{"name":"Lead B1"}' -H "Authorization: Bearer TOKEN"

# Try to fetch from Branch 2 (should NOT appear)
curl -X GET ".../branches/2/leads" -H "Authorization: Bearer TOKEN"
```

### Test 4: User Branch Assignment

```php
php artisan tinker

$user = \App\Models\User::find(1);
$user->getBranchIds(); // Should show assigned branches

$user->assignToBranch(2); // Assign to Branch 2
$user->getBranchIds(); // Should now include Branch 2

$user->removeFromBranch(2); // Remove from Branch 2
$user->getBranchIds(); // Should no longer include Branch 2
```

---

## ✅ PHASE 6: USER MANAGEMENT (15 minutes)

### Create User Management Endpoints

Add to `UserController`:

```php
/**
 * Get user's branches
 */
public function getBranches($moduleId, $branchId, $userId)
{
    $user = User::findOrFail($userId);
    
    return response()->json([
        'success' => true,
        'data' => $user->branches()->get(),
    ]);
}

/**
 * Assign user to branch
 */
public function assignBranch($moduleId, $branchId, $userId, Request $request)
{
    $user = User::findOrFail($userId);
    $newBranchId = $request->input('branch_id');
    
    $user->assignToBranch($newBranchId);
    
    return response()->json([
        'success' => true,
        'message' => 'User assigned to branch successfully.',
        'data' => $user->branches()->get(),
    ]);
}

/**
 * Remove user from branch
 */
public function removeBranch($moduleId, $branchId, $userId, Request $request)
{
    $user = User::findOrFail($userId);
    $removeBranchId = $request->input('branch_id');
    
    $user->removeFromBranch($removeBranchId);
    
    return response()->json([
        'success' => true,
        'message' => 'User removed from branch successfully.',
        'data' => $user->branches()->get(),
    ]);
}

/**
 * Sync user branches
 */
public function syncBranches($moduleId, $branchId, $userId, Request $request)
{
    $user = User::findOrFail($userId);
    $branchIds = $request->input('branch_ids', []);
    
    $user->syncBranches($branchIds);
    
    return response()->json([
        'success' => true,
        'message' => 'User branches synchronized successfully.',
        'data' => $user->branches()->get(),
    ]);
}
```

### Add Routes:

```php
// User Branch Management
Route::get('/{userId}/branches', [UserController::class, 'getBranches']);
Route::post('/{userId}/branches/assign', [UserController::class, 'assignBranch']);
Route::post('/{userId}/branches/remove', [UserController::class, 'removeBranch']);
Route::post('/{userId}/branches/sync', [UserController::class, 'syncBranches']);
```

---

## ✅ PHASE 7: ROLES & PERMISSIONS (Optional - Advanced)

### Make Roles Branch-Specific

#### Option A: Branch-Specific Roles (Recommended)

Update `roles` table:

```php
Schema::table('roles', function (Blueprint $table) {
    $table->foreignId('branch_id')->nullable()->after('guard_name');
    $table->index('branch_id');
});
```

Modify role assignment to include branch context.

#### Option B: Keep Roles Company-Wide (Simpler)

Keep roles company-wide but permissions still respect branch access.

**Recommendation**: Start with Option B (company-wide roles) and add Option A later if needed.

---

## 📊 VERIFICATION CHECKLIST

### Files Created:
- [ ] `app/Traits/HasBranchContext.php`
- [ ] `app/Helpers/BranchHelper.php`
- [ ] `app/Http/Middleware/EnsureBranchAccess.php` (updated)

### Files Modified:
- [ ] `composer.json` (autoload section)
- [ ] `app/Models/User.php` (added branch methods)
- [ ] All 20 model files (added `HasBranchContext` trait)
- [ ] All 17 controller files (added branch filtering)

### Commands Executed:
- [ ] `composer dump-autoload`
- [ ] `php artisan migrate --database=tenant`
- [ ] Assigned existing data to default branch
- [ ] Assigned users to branches

### Tests Passed:
- [ ] Branch access control works
- [ ] Users can only see their branch data
- [ ] Super Admin can see all branches
- [ ] Auto-assign branch_id works
- [ ] Data isolation verified
- [ ] User branch assignment works

---

## 🎯 SUCCESS CRITERIA

✅ **Users are separated by branches**
✅ **Each user belongs to one or more branches**
✅ **Users can only see data from their branches**
✅ **Super Admin / Admin can see all branches**
✅ **branch_id is auto-assigned when creating records**
✅ **No data leakage between branches**
✅ **Products, Services, Settings are all branch-specific**

---

## 🚨 TROUBLESHOOTING

### Issue: "Call to undefined function currentBranchId()"
**Fix**: Run `composer dump-autoload`

### Issue: "Branch ID is required in the URL path"
**Fix**: Verify route uses `/{companySlug}/modules/{moduleId}/branches/{branchId}/...`

### Issue: User can't access any branch
**Fix**: Assign user to at least one branch:
```php
$user->assignToBranch($branchId);
```

### Issue: branch_id is NULL when creating records
**Fix**: 
1. Ensure middleware sets current branch: `setCurrentBranch($branch)`
2. Verify `HasBranchContext` trait is used
3. Manually set: `$validated['branch_id'] = $branchId;`

---

## 📞 NEXT STEPS

After completing all phases:

1. **Test thoroughly** with different user roles
2. **Create seed data** for testing
3. **Document API** with branch requirements
4. **Train users** on branch selection
5. **Monitor logs** for access violations
6. **Set up alerts** for security issues

---

**Ready to implement? Start with Phase 1! 🚀**
