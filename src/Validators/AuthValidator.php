<?php

namespace App\Validators;

use App\Attributes\Required;
use App\Attributes\Email;

class AuthValidator
{
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

                if ($instance instanceof Email && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "{$propName} must be a valid email";
                }
            }
        }

        return $errors;
    }
}