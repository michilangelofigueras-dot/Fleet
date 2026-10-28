<?php
declare(strict_types=1);

/** Safe reading of values from the decoded JSON request body. */
trait ValidatesInput
{
    /** A scalar field as a string; anything else (arrays, null) falls back to $default. */
    protected function inputText(array $input, string $key, string $default = ''): string
    {
        $value = $input[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    protected function requireText(array $input, string $key, string $label): string
    {
        $value = trim($this->inputText($input, $key));
        if ($value === '') {
            throw new ValidationException($label . ' is required.');
        }
        return $value;
    }
}
