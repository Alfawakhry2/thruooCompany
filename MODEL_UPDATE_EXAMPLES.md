# 📦 MODEL UPDATE EXAMPLES - EXACT PATTERNS

## ✅ Example 1: Contract Model

**File**: `app/Models/Modules/Sales/Contract.php`

```php
<?php

namespace App\Models\Modules\Sales;

use App\Traits\HasBranchContext; // ← ADD THIS
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes, HasBranchContext; // ← ADD HasBranchContext

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',      // ← ENSURE THIS IS HERE
        'module_id',
        'lead_id',
        'template_id',
        'title',
        'content',
        'status',
        'signed_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Trait automatically provides:
    // - branch() relationship
    // - forBranch($id) scope
    // - forBranches($ids) scope
    // - forUserBranches($user) scope
    // - Auto-set branch_id on create

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

---

## ✅ Example 2: Product Model

**File**: `app/Models/Modules/Sales/Product.php`

```php
<?php

namespace App\Models\Modules\Sales;

use App\Traits\HasBranchContext; // ← ADD THIS
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, HasBranchContext; // ← ADD HasBranchContext

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',           // ← ENSURE THIS IS HERE
        'name',
        'name_ar',
        'description',
        'description_ar',
        'sku',
        'barcode',
        'price',
        'cost',
        'stock_quantity',
        'low_stock_threshold',
        'unit_id',
        'is_active',
        'has_variants',
        'image',
        'images',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
        'has_variants' => 'boolean',
        'images' => 'array',
    ];

    // Trait automatically provides branch filtering

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }

    public function vendors()
    {
        return $this->belongsToMany(Vendor::class, 'product_vendor');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
```

---

## ✅ Example 3: Tax Model

**File**: `app/Models/Modules/Sales/Tax.php`

```php
<?php

namespace App\Models\Modules\Sales;

use App\Traits\HasBranchContext; // ← ADD THIS
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
{
    use SoftDeletes, HasBranchContext; // ← ADD HasBranchContext

    protected $connection = 'tenant';
    protected $table = 'taxes';

    protected $fillable = [
        'branch_id',     // ← ENSURE THIS IS HERE
        'name',
        'name_ar',
        'rate',
        'type',
        'is_active',
        'description',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Trait automatically provides branch filtering
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

---

## ✅ Example 4: Service Model

**File**: `app/Models/Modules/Sales/Service.php`

```php
<?php

namespace App\Models\Modules\Sales;

use App\Traits\HasBranchContext; // ← ADD THIS
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes, HasBranchContext; // ← ADD HasBranchContext

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',         // ← ENSURE THIS IS HERE
        'name',
        'name_ar',
        'description',
        'description_ar',
        'price',
        'duration_minutes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    // Trait automatically provides branch filtering

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

---

## ✅ Example 5: LeadSource Model

**File**: `app/Models/Modules/Sales/LeadSource.php`

```php
<?php

namespace App\Models\Modules\Sales;

use App\Traits\HasBranchContext; // ← ADD THIS
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadSource extends Model
{
    use SoftDeletes, HasBranchContext; // ← ADD HasBranchContext

    protected $connection = 'tenant';
    protected $table = 'lead_sources';

    protected $fillable = [
        'branch_id',     // ← ENSURE THIS IS HERE
        'name',
        'name_ar',
        'description',
        'is_active',
        'color',
        'icon',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Trait automatically provides branch filtering

    public function leads()
    {
        return $this->hasMany(Lead::class, 'source_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

---

## 📝 QUICK CHECKLIST FOR EACH MODEL

For EVERY model, ensure:

1. ✅ `use App\Traits\HasBranchContext;` at top
2. ✅ `HasBranchContext` in use statement (e.g., `use SoftDeletes, HasBranchContext;`)
3. ✅ `'branch_id'` in `$fillable` array
4. ✅ No need to add `branch()` relationship manually (trait provides it)
5. ✅ No need to add scopes manually (trait provides them)

---

## 🎯 ALL 20 MODELS TO UPDATE

### Core Business Logic:
1. ✅ Lead
2. ✅ Contract
3. ✅ ContractTemplate
4. ✅ Team
5. ✅ TeamMember

### Settings & Configuration:
6. ✅ Tax
7. ✅ Currency
8. ✅ PaymentMethod
9. ✅ Unit
10. ✅ LeadSource
11. ✅ LeadStatus
12. ✅ Department

### Products & Services:
13. ✅ Service
14. ✅ Product
15. ✅ ProductVariant
16. ✅ Vendor
17. ✅ Category
18. ✅ Attribute
19. ✅ AttributeValue

### Tracking & Targets:
20. ✅ Target

---

## 🔍 HOW TO VERIFY

After updating each model, verify with:

```php
php artisan tinker

// Test the model
$model = \App\Models\Modules\Sales\ModelName::first();

// Check if trait is loaded
$model->branch; // Should return Branch model or null

// Check if scope works
\App\Models\Modules\Sales\ModelName::forBranch(1)->count();

// Check auto-assignment (create without branch_id)
$item = \App\Models\Modules\Sales\ModelName::create([
    // ... required fields but NOT branch_id
]);
$item->branch_id; // Should be auto-set if currentBranchId() is available
```

---

## 🚀 AUTOMATION SCRIPT

Want to update all models automatically? Here's a bash script:

```bash
#!/bin/bash

# List of model files
models=(
    "Lead"
    "Contract"
    "ContractTemplate"
    "Team"
    "Target"
    "Tax"
    "Currency"
    "PaymentMethod"
    "Unit"
    "LeadSource"
    "LeadStatus"
    "Department"
    "Service"
    "Product"
    "ProductVariant"
    "Vendor"
    "Category"
    "Attribute"
    "AttributeValue"
)

for model in "${models[@]}"; do
    file="app/Models/Modules/Sales/${model}.php"
    
    if [ -f "$file" ]; then
        echo "Updating $file..."
        
        # Add use statement if not exists
        if ! grep -q "use App\\\\Traits\\\\HasBranchContext;" "$file"; then
            sed -i '/namespace/a use App\\Traits\\HasBranchContext;' "$file"
        fi
        
        # Add trait to use statement in class
        # This part needs manual verification
        echo "  → Added use statement"
        echo "  → Please manually add 'HasBranchContext' to class use statement"
        echo "  → Verify 'branch_id' is in fillable array"
        echo ""
    else
        echo "File not found: $file"
    fi
done

echo "✅ Done! Please verify each file manually."
```

---

**Ready to update models? Start with Contract, then Product, then Tax!** 🚀
