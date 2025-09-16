<?php

namespace App\DTO;

use App\Attributes\Email;
use App\Attributes\Unique;
use App\Attributes\MinLength;

class UpdateUserDTO
{
    public ?string $name = null;

    #[Email]
    #[Unique('users', 'email')]
    public ?string $email = null;

    #[MinLength(6)]
    public ?string $password = null;

    public ?array $roles = null;

    public ?string $telegram_id = null;
}