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

function menuIcon(string $label): string
{
    $icons = [
        'Bacheca' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'Offerte' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5"/>',
        'Commesse' => '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V3h8v4M3 12h18M10 12v3h4v-3"/>',
        'Anagrafiche' => '<path d="M4 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4zM2 7h4M2 12h4M2 17h4M9 17c0-4 7-4 7 0"/><circle cx="12.5" cy="8.5" r="2.5"/>',
        'Utenti' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M17 4a4 4 0 0 1 0 8M18 15a6 6 0 0 1 4 6"/>',
        'Aziende' => '<path d="M4 21V3h12v18M16 9h4v12M2 21h20M8 7h4M8 11h4M8 15h4M9 21v-3h2v3"/>',
        'Enti di Certificazione' => '<circle cx="12" cy="8" r="6"/><path d="m9 8 2 2 4-4M8 13l-2 9 6-3 6 3-2-9"/>',
        'Amministrazione' => '<path d="m2 9 10-6 10 6H2zM4 21h16M6 9v9M12 9v9M18 9v9"/>',
        'Produzione' => '<path d="M3 3v18h18M7 17v-4M12 17V9M17 17V5"/>',
        'Pro-Forma' => '<path d="M5 2h14v20l-3-2-4 2-4-2-3 2V2zM8 6h8M8 10h5"/>',
        'Genera Fattura' => '<path d="M4 3h12v7M4 3v18h9M8 7h4M8 11h4M18 13v8M14 17h8"/>',
        'Fatture' => '<path d="M5 2h14v20l-3-2-4 2-4-2-3 2V2zM8 6h8M8 10h8M8 14h5"/>',
        'Pagamenti' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20M6 15h4"/>',
        'Impostazioni' => '<circle cx="12" cy="12" r="3"/><path d="m9 3 1-1h4l1 3 3 1 3 1v4l-2 2-1 3-1 3h-4l-2-2-3-1-3-1v-4l2-2 1-3z"/>',
        'Note' => '<path d="M4 3h16v13l-5 5H4zM15 21v-5h5M8 7h8M8 11h8M8 15h3"/>',
        'Modifica' => '<path d="m16 3 5 5L8 21H3v-5zM14 5l5 5"/>',
        'Lavorazioni' => '<path d="M14 6a6 6 0 0 0-8 8L2 18l4 4 4-4a6 6 0 0 0 8-8l-4 4-4-4z"/>',
        'Elimina' => '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>',
        'Logout' => '<path d="M9 21H4V3h5M9 12h13M17 7l5 5-5 5"/>',
    ];

    if (!isset($icons[$label])) {
        return '';
    }

    return '<svg class="menu-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $icons[$label] . '</svg>';
}

function currentMenuPage(): string
{
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return $page === 'lavorazioni.php' ? 'offerte.php' : $page;
}

function navAttributes(string $page): string
{
    return currentMenuPage() === $page
        ? 'class="nav-link active" aria-current="page"'
        : 'class="nav-link"';
}

function navGroupActive(string $group): bool
{
    $groups = [
        'Anagrafiche' => ['utenti.php', 'aziende.php', 'enti_certificazione.php'],
        'Amministrazione' => ['amministrazione_produzione.php', 'proforme.php', 'fatture.php', 'pagamenti.php'],
    ];
    return in_array(currentMenuPage(), $groups[$group] ?? [], true);
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
