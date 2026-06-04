<?php

function formatDateIt(mixed $value): string
{
    if ($value === null) {
        return '-';
    }
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00' || str_starts_with($value, '0000-00-00')) {
        return '-';
    }

    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime && $date->format($format) === $value) {
            return $date->format('d/m/Y');
        }
    }

    try {
        return (new DateTime($value))->format('d/m/Y');
    } catch (Exception $e) {
        return $value;
    }
}

function normalizeDateForDb(mixed $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    foreach (['d/m/Y', 'Y-m-d'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }

    return $value;
}

function formatDateInputIt(mixed $value): string
{
    $formatted = formatDateIt($value);
    return $formatted === '-' ? '' : $formatted;
}

function renderHeader(string $title = 'Simplex'): void
{
    ?>
    <!doctype html>
    <html lang="it">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/css/app.css" rel="stylesheet">
    </head>
    <body>
    <?php
}

function renderFooter(): void
{
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
