<?php

namespace App\DTO;

use App\Attributes\Required;
use App\Attributes\Date;

class CreateTaskDTO
{
    #[Required]
    public string $title;

    public ?string $description = null;

    public ?int $direction_id = null;

    public ?int $urgency = null;

    #[Date]
    public ?string $due_at = null;

    public ?int $assigned_user_id = null;

    public ?array $links = null;

    public ?array $files = null;
}