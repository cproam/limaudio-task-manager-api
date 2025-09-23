<?php

namespace App\DTO;

use App\Attributes\Required;
use App\Attributes\Unique;

class CreateRoleDTO
{
    #[Required]
    #[Unique('roles', 'name')]
    public string $name;
}