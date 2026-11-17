<?php

namespace App\Services;

class TextMessageNumber
{
    public function parse(?string $value): array
    {
        $input = trim((string) $value);

        if ($input === '') {
            return $this->result(null, null, null);
        }

        if (preg_match('/[^0-9+().\s-]/u', $input)) {
            return $this->invalid();
        }

        $number = preg_replace('/[\s().-]/', '', $input);
        if (! is_string($number) || substr_count($number, '+') > 1 || (str_contains($number, '+') && ! str_starts_with($number, '+'))) {
            return $this->invalid();
        }

        if (preg_match('/^09\d{9}$/', $number)) {
            $normalized = '+63'.substr($number, 1);
        } elseif (preg_match('/^9\d{9}$/', $number)) {
            $normalized = '+63'.$number;
        } elseif (preg_match('/^639\d{9}$/', $number)) {
            $normalized = '+'.$number;
        } elseif (preg_match('/^\+639\d{9}$/', $number)) {
            $normalized = $number;
        } elseif (str_starts_with($number, '+63')) {
            return $this->invalid();
        } elseif (preg_match('/^\+[1-9]\d{7,14}$/', $number)) {
            $normalized = $number;
        } else {
            return $this->invalid();
        }

        return $this->result(
            $normalized,
            $this->format($normalized),
            'sms:'.$normalized,
        );
    }

    private function format(string $number): string
    {
        if (preg_match('/^\+639(\d{2})(\d{3})(\d{4})$/', $number, $parts)) {
            return '+63 9'.$parts[1].' '.$parts[2].' '.$parts[3];
        }

        return $number;
    }

    private function result(?string $number, ?string $formatted, ?string $url): array
    {
        return [
            'valid' => true,
            'number' => $number,
            'formatted' => $formatted,
            'url' => $url,
            'name' => 'Text us',
            'icon' => 'chat-dots',
            'label' => $formatted,
            'username' => null,
            'input_type' => 'phone',
            'normalization' => 'e164',
            'label_style' => 'text',
            'aria_label' => $number ? 'Text FleetGuard at '.$this->format($number) : 'Text FleetGuard',
            'touch_only' => true,
            'error' => null,
        ];
    }

    private function invalid(): array
    {
        return [
            ...$this->result(null, null, null),
            'valid' => false,
            'error' => 'Enter a valid Philippine mobile number or an international number beginning with +.',
        ];
    }
}
