<?php

namespace App\DTO;

use App\Attributes\Required;
use App\Attributes\Unique;

class UpdateDirectionDTO
{
    #[Required]
    #[Unique('directions', 'name')]
    public string $name;
}