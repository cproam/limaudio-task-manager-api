<?php

namespace App\Validators;

use App\Repositories\DirectionRepository;
use App\Attributes\Required;
use App\Attributes\Unique;

class DirectionValidator
{
    private DirectionRepository $directions;

    public function __construct()
    {
        $this->directions = new DirectionRepository();
    }

    /**
     * Валидация DTO через атрибуты
     * @param object $dto
     * @param int|null $excludeId
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

    // Старые методы
    public function validateCreate(array $data): array
    {
        $dto = new \App\DTO\CreateDirectionDTO();
        $dto->name = $data['name'] ?? '';
        return $this->validate($dto);
    }

    public function validateUpdate(array $data, int $directionId): array
    {
        $dto = new \App\DTO\UpdateDirectionDTO();
        $dto->name = $data['name'] ?? '';
        return $this->validate($dto, $directionId);
    }
}