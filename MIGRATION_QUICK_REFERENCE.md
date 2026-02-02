# Path-Based Multi-Tenancy Migration - Quick Reference

## What Changed?

### URL Structure

**Before (Subdomain-based):**
```
ahmed.thruoo.com/api/auth/login
ahmed.thruoo.com/modules/1/leads
```

**After (Path-based):**
```
thruoo.com/ahmed/api/auth/login
thruoo.com/ahmed/modules/1/branches/1/leads
```

---

## Key Files Modified

### 1. Database
- ✅ Added `slug` column to `companies` table (unique)
- ✅ Populated slugs from existing subdomain values

### 2. Tenant Resolution
- ✅ `CompanyTenantFinder.php` - Extracts company from URL path instead of subdomain

### 3. Middleware (New)
- ✅ `ResolveTenantFromPath` - Resolves company from `{companySlug}` route parameter
- ✅ `EnsureUserBelongsToCompany` - Verifies user belongs to company in URL
- ✅ `EnsureBranchAccess` - Verifies user can access specific branch

### 4. Routes
- ✅ `routes/api.php` - Complete restructure:
  - Landlord routes: `/api/...` (no prefix)
  - Tenant routes: `/{companySlug}/api/...`
  - Branch routes: `/{companySlug}/api/modules/{id}/branches/{branchId}/...`

### 5. Controllers
- ✅ `TenantRegistrationController` - Added `checkSlug()` and `suggestSlug()` methods
- ✅ `GlobalAuthController` - Updated to accept `company_slug` parameter

### 6. Configuration
- ✅ `bootstrap/app.php` - Registered new middleware
- ✅ `composer.json` - Added RouteHelpers.php to autoload
- ✅ `.htaccess` - Added static asset exclusion rule

---

## Quick Start Commands

```bash
# 1. Dump autoload
composer dump-autoload

# 2. Run migrations
php artisan migrate --path=database/migrations/landlord --database=mysql

# 3. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# 4. Rebuild caches
php artisan config:cache
php artisan route:cache

# 5. Verify slugs
php artisan tinker
>>> App\Models\Landlord\Company::on('mysql')->select('name', 'slug')->get();
```

---

## API Endpoint Examples

### Landlord (No Company Prefix)
```bash
POST /api/registration/register
POST /api/registration/check-slug
POST /api/auth/login-with-company
```

### Tenant (With Company Slug)
```bash
POST /ahmed/api/auth/login
GET  /ahmed/api/auth/me
GET  /ahmed/api/modules/1/branches
GET  /ahmed/api/modules/1/branches/1/leads
POST /ahmed/api/modules/1/branches/1/leads
```

---

## Helper Functions

```php
// Generate company route
companyRoute('api/modules/1/branches');
// Returns: http://thruoo.local/ahmed/api/modules/1/branches

// Generate branch route
branchRoute(1, 1, 'leads');
// Returns: http://thruoo.local/ahmed/api/modules/1/branches/1/leads

// Validate slug format
validateSlug('ahmed-tech'); // true
validateSlug('Ahmed-Tech'); // false (uppercase not allowed)

// Get current company slug
currentCompanySlug(); // 'ahmed'

// Get current branch ID
currentBranchId(); // 1
```

---

## Testing Checklist

- [ ] Company registration creates slug
- [ ] Slug uniqueness is enforced
- [ ] Path-based login works (`/ahmed/api/auth/login`)
- [ ] Global login with slug works
- [ ] Branch-specific routes work
- [ ] Branch access control works
- [ ] Static assets load without company prefix
- [ ] Invalid slug returns 404
- [ ] User can only access their branches

---

## Rollback (If Needed)

```bash
# Revert key files
git checkout HEAD -- app/Multitenancy/TenantFinder/CompanyTenantFinder.php
git checkout HEAD -- routes/api.php
git checkout HEAD -- public/.htaccess

# Clear caches
php artisan cache:clear
php artisan route:clear
```

---

## Important Notes

1. **Backward Compatibility:** Old `company_id` parameter still works in `loginWithCompany`
2. **Subdomain Column:** Kept for backward compatibility
3. **Session Domain:** May need to update from `.thruoo.com` to `thruoo.com`
4. **Frontend Updates:** Frontend apps need to update API base URLs

---

## Next Steps

1. Run migrations
2. Test all scenarios
3. Update frontend applications
4. Monitor logs for errors
5. Update documentation

---

**Migration Status:** ✅ Ready for deployment
