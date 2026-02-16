<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;

class VoucherCipherService
{
    private string $key;

    private string $cipher = 'AES-256-CBC';

    public function __construct()
    {
        $voucherEncryptionPublicKey = config('services.voucher.encryption_key');

        $this->key = base64_decode($voucherEncryptionPublicKey);

        if (strlen($this->key) !== 32) {
            throw new \RuntimeException(
                'VOUCHER_ENCRYPTION_KEY must be 32 bytes (base64 encoded 44 characters). Generate using: openssl rand -base64 32'
            );
        }
    }

    /**
     * Encrypt a voucher code using AES-256-CBC
     * Format: base64(iv:encrypted_data:hmac)
     *
     * @param  string  $code  The plain voucher code to encrypt
     * @return string The encrypted voucher code
     */
    public function encryptCode(string $code): string
    {

        $ivLength = openssl_cipher_iv_length($this->cipher);
        if ($ivLength === false) {
            throw new \RuntimeException('Unable to determine IV length for cipher '.$this->cipher);
        }
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt(
            $code,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Create HMAC for integrity verification
        $hmac = hash_hmac('sha256', $iv.$encrypted, $this->key, true);

        // Combine IV, encrypted data, and HMAC
        $combined = $iv.$encrypted.$hmac;

        // Return base64 encoded result
        return base64_encode($combined);
    }

    /**
     * Decrypt a voucher code
     *
     * @param  string  $encryptedCode  The encrypted voucher code
     * @return string The decrypted plain voucher code
     *
     * @throws DecryptException
     */
    public function decryptCode(string $encryptedCode): string
    {
        try {
            // Decode from base64
            $combined = base64_decode($encryptedCode, true);

            if ($combined === false) {
                throw new DecryptException('Base64 decoding failed');
            }

            // Extract IV length
            $ivLength = openssl_cipher_iv_length($this->cipher);
            if ($ivLength === false) {
                throw new DecryptException('Unable to determine IV length for cipher '.$this->cipher);
            }
            $hmacLength = 32; // SHA256 produces 32 bytes

            // Validate minimum length
            if (strlen($combined) < $ivLength + $hmacLength) {
                throw new DecryptException('Invalid encrypted data format');
            }

            // Extract components
            $iv = substr($combined, 0, $ivLength);
            $hmac = substr($combined, -$hmacLength);
            $encrypted = substr($combined, $ivLength, -$hmacLength);

            // Verify HMAC for integrity
            $calculatedHmac = hash_hmac('sha256', $iv.$encrypted, $this->key, true);

            if (! hash_equals($hmac, $calculatedHmac)) {
                throw new DecryptException('HMAC verification failed');
            }

            // Decrypt the data
            $decrypted = openssl_decrypt(
                $encrypted,
                $this->cipher,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                throw new DecryptException('Decryption failed');
            }

            return $decrypted;
        } catch (\Exception $e) {
            throw new DecryptException('Decryption failed: '.$e->getMessage());
        }
    }

    /**
     * Encrypt a pin code
     *
     * @param  string|null  $pinCode  The plain pin code to encrypt
     * @return string|null The encrypted pin code or null if input is null
     */
    public function encryptPinCode(?string $pinCode): ?string
    {
        if ($pinCode === null) {
            return null;
        }

        return $this->encryptCode($pinCode);
    }

    /**
     * Decrypt a pin code
     *
     * @param  string|null  $encryptedPinCode  The encrypted pin code
     * @return string|null The decrypted plain pin code or null if input is null
     *
     * @throws DecryptException
     */
    public function decryptPinCode(?string $encryptedPinCode): ?string
    {
        if ($encryptedPinCode === null) {
            return null;
        }

        return $this->decryptCode($encryptedPinCode);
    }

    /**
     * Safely decrypt a code, returning null on failure instead of throwing exception
     *
     * @param  string  $encryptedCode  The encrypted code
     * @return string|null The decrypted code or null if decryption fails
     */
    public function safeDecrypt(string $encryptedCode): ?string
    {
        try {
            return $this->decryptCode($encryptedCode);
        } catch (DecryptException $e) {
            return null;
        }
    }

    /**
     * Check if a code is encrypted (basic validation)
     *
     * @param  string  $code  The code to check
     * @return bool True if the code appears to be encrypted
     */
    public function isEncrypted(string $code): bool
    {
        try {
            $this->decryptCode($code);

            return true;
        } catch (DecryptException $e) {
            return false;
        }
    }

    /**
     * Encrypt multiple voucher codes in batch
     *
     * @param  array<string>  $codes  Array of plain voucher codes
     * @return array<string> Array of encrypted voucher codes
     */
    public function encryptBatch(array $codes): array
    {
        return array_map(fn ($code) => $this->encryptCode($code), $codes);
    }

    /**
     * Decrypt multiple voucher codes in batch
     *
     * @param  array<string>  $encryptedCodes  Array of encrypted voucher codes
     * @return array<string> Array of decrypted voucher codes
     *
     * @throws DecryptException
     */
    public function decryptBatch(array $encryptedCodes): array
    {
        return array_map(fn ($code) => $this->decryptCode($code), $encryptedCodes);
    }
}
