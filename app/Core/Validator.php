<?php
declare(strict_types=1);

namespace App\Core;

/** Small rule based validator - kept intentionally simple and dependency free. */
final class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
            $this->errors[$field] = $label . ' wajib diisi.';
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $label . ' tidak valid.';
        }
        return $this;
    }

    public function numeric(string $field, string $label, ?float $min = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this;
        }
        if (!is_numeric($value)) {
            $this->errors[$field] = $label . ' harus berupa angka.';
        } elseif ($min !== null && (float) $value < $min) {
            $this->errors[$field] = $label . ' minimal ' . $min . '.';
        }
        return $this;
    }

    public function min(string $field, string $label, int $length): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && mb_strlen($value) < $length) {
            $this->errors[$field] = $label . ' minimal ' . $length . ' karakter.';
        }
        return $this;
    }

    public function same(string $field, string $other, string $label): self
    {
        if (($this->data[$field] ?? null) !== ($this->data[$other] ?? null)) {
            $this->errors[$field] = $label . ' tidak sama.';
        }
        return $this;
    }

    public function in(string $field, string $label, array $allowed): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = $label . ' tidak valid.';
        }
        return $this;
    }

    public function date(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && strtotime((string) $value) === false) {
            $this->errors[$field] = $label . ' bukan tanggal yang valid.';
        }
        return $this;
    }

    public function custom(bool $condition, string $field, string $message): self
    {
        if ($condition) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors ? reset($this->errors) : null;
    }
}
