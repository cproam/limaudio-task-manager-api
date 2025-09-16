<?php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Unique
{
    public function __construct(
        public string $table,
        public string $column
    ) {}
}