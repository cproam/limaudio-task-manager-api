<?php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MinLength
{
    public function __construct(
        public int $length
    ) {}
}