<?php

namespace App\Enums;

enum TaskStatus: string
{
    case New = 'Новая';
    case Assigned = 'Ответственный назначен';
    case InProgress = 'В работе';
    case Completed = 'Выполнена';
    case Overdue = 'Просрочена';
    case Extended = 'Продлена';

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