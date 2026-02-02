@echo off
REM Quick API Test Script for Windows

set BASE_URL=http://thruoo.local:8000
set TENANT_URL=http://acme.thruoo.local:8000

echo ========================================
echo Thruoo CRM API Testing Script
echo ========================================
echo.

echo [1/4] Registering tenant...
curl -X POST %BASE_URL%/api/tenants/register ^
  -H "Content-Type: application/json" ^
  -d "{\"company_name\":\"Test Corp\",\"subdomain\":\"acme\",\"email\":\"admin@acme.com\",\"password\":\"password123\",\"password_confirmation\":\"password123\",\"name\":\"Test Admin\",\"modules\":[\"sales\"]}"

echo.
echo.
echo [2/4] Logging in...
curl -X POST %TENANT_URL%/api/auth/login ^
  -H "Content-Type: application/json" ^
  -d "{\"email\":\"admin@acme.com\",\"password\":\"password123\"}"

echo.
echo.
echo [3/4] Please copy the token from above and set it in the next command
echo Example: set TOKEN=1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
echo.
pause

set /p TOKEN="Enter your token: "

echo.
echo [4/4] Creating a test lead...
curl -X POST %TENANT_URL%/api/sales/leads ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json" ^
  -d "{\"name\":\"Test Lead\",\"email\":\"lead@test.com\",\"status\":\"new\",\"value\":1000.00}"

echo.
echo.
echo [5/5] Listing leads...
curl -X GET %TENANT_URL%/api/sales/leads ^
  -H "Authorization: Bearer %TOKEN%"

echo.
echo.
echo ========================================
echo Testing Complete!
echo ========================================
pause

