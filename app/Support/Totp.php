<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Time-based one-time codes (RFC 6238), the kind Google Authenticator, Microsoft
 * Authenticator, Authy and 1Password produce: 6 digits, a new one every 30 seconds.
 */
class Totp
{
    protected const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const PERIOD = 30;

    /** A new random secret, as the 32-character key shown under the QR code. */
    public static function generateSecret(): string
    {
        $secret = '';
        foreach (str_split(random_bytes(32)) as $byte) {
            $secret .= self::ALPHABET[ord($byte) % 32];
        }

        return $secret;
    }

    public static function codeAt(string $secret, int $timestamp): string
    {
        $key = self::base32Decode($secret);
        $counter = pack('N*', 0, intdiv($timestamp, self::PERIOD));
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Accepts the current code and the one either side, so a phone clock that is slightly off still works. */
    public static function verify(string $secret, ?string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', (string) $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $timestamp ??= now()->getTimestamp();
        foreach ([-1, 0, 1] as $step) {
            if (hash_equals(self::codeAt($secret, $timestamp + $step * self::PERIOD), $code)) {
                return true;
            }
        }

        return false;
    }

    /** What the QR code contains: the account name, the secret and who issued it. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period='.self::PERIOD;
    }

    public static function qrSvg(string $uri, int $size = 200): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($uri);

        return trim(substr($svg, strpos($svg, '<svg')));
    }

    /** @return string[] eight one-time codes such as "k7dm-2xqp" */
    public static function recoveryCodes(): array
    {
        return array_map(fn () => strtolower(substr(self::generateSecret(), 0, 4).'-'.substr(self::generateSecret(), 0, 4)), range(1, 8));
    }

    protected static function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret))) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
