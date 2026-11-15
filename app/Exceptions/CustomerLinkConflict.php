<?php

namespace App\Exceptions;

use RuntimeException;

class CustomerLinkConflict extends RuntimeException
{
    public function __construct(
        public readonly array $customerIds,
        public readonly string $reason,
    ) {
        parent::__construct('A customer record link conflict occurred.');
    }
}
