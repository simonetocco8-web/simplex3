<?php

function ensureConsuntivoSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS commessa_momenti_consuntivo (
        momento_id INT UNSIGNED PRIMARY KEY,
        data_momento DATE NOT NULL,
        tipologia VARCHAR(120) NOT NULL,
        valore_giornaliero_uomo DECIMAL(12,2) NOT NULL,
        costo_totale DECIMAL(12,2) NOT NULL,
        ore DECIMAL(8,2) NOT NULL,
        giorni DECIMAL(8,2) NOT NULL,
        numero_incontri INT UNSIGNED NOT NULL,
        ore_studio VARCHAR(5) NULL,
        data_prevista DATE NULL,
        aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_consuntivo_momento FOREIGN KEY (momento_id)
            REFERENCES commessa_momenti_lavorazione(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function consuntivoFields(): array
{
    return ['data_momento', 'tipologia', 'valore_giornaliero_uomo', 'costo_totale',
        'ore', 'giorni', 'numero_incontri', 'ore_studio', 'data_prevista'];
}

function consuntivoDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function consuntivoDecimal(string $value, int $integerDigits): ?string
{
    $value = str_replace(',', '.', trim($value));
    if (!preg_match('/^\d{1,' . $integerDigits . '}(?:\.\d{1,2})?$/', $value)) return null;
    $parts = explode('.', $value);
    return (string)(int)$parts[0] . '.' . str_pad($parts[1] ?? '', 2, '0');
}

function validateConsuntivo(array $input): array
{
    $values = [];
    $errors = [];
    foreach (consuntivoFields() as $field) {
        if (isset($input[$field]) && !is_scalar($input[$field])) {
            $errors[] = 'Valore non valido per ' . $field . '.';
            continue;
        }
        $values[$field] = trim((string)($input[$field] ?? ''));
    }
    if ($errors) return [$values, $errors];
    foreach (['valore_giornaliero_uomo' => 10, 'costo_totale' => 10, 'ore' => 6, 'giorni' => 6] as $field => $digits) {
        $normalized = consuntivoDecimal($values[$field], $digits);
        if ($normalized === null) $errors[] = 'Valore non valido per ' . $field . ': usa un numero non negativo con massimo due decimali.';
        else $values[$field] = $normalized;
    }
    if (!consuntivoDate($values['data_momento'])) $errors[] = 'Data del consuntivo non valida.';
    if ($values['data_prevista'] !== '' && !consuntivoDate($values['data_prevista'])) $errors[] = 'Data prevista non valida.';
    if ($values['tipologia'] === '' || mb_strlen($values['tipologia']) > 120) $errors[] = 'Tipologia obbligatoria e massimo 120 caratteri.';
    if (!preg_match('/^\d{1,10}$/', $values['numero_incontri']) || (int)$values['numero_incontri'] > 4294967295) $errors[] = 'Numero incontri non valido.';
    if ($values['ore_studio'] !== '' && (strlen($values['ore_studio']) > 5
        || !preg_match('/^(?:\d{1,2}:[0-5]\d|\d{1,3}(?:[.,]\d{1,2})?)$/', $values['ore_studio']))) $errors[] = 'Ore di studio non valide: usa ore numeriche o HH:MM.';
    $values['data_prevista'] = $values['data_prevista'] !== '' ? $values['data_prevista'] : null;
    $values['ore_studio'] = $values['ore_studio'] !== '' ? $values['ore_studio'] : null;
    return [$values, $errors];
}

function consuntivoCents(mixed $value): int
{
    $parts = explode('.', (string)$value);
    return (int)$parts[0] * 100 + (int)str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
}

function consuntivoDeltaClass(int $delta): string
{
    return $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : 'text-body-secondary');
}

function consuntivoFormat(int $cents): string
{
    return ($cents > 0 ? '+' : '') . number_format($cents / 100, 2, ',', '.');
}
