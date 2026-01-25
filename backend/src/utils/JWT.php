<?php
/**
 * StudyWS Backend - JWT Token Handler
 *
 * - Create and verify JWT tokens for authentication:
 *   - Access tokens (short-lived) for API authorization
 *   - Refresh tokens (long-lived) for session renewal
 *
 * Token claims used:
 * - sub: user id (subject)
 * - email: user email
 * - type: "access" or "refresh"
 * - iat: issued-at timestamp (epoch seconds)
 * - exp: expiry timestamp (epoch seconds)
 * - jti: unique token id (refresh tokens only)
 */

class JWT
{
    /**
     * Create an access token (short-lived).
     *
     * @param int $userId User id.
     * @param string $email User email.
     * @param int|null $expirySeconds override (defaults to JWT_ACCESS_EXPIRY).
     *
     * @return string Signed JWT string.
     */
    public static function createAccessToken(int $userId, string $email, ?int $expirySeconds = null): string
    {
        $expirySeconds = $expirySeconds ?? JWT_ACCESS_EXPIRY;

        $iat = time();               
        $exp = $iat + $expirySeconds; // expires at

        // Access token payload
        $payload = [
            'sub' => $userId,
            'email' => $email,
            'type' => 'access',
            'iat' => $iat,
            'exp' => $exp,
        ];

        return self::encode($payload);
    }

    /**
     * Create a refresh token (long-lived).
     *
     * @param int $userId User id.
     * @param string $email User email.
     * @param int|null $expirySeconds override (defaults to JWT_REFRESH_EXPIRY).
     *
     * @return string Signed JWT string.
     */
    public static function createRefreshToken(int $userId, string $email, ?int $expirySeconds = null): string
    {
        $expirySeconds = $expirySeconds ?? JWT_REFRESH_EXPIRY;

        $iat = time();
        $exp = $iat + $expirySeconds; // expires at

        // Refresh token payload
        $payload = [
            'sub' => $userId,
            'email' => $email,
            'type' => 'refresh',
            'iat' => $iat,
            'exp' => $exp,
            'jti' => bin2hex(random_bytes(16)), // unique token id 
        ];

        return self::encode($payload);
    }

    /**
     * Verify and decode a JWT.
     *
     * Validation performed:
     * - Token must have 3 parts (header.payload.signature).
     * - Signature must match (HS256 with JWT_SECRET).
     * - Token must not be expired (exp > now).
     *
     * @param string $token Raw JWT string.
     *
     * @return array|null Decoded payload as associative array, or null if invalid/expired.
     */
    public static function verify(string $token): ?array
    {
        try {
            // Split into parts
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            // Extract parts
            [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

            // Decode payload JSON (throws on invalid JSON).
            $payload = json_decode(
                self::base64UrlDecode($payloadEncoded),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // Recompute signature for "<header>.<payload>".
            $message = $headerEncoded . '.' . $payloadEncoded;

            // Expected signature
            $expectedSignature = self::base64UrlEncode(
                hash_hmac('sha256', $message, JWT_SECRET, true) // raw binary output
            );

            // Constant-time compare to avoid timing attacks.
            if (!hash_equals($expectedSignature, $signatureEncoded)) {
                return null;
            }

            // Expiry check.
            if (!isset($payload['exp']) || $payload['exp'] < time()) {
                return null;
            }

            return $payload;
        } 
        catch (\Throwable $e) {
            error_log('JWT verification error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Encode a payload into a signed JWT (HS256).
     *
     * @param array $payload Associative array of claims.
     *
     * @return string JWT string.
     */
    private static function encode(array $payload): string
    {
        $header = [
            'alg' => JWT_ALGORITHM, // expected "HS256"
            'typ' => 'JWT',
        ];

        // base64url(header).base64url(payload)
        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $message = $headerEncoded . '.' . $payloadEncoded;

        // signature = HMAC-SHA256(message, secret)
        $signature = hash_hmac('sha256', $message, JWT_SECRET, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return $message . '.' . $signatureEncoded;
    }

    /**
     * Base64URL encode (RFC 7515 style):
     * - Standard base64, then replace +/ with -_, and remove trailing "=" padding.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode:
     * - Restore "=" padding to multiple of 4, then standard base64 decode.
     */
    private static function base64UrlDecode(string $data): string
    {
        $data .= str_repeat('=', (4 - strlen($data) % 4) % 4);
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
