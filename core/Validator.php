<?php

class Validator
{
    protected $data;
    protected $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required($field, $label)
    {
        if (trim((string) ($this->data[$field] ?? '')) === '') {
            $this->errors[$field] = $label . ' الزامی است.';
        }
        return $this;
    }

    public function mobile($field, $label)
    {
        $value = to_english_digits($this->data[$field] ?? '');
        if ($value !== '' && !preg_match('/^09\d{9}$/', $value)) {
            $this->errors[$field] = $label . ' معتبر نیست.';
        }
        return $this;
    }

    public function nationalId($field, $label)
    {
        $value = to_english_digits($this->data[$field] ?? '');
        if ($value !== '' && !preg_match('/^\d{10}$/', $value)) {
            $this->errors[$field] = $label . ' باید ده رقم باشد.';
        }
        return $this;
    }

    public function email($field, $label)
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $label . ' معتبر نیست.';
        }
        return $this;
    }

    public function min($field, $label, $minimum)
    {
        if (mb_strlen((string) ($this->data[$field] ?? ''), 'UTF-8') < $minimum) {
            $this->errors[$field] = $label . ' کوتاه است.';
        }
        return $this;
    }

    public function errors()
    {
        return $this->errors;
    }

    public function passes()
    {
        return empty($this->errors);
    }
}
