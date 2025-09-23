<?php

namespace App\DTO;

use App\Attributes\Required;
use App\Attributes\Unique;

class CreatePermissionDTO
{
    #[Required]
    #[Unique('permissions', 'name')]
    public string $name;

    public string $userId;
    
    public string $roleId;
}
