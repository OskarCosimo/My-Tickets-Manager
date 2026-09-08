<?php
// includes/totp_helper.php
// Pure PHP TOTP (RFC 6238) Engine for 2FA Authentication

/**
 * Generate a random Base32 secret key
 */
function generate_totp_secret(int $length = 16): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

/**
 * Decode Base32 string to binary
 */
function base32_decode(string $b32): string {
    $b32 = strtoupper($b32);
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buf = 0;
    $bufSize = 0;
    $res = '';

    for ($i = 0; $i < strlen($b32); $i++) {
        $ch = $b32[$i];
        $pos = strpos($chars, $ch);
        if ($pos === false) continue;

        $buf = ($buf << 5) | $pos;
        $bufSize += 5;

        if ($bufSize >= 8) {
            $bufSize -= 8;
            $res .= chr(($buf >> $bufSize) & 0xFF);
        }
    }

    return $res;
}

/**
 * Verify a 6-digit TOTP code with time-window tolerance
 */
function verify_totp_code(string $secret, string $code, int $discrepancy = 1): bool {
    $code = trim($code);
    if (strlen($code) !== 6 || !is_numeric($code)) {
        return false;
    }

    $currentTimeSlice = floor(time() / 30);

    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $calculatedCode = get_totp_code($secret, $currentTimeSlice + $i);
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }

    return false;
}

/**
 * Calculate TOTP code for a given time slice
 */
function get_totp_code(string $secret, int $timeSlice): string {
    $secretKey = base32_decode($secret);

    // Pack time into binary string (64-bit Big-Endian)
    $time = pack('N*', 0) . pack('N*', $timeSlice);

    // HMAC-SHA1
    $hmac = hash_hmac('sha1', $time, $secretKey, true);

    // Dynamic truncation
    $offset = ord(substr($hmac, -1)) & 0x0F;
    $hashpart = substr($hmac, $offset, 4);

    $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;

    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Generate otpauth:// URI for QR Code generation
 */
function get_totp_qr_url(string $label, string $issuer, string $secret): string {
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($label) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
}