<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Services\VoucherCipherService;
use Illuminate\Contracts\Encryption\DecryptException;

class VoucherCipherServiceTest extends TestCase
{
    private VoucherCipherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VoucherCipherService::class);
    }

    public function test_can_encrypt_voucher_code(): void
    {
        $plainCode = 'VOUCHER123456';
        $encryptedCode = $this->service->encryptCode($plainCode);

        $this->assertNotEquals($plainCode, $encryptedCode);
        $this->assertIsString($encryptedCode);
        $this->assertNotEmpty($encryptedCode);
    }

    public function test_can_decrypt_voucher_code(): void
    {
        $plainCode = 'VOUCHER123456';
        $encryptedCode = $this->service->encryptCode($plainCode);
        $decryptedCode = $this->service->decryptCode($encryptedCode);

        $this->assertEquals($plainCode, $decryptedCode);
    }

    public function test_encrypt_and_decrypt_preserves_original_value(): void
    {
        $originalCode = 'TEST-VOUCHER-2024';
        $encrypted = $this->service->encryptCode($originalCode);
        $decrypted = $this->service->decryptCode($encrypted);

        $this->assertEquals($originalCode, $decrypted);
    }

    public function test_can_encrypt_pin_code(): void
    {
        $plainPin = '1234';
        $encryptedPin = $this->service->encryptPinCode($plainPin);

        $this->assertNotEquals($plainPin, $encryptedPin);
        $this->assertIsString($encryptedPin);
    }

    public function test_can_decrypt_pin_code(): void
    {
        $plainPin = '5678';
        $encryptedPin = $this->service->encryptPinCode($plainPin);
        $decryptedPin = $this->service->decryptPinCode($encryptedPin);

        $this->assertEquals($plainPin, $decryptedPin);
    }

    public function test_encrypt_pin_code_returns_null_for_null_input(): void
    {
        $result = $this->service->encryptPinCode(null);

        $this->assertNull($result);
    }

    public function test_decrypt_pin_code_returns_null_for_null_input(): void
    {
        $result = $this->service->decryptPinCode(null);

        $this->assertNull($result);
    }

    public function test_decrypt_throws_exception_for_invalid_encrypted_string(): void
    {
        $this->expectException(DecryptException::class);

        $this->service->decryptCode('invalid-encrypted-string');
    }

    public function test_safe_decrypt_returns_null_for_invalid_encrypted_string(): void
    {
        $result = $this->service->safeDecrypt('invalid-encrypted-string');

        $this->assertNull($result);
    }

    public function test_safe_decrypt_returns_decrypted_value_for_valid_encrypted_string(): void
    {
        $plainCode = 'SAFE-DECRYPT-TEST';
        $encryptedCode = $this->service->encryptCode($plainCode);
        $decryptedCode = $this->service->safeDecrypt($encryptedCode);

        $this->assertEquals($plainCode, $decryptedCode);
    }

    public function test_is_encrypted_returns_true_for_encrypted_string(): void
    {
        $plainCode = 'CHECK-ENCRYPTION';
        $encryptedCode = $this->service->encryptCode($plainCode);

        $this->assertTrue($this->service->isEncrypted($encryptedCode));
    }

    public function test_is_encrypted_returns_false_for_plain_string(): void
    {
        $plainCode = 'PLAIN-TEXT-CODE';

        $this->assertFalse($this->service->isEncrypted($plainCode));
    }

    public function test_can_encrypt_batch_of_codes(): void
    {
        $plainCodes = ['CODE1', 'CODE2', 'CODE3'];
        $encryptedCodes = $this->service->encryptBatch($plainCodes);

        $this->assertCount(3, $encryptedCodes);
        foreach ($encryptedCodes as $index => $encrypted) {
            $this->assertNotEquals($plainCodes[$index], $encrypted);
        }
    }

    public function test_can_decrypt_batch_of_codes(): void
    {
        $plainCodes = ['CODE1', 'CODE2', 'CODE3'];
        $encryptedCodes = $this->service->encryptBatch($plainCodes);
        $decryptedCodes = $this->service->decryptBatch($encryptedCodes);

        $this->assertEquals($plainCodes, $decryptedCodes);
    }

    public function test_batch_encrypt_and_decrypt_preserves_order(): void
    {
        $originalCodes = ['FIRST', 'SECOND', 'THIRD', 'FOURTH'];
        $encrypted = $this->service->encryptBatch($originalCodes);
        $decrypted = $this->service->decryptBatch($encrypted);

        $this->assertEquals($originalCodes, $decrypted);
        $this->assertEquals('FIRST', $decrypted[0]);
        $this->assertEquals('FOURTH', $decrypted[3]);
    }

    public function test_different_codes_produce_different_encrypted_values(): void
    {
        $code1 = 'CODE-A';
        $code2 = 'CODE-B';

        $encrypted1 = $this->service->encryptCode($code1);
        $encrypted2 = $this->service->encryptCode($code2);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function test_same_code_produces_different_encrypted_values_each_time(): void
    {
        $code = 'SAME-CODE';

        $encrypted1 = $this->service->encryptCode($code);
        $encrypted2 = $this->service->encryptCode($code);

        // Laravel's encryption adds randomness, so same input produces different encrypted output
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same value
        $this->assertEquals($code, $this->service->decryptCode($encrypted1));
        $this->assertEquals($code, $this->service->decryptCode($encrypted2));
    }

    public function test_handles_special_characters_in_code(): void
    {
        $specialCode = 'CODE!@#$%^&*()_+-={}[]|:";\'<>?,./';
        $encrypted = $this->service->encryptCode($specialCode);
        $decrypted = $this->service->decryptCode($encrypted);

        $this->assertEquals($specialCode, $decrypted);
    }

    public function test_handles_unicode_characters_in_code(): void
    {
        $unicodeCode = '코드-123-مرحبا-🎉';
        $encrypted = $this->service->encryptCode($unicodeCode);
        $decrypted = $this->service->decryptCode($encrypted);

        $this->assertEquals($unicodeCode, $decrypted);
    }

    public function test_handles_empty_string(): void
    {
        $emptyCode = '';
        $encrypted = $this->service->encryptCode($emptyCode);
        $decrypted = $this->service->decryptCode($encrypted);

        $this->assertEquals($emptyCode, $decrypted);
    }

    public function test_handles_long_codes(): void
    {
        $longCode = str_repeat('ABCDEF123456', 100); // 1200 characters
        $encrypted = $this->service->encryptCode($longCode);
        $decrypted = $this->service->decryptCode($encrypted);

        $this->assertEquals($longCode, $decrypted);
    }
}
