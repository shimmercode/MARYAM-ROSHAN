<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\Database;
use App\Core\Exceptions\ValidationException;
use App\Helpers\Format;
use App\Helpers\Jalali;

/**
 * Rule-based validator.
 *
 *  'mobile' => 'required|mobile|unique:customers,mobile'
 *  'price'  => 'required|numeric|min:0'
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $labels;
    private array $errors = [];
    private array $validated = [];

    public function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data   = $data;
        $this->rules  = $rules;
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    /** Validate and throw on failure. @return array validated subset */
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        $v = new self($data, $rules, $labels);
        if ($v->fails()) {
            throw new ValidationException($v->errors());
        }
        return $v->validated();
    }

    public function fails(): bool
    {
        $this->run();
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? $field;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function run(): void
    {
        $this->errors    = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleString) {
            $rules    = is_array($ruleString) ? $ruleString : explode('|', (string)$ruleString);
            $value    = $this->data[$field] ?? null;
            $nullable = in_array('nullable', $rules, true);
            $required = in_array('required', $rules, true);

            if (is_string($value)) {
                $value = trim($value);
            }
            $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

            if ($required && $isEmpty) {
                $this->addError($field, 'فیلد «' . $this->label($field) . '» الزامی است.');
                continue;
            }
            if ($isEmpty) {
                if ($nullable || !$required) {
                    $this->validated[$field] = $nullable ? null : $value;
                }
                continue;
            }

            $failed = false;
            foreach ($rules as $rule) {
                if (in_array($rule, ['required', 'nullable'], true)) {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', (string)$rule, 2), 2, null);
                $result = $this->applyRule((string)$name, $value, $param, $field);
                if ($result === false) {
                    $failed = true;
                    break;
                }
                if (is_array($result) && array_key_exists('value', $result)) {
                    $value = $result['value'];
                }
            }
            if (!$failed) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function applyRule(string $name, mixed $value, ?string $param, string $field): bool|array
    {
        $label = $this->label($field);

        switch ($name) {
            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, 'مقدار «' . $label . '» باید متن باشد.');
                    return false;
                }
                return true;

            case 'int':
            case 'integer':
                if (!is_numeric($value) || (string)(int)$value !== (string)$value && !is_int($value)) {
                    if (!preg_match('/^-?\d+$/', (string)Format::toEnglishDigits((string)$value))) {
                        $this->addError($field, 'مقدار «' . $label . '» باید عدد صحیح باشد.');
                        return false;
                    }
                }
                return ['value' => (int)Format::toEnglishDigits((string)$value)];

            case 'numeric':
                $n = Format::toEnglishDigits(str_replace(',', '', (string)$value));
                if (!is_numeric($n)) {
                    $this->addError($field, 'مقدار «' . $label . '» باید عدد باشد.');
                    return false;
                }
                return ['value' => (float)$n];

            case 'bool':
            case 'boolean':
                return ['value' => in_array((string)$value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0];

            case 'min':
                // Only compare by numeric magnitude when the value has already been cast to
                // an actual int/float by a preceding 'int'/'numeric' rule. A plain string
                // field (e.g. a mobile number) must always be checked by character length,
                // even though it happens to look numeric — otherwise "09120000000" fails a
                // "max:150" length rule because is_numeric() treats it as ~9.12e9.
                if (is_int($value) || is_float($value)) {
                    if ((float)$value < (float)$param) {
                        $this->addError($field, '«' . $label . '» نباید کمتر از ' . Format::digits((string)$param) . ' باشد.');
                        return false;
                    }
                } elseif (mb_strlen((string)$value) < (int)$param) {
                    $this->addError($field, '«' . $label . '» باید حداقل ' . Format::digits((string)$param) . ' کاراکتر باشد.');
                    return false;
                }
                return true;

            case 'max':
                if (is_int($value) || is_float($value)) {
                    if ((float)$value > (float)$param) {
                        $this->addError($field, '«' . $label . '» نباید بیشتر از ' . Format::digits((string)$param) . ' باشد.');
                        return false;
                    }
                } elseif (mb_strlen((string)$value) > (int)$param) {
                    $this->addError($field, '«' . $label . '» باید حداکثر ' . Format::digits((string)$param) . ' کاراکتر باشد.');
                    return false;
                }
                return true;

            case 'mobile':
                $m = Format::mobile((string)$value);
                if (!Format::isValidMobile($m)) {
                    $this->addError($field, 'شماره موبایل وارد شده معتبر نیست. (مثال: ۰۹۱۲۱۲۳۴۵۶۷)');
                    return false;
                }
                return ['value' => $m];

            case 'email':
                if (!filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'ایمیل وارد شده معتبر نیست.');
                    return false;
                }
                return ['value' => mb_strtolower((string)$value)];

            case 'date':
                $v = (string)$value;
                if (preg_match('/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', Format::toEnglishDigits($v))) {
                    $en = Format::toEnglishDigits($v);
                    $year = (int)explode(preg_match('/\//', $en) ? '/' : '-', $en)[0];
                    if ($year < 1900) { // Jalali year
                        $g = Jalali::parse($en);
                        if ($g === null) {
                            $this->addError($field, 'تاریخ «' . $label . '» معتبر نیست.');
                            return false;
                        }
                        return ['value' => $g];
                    }
                    return ['value' => str_replace('/', '-', $en)];
                }
                if (strtotime($v) === false) {
                    $this->addError($field, 'تاریخ «' . $label . '» معتبر نیست.');
                    return false;
                }
                return true;

            case 'time':
                if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', Format::toEnglishDigits((string)$value))) {
                    $this->addError($field, 'ساعت «' . $label . '» معتبر نیست.');
                    return false;
                }
                $t = Format::toEnglishDigits((string)$value);
                return ['value' => strlen($t) === 5 ? $t . ':00' : $t];

            case 'in':
                $allowed = explode(',', (string)$param);
                if (!in_array((string)$value, $allowed, true)) {
                    $this->addError($field, 'مقدار «' . $label . '» معتبر نیست.');
                    return false;
                }
                return true;

            case 'exists':
                [$table, $column] = array_pad(explode(',', (string)$param), 2, 'id');
                $db  = Database::instance();
                $cnt = (int)$db->scalar(
                    'SELECT COUNT(*) FROM ' . $db->quoteIdent($table) . ' WHERE ' . $db->quoteIdent($column) . ' = :v',
                    ['v' => $value]
                );
                if ($cnt === 0) {
                    $this->addError($field, 'مقدار انتخاب‌شده برای «' . $label . '» وجود ندارد.');
                    return false;
                }
                return true;

            case 'unique':
                $parts  = explode(',', (string)$param);
                $table  = $parts[0];
                $column = $parts[1] ?? $field;
                $ignore = isset($parts[2]) ? (int)$parts[2] : 0;
                $db     = Database::instance();
                $sql    = 'SELECT COUNT(*) FROM ' . $db->quoteIdent($table) . ' WHERE ' . $db->quoteIdent($column) . ' = :v';
                $bind   = ['v' => $value];
                if ($ignore > 0) {
                    $sql .= ' AND id <> :ig';
                    $bind['ig'] = $ignore;
                }
                if (in_array($table, ['users', 'customers', 'staff', 'services', 'products', 'branches'], true)) {
                    $sql .= ' AND deleted_at IS NULL';
                }
                if ((int)$db->scalar($sql, $bind) > 0) {
                    $this->addError($field, 'این «' . $label . '» قبلاً ثبت شده است.');
                    return false;
                }
                return true;

            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->addError($field, 'تکرار «' . $label . '» مطابقت ندارد.');
                    return false;
                }
                return true;

            case 'regex':
                if (!preg_match((string)$param, (string)$value)) {
                    $this->addError($field, 'قالب «' . $label . '» صحیح نیست.');
                    return false;
                }
                return true;

            case 'array':
                if (!is_array($value)) {
                    $this->addError($field, '«' . $label . '» باید فهرست باشد.');
                    return false;
                }
                return true;

            case 'national_code':
                $code = Format::toEnglishDigits((string)$value);
                if (!self::validNationalCode($code)) {
                    $this->addError($field, 'کد ملی وارد شده معتبر نیست.');
                    return false;
                }
                return ['value' => $code];

            case 'slug':
                return ['value' => Format::slug((string)$value)];

            default:
                return true;
        }
    }

    public static function validNationalCode(string $code): bool
    {
        if (!preg_match('/^\d{10}$/', $code) || preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$code[$i] * (10 - $i);
        }
        $rem = $sum % 11;
        $chk = (int)$code[9];
        return ($rem < 2 && $chk === $rem) || ($rem >= 2 && $chk === 11 - $rem);
    }
}
