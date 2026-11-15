<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Auth\EmailVerificationRequest;

class CustomerEmailVerificationRequest extends EmailVerificationRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->user()?->isCustomer();
    }
}
