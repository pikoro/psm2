<?php

declare(strict_types=1);

namespace App;

final class Validator
{
    private array $data = [];
    private array $errors = [];
    private array $rules = [];

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleSet) {
            foreach ($ruleSet as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function required(string $field): self
    {
        $this->rules[$field][] = 'required';
        return $this;
    }

    public function email(string $field): self
    {
        $this->rules[$field][] = 'email';
        return $this;
    }

    public function min(string $field, int $length): self
    {
        $this->rules[$field][] = "min:{$length}";
        return $this;
    }

    public function max(string $field, int $length): self
    {
        $this->rules[$field][] = "max:{$length}";
        return $this;
    }

    public function between(string $field, int $min, int $max): self
    {
        $this->rules[$field][] = "between:{$min},{$max}";
        return $this;
    }

    public function numeric(string $field): self
    {
        $this->rules[$field][] = 'numeric';
        return $this;
    }

    public function integer(string $field): self
    {
        $this->rules[$field][] = 'integer';
        return $this;
    }

    public function in(string $field, string $values): self
    {
        $this->rules[$field][] = "in:{$values}";
        return $this;
    }

    public function same(string $field, string $otherField): self
    {
        $this->rules[$field][] = "same:{$otherField}";
        return $this;
    }

    public function unique(string $field, string $table, string $column, int $excludeId = 0): self
    {
        $this->rules[$field][] = "unique:{$table},{$column},{$excludeId}";
        return $this;
    }

    public function url(string $field): self
    {
        $this->rules[$field][] = 'url';
        return $this;
    }

    private function applyRule(string $field, string $rule): void
    {
        $value = $this->data[$field] ?? null;

        match(true) {
            $rule === 'required' => $this->ruleRequired($field, $value),
            $rule === 'email'    => $this->ruleEmail($field, $value),
            $rule === 'numeric'  => $this->ruleNumeric($field, $value),
            $rule === 'integer'  => $this->ruleInteger($field, $value),
            $rule === 'url'      => $this->ruleUrl($field, $value),
            str_starts_with($rule, 'min:') => $this->ruleMin($field, $value, $rule),
            str_starts_with($rule, 'max:') => $this->ruleMax($field, $value, $rule),
            str_starts_with($rule, 'between:') => $this->ruleBetween($field, $value, $rule),
            str_starts_with($rule, 'in:')  => $this->ruleIn($field, $value, $rule),
            str_starts_with($rule, 'same:') => $this->ruleSame($field, $value, $rule),
            str_starts_with($rule, 'unique:') => $this->ruleUnique($field, $value, $rule),
            default => null,
        };
    }

    private function ruleRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is required.');
        }
    }

    private function ruleEmail(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a valid email address.');
        }
    }

    private function ruleNumeric(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be numeric.');
        }
    }

    private function ruleInteger(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be an integer.');
        }
    }

    private function ruleUrl(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a valid URL.');
        }
    }

    private function ruleMin(string $field, mixed $value, string $rule): void
    {
        $min = (int) explode(':', $rule)[1];
        if ($value !== null && strlen((string) $value) < $min) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters.");
        }
    }

    private function ruleMax(string $field, mixed $value, string $rule): void
    {
        $max = (int) explode(':', $rule)[1];
        if ($value !== null && strlen((string) $value) > $max) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max} characters.");
        }
    }

    private function ruleBetween(string $field, mixed $value, string $rule): void
    {
        [$min, $max] = array_map('intval', explode(':', $rule)[1] ?? '0,0');
        if ($value !== null && strlen((string) $value) < $min) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters.");
        }
        if ($value !== null && strlen((string) $value) > $max) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max} characters.");
        }
    }

    private function ruleIn(string $field, mixed $value, string $rule): void
    {
        $allowed = explode(',', explode(':', $rule)[1]);
        if (!in_array($value, $allowed, true)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be one of: ' . implode(', ', $allowed));
        }
    }

    private function ruleSame(string $field, mixed $value, string $rule): void
    {
        $other = explode(':', $rule)[1];
        $otherValue = $this->data[$other] ?? null;
        if ($value !== $otherValue) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must match ' . str_replace('_', ' ', $other) . '.');
        }
    }

    private function ruleUnique(string $field, mixed $value, string $rule): void
    {
        [$table, $column, $excludeId] = explode(',', explode(':', $rule)[1]);
        $excludeId = (int) $excludeId;

        if ($excludeId > 0) {
            $sql = "SELECT id FROM `{$table}` WHERE `{$column}` = ? AND id != ? LIMIT 1";
        } else {
            $sql = "SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1";
        }

        $stmt = Database::getConnection()->prepare($sql);
        if ($excludeId > 0) {
            $stmt->execute([$value, $excludeId]);
        } else {
            $stmt->execute([$value]);
        }

        if ($stmt->fetch()) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is already taken.');
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
