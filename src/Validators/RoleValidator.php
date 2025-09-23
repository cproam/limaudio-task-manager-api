<?php

namespace App\Validators;

use App\Attributes\Required;
use App\Attributes\Unique;

class RoleValidator
{

    public function __construct() {}

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

    public function validateCreate(array $data): array
    {
        $dto = new \App\DTO\CreateRoleDTO();
        $dto->name = $data['name'] ?? '';
        $dto->description = $data['description'] ?? '';
        return $this->validate($dto);
    }

    public function validateUpdate(array $data, int $roleId): array
    {
        $dto = new \App\DTO\UpdateRoleDTO();
        $dto->name = $data['name'] ?? '';
        $dto->description = $data['description'] ?? '';
        return $this->validate($dto, $roleId);
    }
}
