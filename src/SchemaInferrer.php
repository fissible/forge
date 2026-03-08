<?php

declare(strict_types=1);

namespace Fissible\Forge;

/**
 * Infers a JSON Schema object from a framework validation rules array.
 *
 * Supports string rules ('required|string|max:255') and array rules
 * (['required', 'string', 'max:255']). Rule objects (e.g. Laravel's
 * Rule::in()) are skipped — add those schema entries manually.
 */
class SchemaInferrer
{
    /**
     * @param  array<string, string|array> $rules  Field name → rule(s)
     * @return array  JSON Schema object definition
     */
    public function inferRequestSchema(array $rules): array
    {
        $properties = [];
        $required   = [];

        foreach ($rules as $field => $fieldRules) {
            // Skip nested dot-notation fields (e.g. 'address.city') for now
            if (str_contains($field, '.')) {
                continue;
            }

            $ruleList = $this->parseRules($fieldRules);

            if (in_array('required', $ruleList, strict: true)) {
                $required[] = $field;
            }

            $properties[$field] = $this->inferProperty($ruleList);
        }

        $schema = ['type' => 'object', 'properties' => $properties];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /** @return string[] */
    private function parseRules(string|array $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        // Filter out non-string Rule objects
        return array_values(array_filter($rules, 'is_string'));
    }

    private function inferProperty(array $rules): array
    {
        $type     = $this->inferType($rules);
        $property = ['type' => $type];

        // Format
        if (in_array('email', $rules, strict: true)) {
            $property['format'] = 'email';
        } elseif (in_array('url', $rules, strict: true)) {
            $property['format'] = 'uri';
        } elseif (in_array('date', $rules, strict: true)) {
            $property['format'] = 'date';
        } elseif ($this->findPrefixedRule($rules, 'date_format')) {
            $property['format'] = 'date-time';
        } elseif (in_array('uuid', $rules, strict: true)) {
            $property['format'] = 'uuid';
        }

        // Nullable
        if (in_array('nullable', $rules, strict: true)) {
            $property['nullable'] = true;
        }

        // Numeric/string constraints
        foreach ($rules as $rule) {
            if (!is_string($rule)) {
                continue;
            }

            if (str_starts_with($rule, 'min:')) {
                $n = (int) substr($rule, 4);
                $property[$type === 'string' ? 'minLength' : 'minimum'] = $n;
            }

            if (str_starts_with($rule, 'max:')) {
                $n = (int) substr($rule, 4);
                $property[$type === 'string' ? 'maxLength' : 'maximum'] = $n;
            }

            if (str_starts_with($rule, 'in:')) {
                $property['enum'] = explode(',', substr($rule, 3));
            }

            if (str_starts_with($rule, 'digits:')) {
                $property['minLength'] = (int) substr($rule, 7);
                $property['maxLength'] = (int) substr($rule, 7);
            }
        }

        return $property;
    }

    private function inferType(array $rules): string
    {
        if (array_intersect(['integer', 'int', 'numeric'], $rules)) {
            return 'integer';
        }

        if (array_intersect(['boolean', 'bool', 'accepted', 'declined'], $rules)) {
            return 'boolean';
        }

        if (in_array('array', $rules, strict: true)) {
            return 'array';
        }

        if (array_intersect(['number', 'decimal'], $rules)) {
            return 'number';
        }

        // String is the default
        return 'string';
    }

    private function findPrefixedRule(array $rules, string $prefix): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
