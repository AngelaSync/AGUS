<?php

declare(strict_types=1);

namespace Agus\Support;

use Agus\Database\Database;
use Agus\Database\QueryBuilder;

/**
 * Checks input against rules written as strings:
 *
 *   $v = Validator::make($data, [
 *       'name'     => 'required|max:80',
 *       'email'    => 'required|email|unique:users,email',
 *       'price'    => 'required|numeric|min:1',
 *       'password' => 'required|min:8|confirmed',
 *       'role'     => 'required|in:customer,shop',
 *   ]);
 *   if ($v->fails()) { $v->errors(); }
 */
class Validator
{
    private array $errors = [];

    private const LABELS = ['password_confirmation' => 'password confirmation'];

    public function __construct(private array $data, private array $rules, private array $messages = [])
    {
        $this->run();
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $empty = $value === null || $value === '' || $value === [];

            if (!in_array('required', $rules, true) && $empty) {
                continue; // optional and not given: skip the other rules
            }

            $numeric = in_array('numeric', $rules, true) || in_array('integer', $rules, true);
            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($field, $name, $param, $value, $empty, $numeric);
                if ($error !== null) {
                    $this->errors[$field][] = $this->messages["$field.$name"] ?? $error;
                    break; // one message per field is enough
                }
            }
        }
    }

    private function check(string $field, string $rule, ?string $param, mixed $value, bool $empty, bool $isNumeric): ?string
    {
        $label = self::LABELS[$field] ?? str_replace('_', ' ', $field);

        return match ($rule) {
            'required' => $empty ? "The $label field is required." : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "Enter a valid email address.",
            'numeric' => is_numeric($value) ? null : "The $label must be a number.",
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "The $label must be a whole number.",
            'min' => $isNumeric
                ? ((float) $value >= (float) $param ? null : "The $label must be at least $param.")
                : (mb_strlen((string) $value) >= (int) $param ? null : "The $label must be at least $param characters."),
            'max' => $isNumeric
                ? ((float) $value <= (float) $param ? null : "The $label may not be greater than $param.")
                : (mb_strlen((string) $value) <= (int) $param ? null : "The $label may not be longer than $param characters."),
            'in' => in_array((string) $value, explode(',', (string) $param), true) ? null : "The selected $label is not valid.",
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value ? null : "The $label confirmation does not match.",
            'same' => ($this->data[$param] ?? null) === $value ? null : "The $label must match $param.",
            'regex' => preg_match((string) $param, (string) $value) ? null : "The $label format is not valid.",
            'date' => strtotime((string) $value) !== false ? null : "The $label must be a valid date.",
            'url' => filter_var($value, FILTER_VALIDATE_URL) ? null : "The $label must be a valid URL.",
            'unique' => $this->unique($param, $field, $value) ? null : "This $label is already taken.",
            'exists' => $this->exists($param, $field, $value) ? null : "The selected $label does not exist.",
            default => throw new \InvalidArgumentException("Unknown validation rule [$rule]."),
        };
    }

    /** unique:table,column,ignoreId */
    private function unique(?string $param, string $field, mixed $value): bool
    {
        [$table, $column, $ignore] = array_pad(explode(',', (string) $param), 3, null);
        $query = Database::table((string) $table)->where($column ?: $field, $value);
        if ($ignore !== null && $ignore !== '') {
            $query->where('id', '!=', $ignore);
        }
        return !$query->exists();
    }

    /** exists:table,column */
    private function exists(?string $param, string $field, mixed $value): bool
    {
        [$table, $column] = array_pad(explode(',', (string) $param), 2, null);
        QueryBuilder::assertIdentifier((string) $table);
        return Database::table((string) $table)->where($column ?: $field, $value)->exists();
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Only the fields that had rules. */
    public function validated(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }
}
