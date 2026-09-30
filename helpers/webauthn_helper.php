<?php
/**
 * SiteWare — Construction Inventory Management System (CIMS)
 * Copyright (c) GB Construction & Enterprises Inc. & The MedYas.
 * All Rights Reserved.
 *
 * PROPRIETARY AND CONFIDENTIAL.
 * Module: WebAuthn / FIDO2 Passkeys Helper (Pure PHP Implementation)
 */

class WebAuthnHelper
{
    /**
     * Get the dynamic Relying Party ID based on current request host.
     * Supports localhost, ngrok tunnels, and live domains without manual config.
     */
    public static function getRpId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Strip port if present
        $host = explode(':', $host)[0];
        return strtolower($host);
    }

    /**
     * Get the application origin (protocol + host + port if any).
     */
    public static function getOrigin(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host;
    }

    /**
     * Generate a cryptographically secure random 32-byte challenge.
     */
    public static function generateChallenge(): string
    {
        return self::base64UrlEncode(random_bytes(32));
    }

    /**
     * Base64URL Encode (RFC 4648)
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL Decode (RFC 4648)
     */
    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Minimal RFC 8949 CBOR Decoder in Pure PHP
     */
    public static function decodeCbor(string $data, int &$offset = 0)
    {
        if ($offset >= strlen($data)) {
            throw new Exception('Unexpected end of CBOR data');
        }

        $byte = ord($data[$offset++]);
        $majorType = $byte >> 5;
        $info = $byte & 0x1F;

        // Determine value/length
        if ($info < 24) {
            $val = $info;
        } elseif ($info === 24) {
            $val = ord($data[$offset++]);
        } elseif ($info === 25) {
            $val = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($info === 26) {
            $val = unpack('N', substr($data, $offset, 4))[1];
            $offset += 4;
        } elseif ($info === 27) {
            $val = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        } else {
            throw new Exception("Unsupported CBOR additional info: {$info}");
        }

        switch ($majorType) {
            case 0: // Unsigned integer
                return $val;
            case 1: // Negative integer: -1 - val
                return -1 - $val;
            case 2: // Byte string
                $str = substr($data, $offset, $val);
                $offset += $val;
                return $str;
            case 3: // Text string
                $str = substr($data, $offset, $val);
                $offset += $val;
                return $str;
            case 4: // Array
                $arr = [];
                for ($i = 0; $i < $val; $i++) {
                    $arr[] = self::decodeCbor($data, $offset);
                }
                return $arr;
            case 5: // Map
                $map = [];
                for ($i = 0; $i < $val; $i++) {
                    $k = self::decodeCbor($data, $offset);
                    $v = self::decodeCbor($data, $offset);
                    $map[$k] = $v;
                }
                return $map;
            case 6: // Tag (skip tag, decode value)
                return self::decodeCbor($data, $offset);
            case 7: // Simple / float
                if ($val === 20) return false;
                if ($val === 21) return true;
                if ($val === 22) return null;
                return $val;
            default:
                throw new Exception("Unsupported CBOR major type: {$majorType}");
        }
    }

    /**
     * Parses Authenticator Data from attestationObject or assertion.
     * Returns:
     * [
     *   'rpIdHash' => string,
     *   'flags' => int,
     *   'signCount' => int,
     *   'credentialId' => ?string (Base64Url),
     *   'publicKeyPem' => ?string (PEM format)
     * ]
     */
    public static function parseAuthData(string $authData): array
    {
        if (strlen($authData) < 37) {
            throw new Exception('Invalid authData length');
        }

        $rpIdHash = substr($authData, 0, 32);
        $flags = ord($authData[32]);
        $signCount = unpack('N', substr($authData, 33, 4))[1];

        $hasAttestedData = ($flags & 0x40) !== 0;
        $credentialId = null;
        $publicKeyPem = null;

        if ($hasAttestedData && strlen($authData) > 55) {
            $offset = 37;
            // AAGUID (16 bytes)
            $aaguid = substr($authData, $offset, 16);
            $offset += 16;

            // Credential ID Length (2 bytes big endian)
            $credIdLen = unpack('n', substr($authData, $offset, 2))[1];
            $offset += 2;

            // Credential ID
            $rawCredId = substr($authData, $offset, $credIdLen);
            $credentialId = self::base64UrlEncode($rawCredId);
            $offset += $credIdLen;

            // Remainder is CBOR COSE Public Key
            $cborKey = substr($authData, $offset);
            $keyOffset = 0;
            $coseKey = self::decodeCbor($cborKey, $keyOffset);

            $publicKeyPem = self::coseKeyToPem($coseKey);
        }

        return [
            'rpIdHash' => $rpIdHash,
            'flags' => $flags,
            'signCount' => $signCount,
            'credentialId' => $credentialId,
            'publicKeyPem' => $publicKeyPem
        ];
    }

    /**
     * Convert COSE Key (CBOR map) to OpenSSL-usable PEM format.
     * Supports:
     * - ES256 (COSE alg -7: ECDSA with SHA-256 over P-256)
     * - RS256 (COSE alg -257: RSA with SHA-256)
     */
    public static function coseKeyToPem(array $cose): string
    {
        $kty = $cose[1] ?? null; // 2 = EC2, 3 = RSA
        $alg = $cose[3] ?? null; // -7 = ES256, -257 = RS256

        // Case 1: EC2 (P-256 / ES256)
        if ($kty === 2) {
            $crv = $cose[-1] ?? null; // 1 = P-256
            $x = $cose[-2] ?? null;
            $y = $cose[-3] ?? null;

            if ($crv !== 1 || strlen($x) !== 32 || strlen($y) !== 32) {
                throw new Exception('Unsupported EC curve or coordinates in COSE key');
            }

            // Uncompressed point: 0x04 || X || Y (65 bytes)
            $point = "\x04" . $x . $y;

            // ASN.1 DER header for P-256 public key (OID 1.2.840.10045.2.1 id-ecPublicKey, 1.2.840.10045.3.1.7 prime256v1)
            $derHeader = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
            $der = $derHeader . $point;

            return "-----BEGIN PUBLIC KEY-----\n" .
                chunk_split(base64_encode($der), 64, "\n") .
                "-----END PUBLIC KEY-----\n";
        }

        // Case 2: RSA (RS256)
        if ($kty === 3) {
            $n = $cose[-1] ?? null; // Modulus
            $e = $cose[-2] ?? null; // Exponent

            if (empty($n) || empty($e)) {
                throw new Exception('Missing RSA parameters in COSE key');
            }

            $encodeInt = function (string $bytes): string {
                // If high bit is set, prepend 0x00 for positive integer representation
                if ((ord($bytes[0]) & 0x80) !== 0) {
                    $bytes = "\x00" . $bytes;
                }
                $len = strlen($bytes);
                if ($len < 128) {
                    return "\x02" . chr($len) . $bytes;
                } elseif ($len < 256) {
                    return "\x02\x81" . chr($len) . $bytes;
                } else {
                    return "\x02\x82" . pack('n', $len) . $bytes;
                }
            };

            $rsaSeq = $encodeInt($n) . $encodeInt($e);
            $rsaSeqLen = strlen($rsaSeq);
            if ($rsaSeqLen < 128) {
                $rsaDer = "\x30" . chr($rsaSeqLen) . $rsaSeq;
            } elseif ($rsaSeqLen < 256) {
                $rsaDer = "\x30\x81" . chr($rsaSeqLen) . $rsaSeq;
            } else {
                $rsaDer = "\x30\x82" . pack('n', $rsaSeqLen) . $rsaSeq;
            }

            // Wrap in SubjectPublicKeyInfo with RSA OID 1.2.840.113549.1.1.1
            $bitString = "\x03" . (strlen($rsaDer) + 1 < 128 ? chr(strlen($rsaDer) + 1) : "\x82" . pack('n', strlen($rsaDer) + 1)) . "\x00" . $rsaDer;
            $spkiHeader = hex2bin('300d06092a864886f70d0101010500');
            $fullDer = $spkiHeader . $bitString;
            $totalLen = strlen($fullDer);
            $fullDer = "\x30" . ($totalLen < 128 ? chr($totalLen) : "\x82" . pack('n', $totalLen)) . $fullDer;

            return "-----BEGIN PUBLIC KEY-----\n" .
                chunk_split(base64_encode($fullDer), 64, "\n") .
                "-----END PUBLIC KEY-----\n";
        }

        throw new Exception("Unsupported COSE key algorithm or type: kty={$kty}, alg={$alg}");
    }

    /**
     * Verifies WebAuthn Assertion (Login) Signature.
     */
    public static function verifyAssertionSignature(
        string $publicKeyPem,
        string $authenticatorDataBin,
        string $clientDataJsonBin,
        string $signatureBin
    ): bool {
        // Data signed by authenticator: authenticatorData || SHA-256(clientDataJSON)
        $clientDataHash = hash('sha256', $clientDataJsonBin, true);
        $signedData = $authenticatorDataBin . $clientDataHash;

        // OpenSSL signature verification using SHA-256
        $verifyResult = openssl_verify($signedData, $signatureBin, $publicKeyPem, OPENSSL_ALGO_SHA256);
        return ($verifyResult === 1);
    }
}
