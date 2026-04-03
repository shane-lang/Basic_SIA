<?php
// =============================================================================
// validation_helper.php
//
// FIX API-04: Centralised input validation so every endpoint can enforce
// length limits and type checks without duplicating logic.
// =============================================================================

/**
 * Validate a map of fields against rules.
 * Returns an array of error strings (empty = all valid).
 *
 * Supported rules (all optional per field):
 *   required  bool    — field must be present and non-empty
 *   type      string  — 'int', 'float', 'email', 'string', 'date' (Y-m-d)
 *   min       number  — min value (int/float) OR min string length
 *   max       number  — max value (int/float) OR max string length
 *   in        array   — value must be one of these
 *   regex     string  — PCRE pattern the value must match
 *
 * Usage:
 *   $errors = validate($_GET, [
 *       'page'  => ['type' => 'int', 'min' => 1, 'max' => 10000],
 *       'limit' => ['type' => 'int', 'min' => 1, 'max' => 100],
 *   ]);
 *   if ($errors) { respond(['success'=>false,'errors'=>$errors], 422); }
 */
function validate(array $data, array $rules): array {
    $errors = [];

    foreach ($rules as $field => $rule) {
        $present = array_key_exists($field, $data) && $data[$field] !== '' && $data[$field] !== null;
        $value   = $data[$field] ?? null;

        // required
        if (!empty($rule['required']) && !$present) {
            $errors[] = "'$field' is required.";
            continue;
        }
        if (!$present) continue;   // optional and absent — skip further checks

        $type = $rule['type'] ?? 'string';

        // type coercion + check
        switch ($type) {
            case 'int':
                if (!ctype_digit(ltrim((string)$value, '-')) || (string)(int)$value !== (string)$value) {
                    $errors[] = "'$field' must be an integer.";
                    continue 2;
                }
                $value = (int)$value;
                break;

            case 'float':
                if (!is_numeric($value)) {
                    $errors[] = "'$field' must be a number.";
                    continue 2;
                }
                $value = (float)$value;
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "'$field' must be a valid email address.";
                    continue 2;
                }
                break;

            case 'date':
                $d = DateTime::createFromFormat('Y-m-d', $value);
                if (!$d || $d->format('Y-m-d') !== $value) {
                    $errors[] = "'$field' must be a date in YYYY-MM-DD format.";
                    continue 2;
                }
                break;

            case 'string':
            default:
                $value = (string)$value;
                break;
        }

        // min / max
        if (isset($rule['min'])) {
            $check = ($type === 'int' || $type === 'float') ? $value : mb_strlen((string)$value);
            if ($check < $rule['min']) {
                $label = ($type === 'int' || $type === 'float') ? "at least {$rule['min']}" : "at least {$rule['min']} characters";
                $errors[] = "'$field' must be $label.";
            }
        }
        if (isset($rule['max'])) {
            $check = ($type === 'int' || $type === 'float') ? $value : mb_strlen((string)$value);
            if ($check > $rule['max']) {
                $label = ($type === 'int' || $type === 'float') ? "at most {$rule['max']}" : "at most {$rule['max']} characters";
                $errors[] = "'$field' must be $label.";
            }
        }

        // in
        if (isset($rule['in']) && !in_array($value, $rule['in'], true)) {
            $opts = implode(', ', $rule['in']);
            $errors[] = "'$field' must be one of: $opts.";
        }

        // regex
        if (isset($rule['regex']) && !preg_match($rule['regex'], (string)$value)) {
            $errors[] = "'$field' has an invalid format.";
        }
    }

    return $errors;
}

/**
 * Convenience wrapper: validate and exit with 422 if there are errors.
 */
function validateOrFail(array $data, array $rules): never|void {
    $errors = validate($data, $rules);
    if ($errors) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
        exit();
    }
}

/**
 * Parse and validate pagination params from $_GET.
 * Returns ['limit' => int, 'offset' => int, 'page' => int].
 *
 * FIX API-05: All list endpoints should call this.
 */
function getPagination(int $defaultLimit = 25, int $maxLimit = 100): array {
    validateOrFail($_GET, [
        'page'  => ['type' => 'int', 'min' => 1, 'max' => 100000],
        'limit' => ['type' => 'int', 'min' => 1, 'max' => $maxLimit],
    ]);

    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min($maxLimit, max(1, (int)($_GET['limit'] ?? $defaultLimit)));
    $offset = ($page - 1) * $limit;

    return compact('page', 'limit', 'offset');
}

/**
 * Wrap a result set with pagination metadata.
 */
function paginatedResponse(array $data, int $total, int $page, int $limit): array {
    return [
        'success'    => true,
        'data'       => $data,
        'pagination' => [
            'total'        => $total,
            'per_page'     => $limit,
            'current_page' => $page,
            'last_page'    => (int)ceil($total / max(1, $limit)),
        ],
    ];
}