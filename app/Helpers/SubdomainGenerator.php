<?php

namespace App\Helpers;

use App\Models\Landlord\Tenant;
use Illuminate\Support\Str;

class SubdomainGenerator
{
    /**
     * Reserved subdomains that cannot be used
     */
    protected static array $reservedSubdomains = [
        'www',
        'app',
        'api',
        'admin',
        'dashboard',
        'panel',
        'login',
        'register',
        'auth',
        'mail',
        'email',
        'smtp',
        'ftp',
        'sftp',
        'cdn',
        'static',
        'assets',
        'images',
        'img',
        'media',
        'files',
        'docs',
        'help',
        'support',
        'status',
        'blog',
        'news',
        'shop',
        'store',
        'billing',
        'payment',
        'payments',
        'checkout',
        'cart',
        'account',
        'accounts',
        'profile',
        'settings',
        'config',
        'test',
        'demo',
        'staging',
        'dev',
        'development',
        'prod',
        'production',
        'beta',
        'alpha',
        'preview',
        'sandbox',
        'localhost',
        'local',
        'internal',
        'private',
        'public',
        'secure',
        'ssl',
        'vpn',
        'git',
        'gitlab',
        'github',
        'bitbucket',
        'jenkins',
        'ci',
        'cd',
        'deploy',
        'kubernetes',
        'k8s',
        'docker',
        'redis',
        'mysql',
        'postgres',
        'mongodb',
        'db',
        'database',
        'cache',
        'queue',
        'worker',
        'cron',
        'job',
        'jobs',
        'webhook',
        'webhooks',
        'callback',
        'oauth',
        'sso',
        'saml',
        'ldap',
        'thruoo',
        'crm',
        'sales',
        'contacts',
        'accounting',
        'inventory',
        'hr',
        'erp',
        'system',
        'root',
        'null',
        'undefined',
        'admin1',
        'administrator',
        'superadmin',
        'moderator',
        'mod',
        'owner',
        'master',
        'info',
        'contact',
        'about',
        'privacy',
        'terms',
        'legal',
        'security',
        'abuse',
        'spam',
        'postmaster',
        'hostmaster',
        'webmaster',
        'ns1',
        'ns2',
        'ns3',
        'mx',
        'mx1',
        'mx2',
    ];

    /**
     * Generate a subdomain from company name
     */
    public static function generate(string $companyName): string
    {
        // Clean and prepare the company name
        $subdomain = self::sanitize($companyName);

        // Check if it's reserved or already exists
        if (self::isReserved($subdomain) || self::exists($subdomain)) {
            $subdomain = self::makeUnique($subdomain);
        }

        return $subdomain;
    }

    /**
     * Sanitize company name into valid subdomain
     */
    public static function sanitize(string $name): string
    {
        // Convert to lowercase
        $subdomain = Str::lower($name);

        // Replace Arabic/special characters with Latin equivalents where possible
        $subdomain = self::transliterate($subdomain);

        // Replace spaces and special characters with hyphens
        $subdomain = preg_replace('/[^a-z0-9]+/', '-', $subdomain);

        // Remove leading/trailing hyphens
        $subdomain = trim($subdomain, '-');

        // Remove consecutive hyphens
        $subdomain = preg_replace('/-+/', '-', $subdomain);

        // Limit length (max 63 characters for subdomain)
        $subdomain = Str::limit($subdomain, 50, '');

        // Ensure it doesn't end with hyphen after limiting
        $subdomain = rtrim($subdomain, '-');

        // If empty after sanitization, generate a random one
        if (empty($subdomain)) {
            $subdomain = 'company-' . Str::random(8);
        }

        return $subdomain;
    }

    /**
     * Transliterate non-ASCII characters
     */
    protected static function transliterate(string $string): string
    {
        // Common transliterations
        $transliterations = [
            'أ' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j',
            'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r',
            'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd',
            'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f',
            'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
            'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'a',
            'ا' => 'a', 'إ' => 'i', 'آ' => 'a', 'ء' => '',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];

        return strtr($string, $transliterations);
    }

