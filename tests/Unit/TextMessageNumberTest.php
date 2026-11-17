<?php

namespace Tests\Unit;

use App\Services\TextMessageNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TextMessageNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_supported_numbers_normalize_to_e164(string $input, string $number): void
    {
        $result = (new TextMessageNumber)->parse($input);

        $this->assertTrue($result['valid']);
        $this->assertSame($number, $result['number']);
        $this->assertSame('sms:'.$number, $result['url']);
    }

    public static function validNumbers(): array
    {
        return [
            'local zero prefix' => ['09123456789', '+639123456789'],
            'local nine prefix' => ['9123456789', '+639123456789'],
            'plus country code' => ['+63 912 345 6789', '+639123456789'],
            'country code without plus' => ['639123456789', '+639123456789'],
            'punctuated local number' => ['(0912) 345-6789', '+639123456789'],
            'international number' => ['+1 (202) 555-0123', '+12025550123'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_invalid_numbers_are_rejected(string $input): void
    {
        $this->assertFalse((new TextMessageNumber)->parse($input)['valid']);
    }

    public static function invalidNumbers(): array
    {
        return [
            'letters' => ['09CALLME123'],
            'too short' => ['091234567'],
            'too long' => ['091234567890'],
            'bad country code' => ['+6391234567'],
            'bad international' => ['+0123456789'],
            'multiple plus' => ['++639123456789'],
            'script text' => ['<script>alert(1)</script>'],
        ];
    }
}
