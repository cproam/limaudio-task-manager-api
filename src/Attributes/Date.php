<?php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Date
{
    public function __construct(
        public ?string $format = null // Например, 'Y-m-d H:i:s'
    ) {}
}