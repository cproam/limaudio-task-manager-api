<?php

namespace App\DTO;

use App\Attributes\Required;
use App\Attributes\Email;

class LoginDTO
{
    #[Required]
    #[Email]
    public string $email;

    #[Required]
    public string $password;
}