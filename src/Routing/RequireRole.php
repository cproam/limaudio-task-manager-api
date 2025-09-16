<?php

namespace App\Routing;

#[\Attribute(\Attribute::TARGET_METHOD)]
class RequireRole
{
    public function __construct(
        public string $role
    ) {}
}