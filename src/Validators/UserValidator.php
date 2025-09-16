<?php

namespace App\Validators;

use App\Repositories\UserRepository;
use App\Attributes\Required;
use App\Attributes\Email;
use App\Attributes\Unique;
use App\Attributes\MinLength;

class UserValidator
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    /**
     * Валидация DTO через атрибуты
     * @param object $dto
     * @param int|null $excludeId Для unique при обновлении
     * @return array Ошибки валидации
     */
    public function validate(object $dto, ?int $excludeId = null): array
    {
        $errors = [];
        $reflection = new \ReflectionClass($dto);

        foreach ($reflection->getProperties() as $prop) {
            $value = $prop->getValue($dto);
            $propName = $prop->getName();

            foreach ($prop->getAttributes() as $attr) {
                $instance = $attr->newInstance();

                if ($instance instanceof Required && (is_null($value) || $value === '')) {
                    $errors[] = "{$propName} is required";
                }

                if ($instance instanceof Email && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "{$propName} must be a valid email";
                }

                if ($instance instanceof MinLength && !empty($value) && strlen($value) < $instance->length) {
                    $errors[] = "{$propName} must be at least {$instance->length} characters";
                }

                if ($instance instanceof Unique && !empty($value)) {
                    $pdo = \App\Database\DB::conn();
                    $stmt = $pdo->prepare("SELECT 1 FROM {$instance->table} WHERE {$instance->column} = ? " . ($excludeId ? "AND id != ?" : "") . " LIMIT 1");
                    $params = [$value];
                    if ($excludeId) $params[] = $excludeId;
                    $stmt->execute($params);
                    if ($stmt->fetch()) {
                        $errors[] = "{$propName} already exists";
                    }
                }
            }
        }

        return $errors;
    }

    // Старые методы для обратной совместимости, но теперь используют validate
    public function validateCreate(array $data): array
    {
        $dto = new \App\DTO\CreateUserDTO();
        foreach ($data as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }
        return $this->validate($dto);
    }

    public function validateUpdate(array $data, int $userId): array
    {
        $dto = new \App\DTO\UpdateUserDTO();
        foreach ($data as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }
        return $this->validate($dto, $userId);
    }
}