<?php

namespace App\Validators;

use App\Repositories\DirectionRepository;
use App\Repositories\UserRepository;
use App\Attributes\Required;
use App\Attributes\Date;

class TaskValidator
{
    private DirectionRepository $directions;
    private UserRepository $users;

    public function __construct()
    {
        $this->directions = new DirectionRepository();
        $this->users = new UserRepository();
    }

    /**
     * Валидация DTO через атрибуты
     * @param object $dto
     * @return array Ошибки валидации
     */
    public function validate(object $dto): array
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

                if ($instance instanceof Date && !empty($value)) {
                    if (!strtotime($value)) {
                        $errors[] = "{$propName} must be a valid date";
                    } elseif ($instance->format && !\DateTime::createFromFormat($instance->format, $value)) {
                        $errors[] = "{$propName} must match format {$instance->format}";
                    }
                }
            }

            // Специфическая валидация (можно убрать, если все через атрибуты)
            if ($propName === 'direction_id' && !empty($value) && !$this->directions->findById($value)) {
                $errors[] = 'direction_id does not exist';
            }
            if ($propName === 'assigned_user_id' && !empty($value) && !$this->users->findById($value)) {
                $errors[] = 'assigned_user_id does not exist';
            }
            if ($propName === 'links' && !empty($value) && is_array($value)) {
                foreach ($value as $link) {
                    if (!filter_var($link, FILTER_VALIDATE_URL)) {
                        $errors[] = 'links must contain valid URLs';
                    }
                }
            }
        }

        return $errors;
    }

    // Старый метод
    public function validateCreate(array $data): array
    {
        $dto = new \App\DTO\CreateTaskDTO();
        foreach ($data as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }
        return $this->validate($dto);
    }
}