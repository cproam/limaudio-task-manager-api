<?php

namespace App\DTO;

use App\Attributes\Required;
use App\Attributes\Email;
use App\Attributes\Unique;
use App\Attributes\MinLength;

class CreateUserDTO
{
    #[Required]
    public string $name;

    #[Required]
    #[Email]
    #[Unique('users', 'email')]
    public string $email;

    #[Required]
    #[MinLength(6)]
    public string $password;

    public ?array $roles = null;

    public ?string $telegram_id = null;
}