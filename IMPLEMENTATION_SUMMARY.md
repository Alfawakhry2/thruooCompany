# Implementation Summary: Multi-Company Multi-Tenant System

## Overview
This implementation enhances the Laravel multi-tenant system to support:
- **Subdomain generation from company name** (not from tenant registration)
- **Multiple companies per tenant (owner)**
- **Each company has its own subdomain and database**
- **Global login system** with company_id/subdomain + email + password
- **Company details table** for better management

---

## ✅ What Was Implemented

### 1. Subdomain Auto-Generation from Company Name
- **Location**: `app/Models/Landlord/Company.php` (boot method)
- **Format**: 
  - "Ahmed Tech" → "ahmed-tech"
  - "AHMED Compu" → "ahmed-compu"
  - Special characters removed, spaces converted to hyphens
- **Uniqueness**: Automatically ensures unique subdomains by appending numbers if needed

### 2. Company Details Table
- **Migration**: `database/migrations/landlord/2026_01_03_100000_create_company_details_table.php`
- **Model**: `app/Models/Landlord/CompanyDetails.php`
- **Purpose**: Stores additional company information:
  - Social media links (Facebook, Instagram, LinkedIn, etc.)
  - Financial information (annual revenue, currency)
  - Additional contact info (secondary email/phone, fax)
  - Business hours
  - Additional settings and metadata

### 3. Company Model Updates
- **Relationship**: Added `details()` relationship to `CompanyDetails`
- **Subdomain**: Auto-generated from company name in `boot()` method
- **Database**: Auto-generated from subdomain (format: `company_{subdomain}`)

### 4. Global Login System
- **Endpoint**: `POST /api/auth/login-with-company`
- **Body**: 
  ```json
  {
    "company_id": "uuid-or-subdomain",
    "email": "user@example.com",
    "password": "password"
  }
  ```
- **Flow**:
  1. Find company by ID or subdomain
  2. Switch to company database
  3. Validate email and password
  4. Generate token
  5. Return company URL for redirect

- **Alternative Endpoint**: `POST /api/auth/global-login`
  - Validates password across all companies
  - Returns list of valid companies
  - Auto-logs in if only one company found

### 5. Middleware Updates
- **ResolveTenant**: Updated to use `Company` model instead of `Tenant`
- **EnsureModuleEnabled**: Updated to use `Company` model
- **EnsureSubscriptionActive**: Updated to use `Company` model

### 6. Tenant Registration
- **Location**: `app/Http/Controllers/Modules/Sales/Api/TenantRegistrationController.php`
- **Flow**:
  1. Create Tenant (Owner)
  2. Create Company (subdomain auto-generated from company name)
  3. Create company database
  4. Run migrations
  5. Create admin user in company database

---

## 📋 Architecture

### Database Structure
```
Landlord Database:
├── tenants (owners/accounts)
│   └── Can have multiple companies
├── companies (actual tenants)
│   ├── subdomain (auto-generated from name)
│   ├── database (auto-generated from subdomain)
│   └── tenant_id (FK to tenants)
└── company_details
    └── company_id (FK to companies)

Company Databases (one per company):
└── All tenant-specific tables (users, leads, etc.)
```

### Tenant Resolution Flow
1. Request comes to subdomain (e.g., `ahmed-tech.thruoo.com`)
2. `CompanyTenantFinder` extracts subdomain from host
3. Finds company by subdomain in landlord database
4. `ResolveTenant` middleware makes company current
5. Database connection switches to company database
6. All subsequent queries use company database

### Login Flow
1. **Global Login** (Main Domain):
   - User provides: `company_id` (or subdomain) + `email` + `password`
   - System finds company → switches to company database → validates credentials
   - Returns token + redirect URL

2. **Subdomain Login** (Company Subdomain):
   - User accesses: `ahmed-tech.thruoo.com`
   - Company automatically resolved from subdomain
   - User provides: `email` + `password`
   - Validates in company database
   - Returns token

---

## 🔧 Files Modified/Created

### New Files
1. `database/migrations/landlord/2026_01_03_100000_create_company_details_table.php`
2. `app/Models/Landlord/CompanyDetails.php`

### Modified Files
1. `app/Models/Landlord/Company.php` - Added `details()` relationship
2. `app/Http/Controllers/Api/GlobalAuthController.php` - Enhanced login methods
3. `app/Http/Middleware/ResolveTenant.php` - Updated to use Company model
4. `app/Http/Middleware/EnsureModuleEnabled.php` - Updated to use Company model
5. `app/Http/Middleware/EnsureSubscriptionActive.php` - Updated to use Company model
6. `app/Http/Controllers/Modules/Sales/Api/TenantRegistrationController.php` - Updated comments
7. `routes/api.php` - Added global-login route

