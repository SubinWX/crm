<?php

namespace App\Support;

/**
 * Guards the addresses the CRM itself calls (a custom WhatsApp or SMS provider typed into Settings).
 * Such an address must be a public https one: never this server, its private network or a cloud
 * metadata address, whether written as a name, an IPv4/IPv6 address or a name that resolves to one.
 */
class OutboundUrl
{
    public static function isPublic(?string $url): bool
    {
        $parts = parse_url((string) $url);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || blank($parts['host'] ?? null)) {
            return false;
        }
        // parse_url keeps the brackets of an IPv6 address.
        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::publicIp($host);
        }
        // Plain numbers ("2130706433", "0x7f000001") are other spellings of an IP address.
        if (preg_match('/^(0x[0-9a-f]+|\d+)$/', $host)) {
            return false;
        }
        // A name that does not resolve cannot be reached anyway; one that does must point only at public addresses.
        // (The lookup is switched off in automated tests, which use made-up host names.)
        $addresses = config('services.outbound_dns_check', true) ? (@gethostbynamel($host) ?: []) : [];
        foreach ($addresses as $address) {
            if (! self::publicIp($address)) {
                return false;
            }
        }

        return true;
    }

    protected static function publicIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
