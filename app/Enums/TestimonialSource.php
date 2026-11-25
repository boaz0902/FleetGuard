<?php

namespace App\Enums;

enum TestimonialSource: string
{
    case GoogleReview = 'google_review';
    case VerifiedCustomer = 'verified_customer';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::GoogleReview => 'Google review',
            self::VerifiedCustomer => 'Verified customer',
            self::Manual => 'Manual',
        };
    }
}
