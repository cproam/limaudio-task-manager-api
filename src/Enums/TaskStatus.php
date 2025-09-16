<?php

namespace App\Enums;

enum TaskStatus: string
{
    case New = 'Новая';
    case Assigned = 'Ответственный назначен';
    case InProgress = 'Задача принята в работу';
    case Completed = 'Задача выполнена';
    case Overdue = 'Задача просрочена';
    case Extended = 'Задача продлена';

    /**
     * Get all allowed values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if value is valid
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}