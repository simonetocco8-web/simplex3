<?php

const AZIENDA_ORGANICO_OPTIONS = ['0-10', '11-25', '26-50', '51-250', '>250', 'Sconosciuto'];
const AZIENDA_FATTURATO_OPTIONS = ['<500K', '500-1000K', '1000-5000', '>5000'];
const AZIENDA_SETTORI = [
    'Agroalimentare', 'Commercio', 'Servizi', 'Edilizia', 'Formazione',
    'Manifatturiero', 'Sanità', 'Trasporti', 'Turismo', 'P.A',
];

function ensureCompanyIdentifiers(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM aziende LIKE 'partita_iva'")->fetch();
    if ($column && $column['Null'] === 'NO') {
        // NULL permits multiple companies identified exclusively by their fiscal code.
        $pdo->exec('ALTER TABLE aziende MODIFY COLUMN partita_iva VARCHAR(11) NULL');
    }
}

function companyIdentifierErrors(PDO $pdo, string $partitaIva, string $codiceFiscale, int $excludeId = 0): array
{
    if ($partitaIva === '' && $codiceFiscale === '') {
        return ['Inserisci la Partita IVA oppure, in mancanza, il Codice Fiscale.'];
    }
    if ($partitaIva !== '' && !preg_match('/^\d{11}$/', $partitaIva)) {
        return ['Partita IVA non valida: inserire esattamente 11 numeri.'];
    }
    if ($codiceFiscale !== '' && !preg_match('/^(?:[A-Z0-9]{16}|\d{11})$/', $codiceFiscale)) {
        return ['Codice Fiscale non valido: inserire 16 caratteri alfanumerici oppure 11 numeri.'];
    }
    $field = $partitaIva !== '' ? 'partita_iva' : 'codice_fiscale';
    $stmt = $pdo->prepare("SELECT id FROM aziende WHERE UPPER(TRIM($field)) = :identifier AND id <> :id LIMIT 1");
    $stmt->execute([':identifier' => $partitaIva !== '' ? $partitaIva : $codiceFiscale, ':id' => $excludeId]);
    if ($stmt->fetchColumn()) {
        return [$field === 'partita_iva'
            ? 'Azienda già censita a sistema con questa Partita IVA.'
            : 'Azienda già censita a sistema con questo Codice Fiscale.'];
    }
    return [];
}

function companyRcoUsers(PDO $pdo): array
{
    return $pdo->query("SELECT u.id, u.nome, u.cognome FROM utenti u
        WHERE u.ruolo IN ('Amministratore', 'Responsabile di Area', 'Consulente')
           OR EXISTS (SELECT 1 FROM utenti_ruoli ur WHERE ur.utente_id = u.id
               AND ur.ruolo IN ('Amministratore', 'Responsabile di Area', 'Consulente'))
        ORDER BY u.nome, u.cognome")->fetchAll();
}
