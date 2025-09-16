<?php

namespace App\DTO;

use App\Attributes\Required;
use App\Attributes\Unique;

class CreateDirectionDTO
{
    #[Required]
    #[Unique('directions', 'name')]
    public string $name;
}