---

## 🧪 Testing Guide

### 1. Register a New Tenant + Company
```bash
POST /api/registration/register
Content-Type: application/json

{
  "owner": {
    "name": "Ahmed Ali",
    "email": "ahmed@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "+1234567890"
  },
  "company": {
    "name": "Ahmed Tech Solutions",
    "business_email": "info@ahmedtech.com",
    "phone": "+1234567890",
    "industry": "Technology",
    "country": "USA",
    "city": "New York"
  }
}
```

**Expected Result**:
- Company created with subdomain: `ahmed-tech-solutions`
- Database created: `company_ahmed_tech_solutions`
- Admin user created in company database
- Token returned for immediate login

### 2. Global Login (Main Domain)
```bash
POST /api/auth/login-with-company
Content-Type: application/json

{
  "company_id": "ahmed-tech-solutions",  // or UUID
  "email": "ahmed@example.com",
  "password": "password123"
}
```

**Expected Result**:
- Company found and database switched
- User validated
- Token returned
- Redirect URL: `https://ahmed-tech-solutions.thruoo.com`

### 3. Login via Subdomain
```bash
# Access: https://ahmed-tech-solutions.thruoo.com/api/auth/login
POST /api/auth/login
Content-Type: application/json

{
  "email": "ahmed@example.com",
  "password": "password123"
}
```

**Expected Result**:
- Company automatically resolved from subdomain
- User validated in company database
- Token returned

### 4. Get Companies by Email
```bash
POST /api/auth/companies-by-email
Content-Type: application/json

{
  "email": "user@example.com"
}
```

**Expected Result**:
- List of all companies where user exists
- No password validation (for selection UI)

### 5. Global Login (Auto-select)
```bash
POST /api/auth/global-login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

**Expected Result**:
- If user exists in only one company → Auto-login
- If user exists in multiple companies → Return list for selection

---

## 🔑 Key Features

### 1. Subdomain Generation Rules
- Converts to lowercase
- Removes special characters
- Replaces spaces with hyphens
- Ensures uniqueness
- Examples:
  - "Ahmed Tech" → "ahmed-tech"
  - "AHMED Compu" → "ahmed-compu"
  - "Tech@Solutions!" → "tech-solutions"

### 2. Multi-Company Support
- One tenant (owner) can have multiple companies
- Each company has its own:
  - Subdomain
  - Database
  - Users
  - Data

### 3. Global Login Options
- **Option 1**: Direct login with company_id/subdomain
- **Option 2**: Email lookup → Select company → Login
- **Option 3**: Auto-login if only one company

### 4. Spatie Multitenancy Integration
- Uses `CompanyTenantFinder` to resolve tenant from subdomain
- Properly switches database connections
- Works with Sanctum authentication
- Supports queue jobs with tenant awareness

---

## 📝 Important Notes

1. **Subdomain is NOT removed from companies table** - It's stored and used for resolution
2. **Domain format**: `{subdomain}.{app_url}` (e.g., `ahmed-tech.thruoo.com`)
3. **Database naming**: `company_{subdomain}` (e.g., `company_ahmed_tech`)
4. **Company is the actual tenant** - Tenant model is just the owner/account
5. **All middleware updated** to use Company model instead of Tenant

---

## 🚀 Next Steps

1. Run migration: `php artisan migrate`
2. Test registration flow
3. Test global login
4. Test subdomain resolution
5. Verify company details can be managed
6. Test multi-company scenarios

---

## 📚 API Endpoints Summary

### Global Routes (Main Domain)
- `POST /api/auth/companies-by-email` - Get companies by email
- `POST /api/auth/global-login` - Global login with auto-select
- `POST /api/auth/login-with-company` - Login with company_id
- `POST /api/auth/company-login` - Alias for login-with-company
- `POST /api/registration/register` - Register tenant + company

### Tenant Routes (Company Subdomain)
- `POST /api/auth/login` - Login on subdomain
- `GET /api/auth/me` - Get current user
- `POST /api/auth/logout` - Logout
- All other tenant-specific routes...

---

## ✅ Implementation Complete

All requirements have been implemented:
- ✅ Subdomain generated from company name
- ✅ Subdomain stored in companies table
- ✅ Each tenant can have multiple companies
- ✅ Each company has its own subdomain and database
- ✅ Company details table created
- ✅ Global login with company_id/subdomain + email + password
- ✅ Proper tenant resolution via subdomain
- ✅ Spatie multitenancy rules followed