    /**
     * Check if subdomain is reserved
     */
    public static function isReserved(string $subdomain): bool
    {
        return in_array(Str::lower($subdomain), self::$reservedSubdomains);
    }

    /**
     * Check if subdomain already exists
     */
    public static function exists(string $subdomain): bool
    {
        return Tenant::on('mysql')
            ->where('subdomain', $subdomain)
            ->exists();
    }

    /**
     * Check if subdomain is available
     */
    public static function isAvailable(string $subdomain): bool
    {
        $sanitized = self::sanitize($subdomain);
        return !self::isReserved($sanitized) && !self::exists($sanitized);
    }

    /**
     * Make subdomain unique by appending numbers
     */
    protected static function makeUnique(string $subdomain): string
    {
        $original = $subdomain;
        $counter = 1;

        // Limit the original part to leave room for counter
        $maxOriginalLength = 45;
        if (strlen($original) > $maxOriginalLength) {
            $original = substr($original, 0, $maxOriginalLength);
        }

        while (self::isReserved($subdomain) || self::exists($subdomain)) {
            $subdomain = $original . '-' . $counter;
            $counter++;

            // Safety limit
            if ($counter > 1000) {
                $subdomain = $original . '-' . Str::random(8);
                break;
            }
        }

        return $subdomain;
    }

    /**
     * Get suggestions for a company name
     */
    public static function getSuggestions(string $companyName, int $count = 3): array
    {
        $suggestions = [];
        $base = self::sanitize($companyName);

        // First suggestion: direct sanitization
        if (self::isAvailable($base)) {
            $suggestions[] = $base;
        }

        // Generate additional suggestions
        $suffixes = ['app', 'hub', 'pro', 'hq', 'team', 'co'];

        foreach ($suffixes as $suffix) {
            if (count($suggestions) >= $count) {
                break;
            }

            $suggestion = $base . '-' . $suffix;
            if (self::isAvailable($suggestion)) {
                $suggestions[] = $suggestion;
            }
        }

        // If still not enough, add numbered suggestions
        $counter = 1;
        while (count($suggestions) < $count && $counter <= 10) {
            $suggestion = $base . '-' . $counter;
            if (self::isAvailable($suggestion)) {
                $suggestions[] = $suggestion;
            }
            $counter++;
        }

        return array_slice($suggestions, 0, $count);
    }

    /**
     * Validate subdomain format
     */
    public static function isValidFormat(string $subdomain): bool
    {
        // Must be 3-63 characters
        if (strlen($subdomain) < 3 || strlen($subdomain) > 63) {
            return false;
        }

        // Must contain only lowercase letters, numbers, and hyphens
        if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $subdomain)) {
            return false;
        }

        // Must not have consecutive hyphens
        if (strpos($subdomain, '--') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Get validation error message
     */
    public static function getValidationError(string $subdomain): ?string
    {
        if (strlen($subdomain) < 3) {
            return 'Subdomain must be at least 3 characters long';
        }

        if (strlen($subdomain) > 63) {
            return 'Subdomain must not exceed 63 characters';
        }

        if (!preg_match('/^[a-z0-9]/', $subdomain)) {
            return 'Subdomain must start with a letter or number';
        }

        if (!preg_match('/[a-z0-9]$/', $subdomain)) {
            return 'Subdomain must end with a letter or number';
        }

        if (!preg_match('/^[a-z0-9-]+$/', $subdomain)) {
            return 'Subdomain can only contain lowercase letters, numbers, and hyphens';
        }

        if (strpos($subdomain, '--') !== false) {
            return 'Subdomain cannot contain consecutive hyphens';
        }

        if (self::isReserved($subdomain)) {
            return 'This subdomain is reserved and cannot be used';
        }

        if (self::exists($subdomain)) {
            return 'This subdomain is already taken';
        }

        return null;
    }
}
