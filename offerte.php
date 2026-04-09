<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

$SERVIZI = [
    'SISTEMI DI GESTIONE AZIENDALE',
    'SICUREZZA',
    'FORMAZIONE',
    'FINANZA AGEVOLATA',
    'CONSULENZA SOA',
    'ALTRE CONSULENZE',
];

$DETTAGLI_SERVIZIO = [
    'SISTEMI DI GESTIONE AZIENDALE' => [
        'ISO 9001', 'MANT ISO 9001', 'ISO 14001', 'MANT ISO 14001', 'EMAS', 'MANTENIMENTO EMAS', 'ISO 45001',
        'MANT ISO 45001', 'SA 8000', 'MANT SA8000', 'ISO 50000', 'MANT ISO 50000', 'ISO 27001', 'MANT ISO 27001',
        'ISO 27017', 'MANT ISO 27017', 'ISO 27018', 'MANT ISO 27018', 'ISO 42000', 'MANT ISO 42000', 'ISO 37001',
        'MANT ISO 37001', 'ISO 39001', 'MANT 39001', 'ISO 22000', 'MANT ISO 22000', 'ISO 22005', 'MANT ISO 22005',
        'ISO 1090', 'MANT ISO 1090', 'SISTEMA INTEGRATO', 'MANTENIMENTO SISTEMA INTEGRATO', 'HALAL',
        'MANTENIMENTO HALAL', 'GLOBAL GAP', 'MANTENIMENTO GLOBAL GAP', 'BIOLOGICO', 'MANTENIMENTO BIOLOGICO',
        'MARC CE', 'FPC CLS', 'MANTENIMENTO FPC CLS', 'MANTENIMENTO MAR CE', 'BRC', 'MANTENIMENTO BRC AEO',
        'Parità di genere', 'MANTENIMENTO Parità di genere', 'MODELLO 231', 'ODV 231', 'PRIVACY (GDPR)', 'HACCP',
        'MANT HACCP', 'ANALISI TAMPONE HACCP',
    ],
    'SICUREZZA' => ['DVR (D. Lgs. 81/2008)', 'AGGIORNAMENTO DVR', 'MISURAZIONI TECNICHE', 'VISITE MEDICHE'],
    'FORMAZIONE' => ['FORMAZIONE D. Lgs. 81/2008', 'Formazione Profili ENEL', 'ALTRE ATTIVITA’ DI FORMAZIONE'],
    'FINANZA AGEVOLATA' => ['REGIONE CALABRIA', 'INVITALIA', 'MINISTERO', 'ALTRO'],
    'CONSULENZA SOA' => ['NUOVA ATTESTAZIONE', 'VERIFICA TRIENNALE', 'VERIFICA QUINQUENNALE', 'VARIAZIONE', 'ALTRO'],
    'ALTRE CONSULENZE' => ['MARKETING', 'PIANI STRATEGICI', 'CONTROLLO DI GESTIONE', 'ALTRO'],
];

$MODALITA_PAGAMENTO = [
    '30-60 FMDF',
    'Bonifico Bancario f.m.v.f',
    'da definire',
    'RI.BA.',
    'RID',
    'rid 06 mensilità',
    'rimessa diretta',
    'Rimessa Diretta',
];

$errors = [];
$success = null;

$totaleUtenti = (int) $pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn();
if (!isLoggedIn()) {
    if ($totaleUtenti > 0) {
        header('Location: login.php');
    } else {
        header('Location: utenti.php');
    }
    exit;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS offerte (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        protocollo_numero INT UNSIGNED NOT NULL,
        anno_riferimento YEAR NOT NULL,
        protocollo VARCHAR(20) NOT NULL UNIQUE,
        servizio VARCHAR(80) NOT NULL,
        tipo_dettaglio VARCHAR(30) NOT NULL,
        dettaglio_servizio VARCHAR(120) NOT NULL,
        specifiche_oggetto TEXT NULL,
        sede_erogazione_servizio VARCHAR(255) NULL,
        rco_utente_id INT UNSIGNED NULL,
        segnalato_da_utente_id INT UNSIGNED NULL,
        data_offerta DATE NULL,
        validita_giorni INT UNSIGNED NULL,
        data_scadenza DATE NULL,
        note TEXT NULL,
        promotore_azienda_id INT UNSIGNED NULL,
        commissione_tipo VARCHAR(20) NULL,
        commissione_valore DECIMAL(10,2) NULL,
        modalita_pagamento VARCHAR(80) NULL,
        sconto_percentuale DECIMAL(5,2) NULL,
        descrizione TEXT NULL,
        creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_protocollo_anno (protocollo_numero, anno_riferimento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$columnMigrations = [
    'specifiche_oggetto' => 'ALTER TABLE offerte ADD COLUMN specifiche_oggetto TEXT NULL AFTER dettaglio_servizio',
    'sede_erogazione_servizio' => 'ALTER TABLE offerte ADD COLUMN sede_erogazione_servizio VARCHAR(255) NULL AFTER specifiche_oggetto',
    'rco_utente_id' => 'ALTER TABLE offerte ADD COLUMN rco_utente_id INT UNSIGNED NULL AFTER sede_erogazione_servizio',
    'segnalato_da_utente_id' => 'ALTER TABLE offerte ADD COLUMN segnalato_da_utente_id INT UNSIGNED NULL AFTER rco_utente_id',
    'data_offerta' => 'ALTER TABLE offerte ADD COLUMN data_offerta DATE NULL AFTER segnalato_da_utente_id',
    'validita_giorni' => 'ALTER TABLE offerte ADD COLUMN validita_giorni INT UNSIGNED NULL AFTER data_offerta',
    'data_scadenza' => 'ALTER TABLE offerte ADD COLUMN data_scadenza DATE NULL AFTER validita_giorni',
    'note' => 'ALTER TABLE offerte ADD COLUMN note TEXT NULL AFTER data_scadenza',
    'promotore_azienda_id' => 'ALTER TABLE offerte ADD COLUMN promotore_azienda_id INT UNSIGNED NULL AFTER note',
    'commissione_tipo' => 'ALTER TABLE offerte ADD COLUMN commissione_tipo VARCHAR(20) NULL AFTER promotore_azienda_id',
    'commissione_valore' => 'ALTER TABLE offerte ADD COLUMN commissione_valore DECIMAL(10,2) NULL AFTER commissione_tipo',
    'modalita_pagamento' => 'ALTER TABLE offerte ADD COLUMN modalita_pagamento VARCHAR(80) NULL AFTER commissione_valore',
    'sconto_percentuale' => 'ALTER TABLE offerte ADD COLUMN sconto_percentuale DECIMAL(5,2) NULL AFTER modalita_pagamento',
];

foreach ($columnMigrations as $column => $sqlAlter) {
    $exists = (bool) $pdo->query("SHOW COLUMNS FROM offerte LIKE '{$column}'")->fetch();
    if (!$exists) {
        $pdo->exec($sqlAlter);
    }
}

$utenti = $pdo->query('SELECT id, nome, cognome FROM utenti ORDER BY nome, cognome')->fetchAll();
$aziendePromotori = [];
$hasAziendeTable = (bool) $pdo->query("SHOW TABLES LIKE 'aziende'")->fetchColumn();
if ($hasAziendeTable) {
    $aziendePromotori = $pdo->query("SELECT id, ragione_sociale FROM aziende WHERE FIND_IN_SET('Promotore', tipologia_azienda) > 0 ORDER BY ragione_sociale")->fetchAll();
}

$action = $_GET['action'] ?? 'list';
$viewId = isset($_GET['view']) ? (int) $_GET['view'] : 0;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$offertaInModifica = null;
$offertaInVisualizzazione = null;

if ($viewId > 0) {
    $stmt = $pdo->prepare('SELECT o.*, CONCAT(r.nome, " ", r.cognome) AS rco_nome, CONCAT(s.nome, " ", s.cognome) AS segnalato_nome, p.ragione_sociale AS promotore_nome
                           FROM offerte o
                           LEFT JOIN utenti r ON r.id = o.rco_utente_id
                           LEFT JOIN utenti s ON s.id = o.segnalato_da_utente_id
                           LEFT JOIN aziende p ON p.id = o.promotore_azienda_id
                           WHERE o.id = :id');
    $stmt->execute([':id' => $viewId]);
    $offertaInVisualizzazione = $stmt->fetch();
}

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM offerte WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $offertaInModifica = $stmt->fetch();
    if (!$offertaInModifica) {
        $errors[] = 'Offerta da modificare non trovata.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'delete') {
        $idDelete = (int) ($_POST['id'] ?? 0);
        if ($idDelete > 0) {
            $stmt = $pdo->prepare('DELETE FROM offerte WHERE id = :id');
            $stmt->execute([':id' => $idDelete]);
            $success = 'Offerta eliminata correttamente.';
        }
    }

    if ($azione === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $servizio = trim($_POST['servizio'] ?? '');
        $dettaglioServizio = trim($_POST['dettaglio_servizio'] ?? '');
        $specificheOggetto = trim($_POST['specifiche_oggetto'] ?? '');
        $sedeErogazione = trim($_POST['sede_erogazione_servizio'] ?? '');
        $rcoUtenteId = (int) ($_POST['rco_utente_id'] ?? 0);
        $segnalatoDaUtenteId = ($_POST['segnalato_da_utente_id'] ?? '') !== '' ? (int) $_POST['segnalato_da_utente_id'] : null;
        $dataOfferta = trim($_POST['data_offerta'] ?? '');
        $validitaGiorniInput = trim($_POST['validita_giorni'] ?? '');
        $dataScadenza = trim($_POST['data_scadenza'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $promotoreAziendaId = ($_POST['promotore_azienda_id'] ?? '') !== '' ? (int) $_POST['promotore_azienda_id'] : null;
        $commissioneTipo = trim($_POST['commissione_tipo'] ?? '');
        $commissioneValoreInput = trim($_POST['commissione_valore'] ?? '');
        $modalitaPagamento = trim($_POST['modalita_pagamento'] ?? '');
        $scontoInput = trim($_POST['sconto_percentuale'] ?? '');

        if (!in_array($servizio, $SERVIZI, true)) {
            $errors[] = 'Servizio non valido.';
        }
        $opzioniDettaglio = $DETTAGLI_SERVIZIO[$servizio] ?? [];
        if (!$opzioniDettaglio || !in_array($dettaglioServizio, $opzioniDettaglio, true)) {
            $errors[] = 'Campo dettaglio servizio non valido o non coerente con il servizio scelto.';
        }

        if ($rcoUtenteId <= 0) {
            $errors[] = 'Il campo RCO è obbligatorio.';
        }

        if ($dataOfferta === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataOfferta)) {
            $errors[] = 'Data Offerta obbligatoria e non valida.';
        }

        $validitaGiorni = $validitaGiorniInput !== '' ? (int) $validitaGiorniInput : 0;
        if ($validitaGiorniInput !== '' && $validitaGiorni <= 0) {
            $errors[] = 'Validità deve essere un numero di giorni maggiore di zero.';
        }

        if ($dataScadenza !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataScadenza)) {
            $errors[] = 'Data di Scadenza non valida.';
        }

        if (!$errors) {
            $dateOffertaObj = new DateTimeImmutable($dataOfferta);

            if ($validitaGiorni > 0 && $dataScadenza === '') {
                $dataScadenza = $dateOffertaObj->modify('+' . $validitaGiorni . ' days')->format('Y-m-d');
            }

            if ($dataScadenza !== '' && $validitaGiorni === 0) {
                $dateScadenzaObj = new DateTimeImmutable($dataScadenza);
                $diff = $dateOffertaObj->diff($dateScadenzaObj);
                $validitaGiorni = (int) $diff->format('%r%a');
                if ($validitaGiorni <= 0) {
                    $errors[] = 'Data di Scadenza deve essere successiva alla Data Offerta.';
                }
            }

            if ($dataScadenza !== '' && $validitaGiorni > 0) {
                $dateScadenzaCalcolata = $dateOffertaObj->modify('+' . $validitaGiorni . ' days')->format('Y-m-d');
                if ($dateScadenzaCalcolata !== $dataScadenza) {
                    $dataScadenza = $dateScadenzaCalcolata;
                }
            }

            if ($validitaGiorni <= 0) {
                $errors[] = 'Il campo Validità è obbligatorio.';
            }
        }

        if (!in_array($modalitaPagamento, $MODALITA_PAGAMENTO, true)) {
            $errors[] = 'Modalità di Pagamento non valida.';
        }

        if ($commissioneTipo !== '' && !in_array($commissioneTipo, ['percentuale', 'euro'], true)) {
            $errors[] = 'Tipo commissione non valido.';
        }

        $commissioneValore = null;
        if ($commissioneValoreInput !== '') {
            $commissioneValore = (float) str_replace(',', '.', $commissioneValoreInput);
            if ($commissioneValore < 0) {
                $errors[] = 'Commissione non valida.';
            }
        }

        $sconto = (float) str_replace(',', '.', $scontoInput);
        if ($scontoInput === '' || $sconto < 0 || $sconto > 100) {
            $errors[] = '% Sconto obbligatorio e compreso tra 0 e 100.';
        }

        $tipoDettaglio = $servizio === 'SISTEMI DI GESTIONE AZIENDALE' ? 'SottoServizio' : 'Servizio Specifico';

        if (!$errors) {
            $params = [
                ':servizio' => $servizio,
                ':tipo_dettaglio' => $tipoDettaglio,
                ':dettaglio_servizio' => $dettaglioServizio,
                ':specifiche_oggetto' => $specificheOggetto !== '' ? $specificheOggetto : null,
                ':sede_erogazione_servizio' => $sedeErogazione !== '' ? $sedeErogazione : null,
                ':rco_utente_id' => $rcoUtenteId,
                ':segnalato_da_utente_id' => $segnalatoDaUtenteId,
                ':data_offerta' => $dataOfferta,
                ':validita_giorni' => $validitaGiorni,
                ':data_scadenza' => $dataScadenza !== '' ? $dataScadenza : null,
                ':note' => $note !== '' ? $note : null,
                ':promotore_azienda_id' => $promotoreAziendaId,
                ':commissione_tipo' => $commissioneTipo !== '' ? $commissioneTipo : null,
                ':commissione_valore' => $commissioneValore,
                ':modalita_pagamento' => $modalitaPagamento,
                ':sconto_percentuale' => number_format($sconto, 2, '.', ''),
            ];

            if ($id > 0) {
                $sql = 'UPDATE offerte
                        SET servizio = :servizio,
                            tipo_dettaglio = :tipo_dettaglio,
                            dettaglio_servizio = :dettaglio_servizio,
                            specifiche_oggetto = :specifiche_oggetto,
                            sede_erogazione_servizio = :sede_erogazione_servizio,
                            rco_utente_id = :rco_utente_id,
                            segnalato_da_utente_id = :segnalato_da_utente_id,
                            data_offerta = :data_offerta,
                            validita_giorni = :validita_giorni,
                            data_scadenza = :data_scadenza,
                            note = :note,
                            promotore_azienda_id = :promotore_azienda_id,
                            commissione_tipo = :commissione_tipo,
                            commissione_valore = :commissione_valore,
                            modalita_pagamento = :modalita_pagamento,
                            sconto_percentuale = :sconto_percentuale
                        WHERE id = :id';
                $params[':id'] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = 'Offerta aggiornata correttamente.';
                $editId = 0;
                $action = 'list';
                $offertaInModifica = null;
            } else {
                $anno = (int) date('Y');
                $stmtNum = $pdo->prepare('SELECT COALESCE(MAX(protocollo_numero), 0) + 1 FROM offerte WHERE anno_riferimento = :anno');
                $stmtNum->execute([':anno' => $anno]);
                $numeroProgressivo = (int) $stmtNum->fetchColumn();
                $protocollo = $numeroProgressivo . '/' . $anno;

                $sql = 'INSERT INTO offerte (
                            protocollo_numero, anno_riferimento, protocollo, servizio, tipo_dettaglio, dettaglio_servizio,
                            specifiche_oggetto, sede_erogazione_servizio, rco_utente_id, segnalato_da_utente_id,
                            data_offerta, validita_giorni, data_scadenza, note, promotore_azienda_id,
                            commissione_tipo, commissione_valore, modalita_pagamento, sconto_percentuale
                        ) VALUES (
                            :protocollo_numero, :anno_riferimento, :protocollo, :servizio, :tipo_dettaglio, :dettaglio_servizio,
                            :specifiche_oggetto, :sede_erogazione_servizio, :rco_utente_id, :segnalato_da_utente_id,
                            :data_offerta, :validita_giorni, :data_scadenza, :note, :promotore_azienda_id,
                            :commissione_tipo, :commissione_valore, :modalita_pagamento, :sconto_percentuale
                        )';

                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute($params + [
                        ':protocollo_numero' => $numeroProgressivo,
                        ':anno_riferimento' => $anno,
                        ':protocollo' => $protocollo,
                    ]);
                    $success = 'Offerta creata correttamente con protocollo: ' . $protocollo;
                    $action = 'list';
                } catch (PDOException $e) {
                    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                        $errors[] = 'Conflitto su protocollo progressivo, riprovare.';
                    } else {
                        $errors[] = 'Errore durante il salvataggio dell\'offerta.';
                    }
                }
            }
        }
    }
}

$filters = [
    'protocollo', 'servizio', 'tipo_dettaglio', 'dettaglio_servizio', 'specifiche_oggetto', 'sede_erogazione_servizio',
    'rco_utente_id', 'segnalato_da_utente_id', 'data_offerta', 'validita_giorni', 'data_scadenza', 'note',
    'promotore_azienda_id', 'commissione_tipo', 'commissione_valore', 'modalita_pagamento', 'sconto_percentuale',
    'anno_riferimento',
];

$where = [];
$params = [];
foreach ($filters as $field) {
    $key = 'f_' . $field;
    $value = trim((string) ($_GET[$key] ?? ''));
    if ($value === '') {
        continue;
    }

    if (in_array($field, ['rco_utente_id', 'segnalato_da_utente_id', 'promotore_azienda_id', 'anno_riferimento', 'validita_giorni'], true)) {
        $where[] = "o.$field = :$key";
        $params[":$key"] = (int) $value;
    } else {
        $where[] = "o.$field LIKE :$key";
        $params[":$key"] = '%' . $value . '%';
    }
}

$sqlList = 'SELECT o.*, CONCAT(r.nome, " ", r.cognome) AS rco_nome, CONCAT(s.nome, " ", s.cognome) AS segnalato_nome, p.ragione_sociale AS promotore_nome
            FROM offerte o
            LEFT JOIN utenti r ON r.id = o.rco_utente_id
            LEFT JOIN utenti s ON s.id = o.segnalato_da_utente_id
            LEFT JOIN aziende p ON p.id = o.promotore_azienda_id';
if ($where) {
    $sqlList .= ' WHERE ' . implode(' AND ', $where);
}
$sqlList .= ' ORDER BY o.id DESC';
$stmtList = $pdo->prepare($sqlList);
$stmtList->execute($params);
$offerte = $stmtList->fetchAll();

$utenteLoggato = currentUser();
$formData = $offertaInModifica ?: [];

renderHeader('Simplex - Offerte');
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2 mb-3">
                <li class="nav-item"><a class="nav-link" href="utenti.php">Utenti</a></li>
                <li class="nav-item"><a class="nav-link" href="aziende.php">Aziende</a></li>
                <li class="nav-item"><a class="nav-link active" href="offerte.php">Offerte</a></li>
                <li class="nav-item"><a class="nav-link disabled" href="#">Impostazioni</a></li>
            </ul>
            <?php if ($utenteLoggato): ?>
                <div class="text-white small border-top pt-3">
                    <div>Connesso come:</div>
                    <strong><?= htmlspecialchars($utenteLoggato['nome_completo']) ?></strong><br>
                    <span class="text-light-emphasis"><?= htmlspecialchars($utenteLoggato['nome_utente']) ?></span>
                    <div class="mt-2"><a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a></div>
                </div>
            <?php endif; ?>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Gestione Offerte</h2>
                <a class="btn btn-primary" href="offerte.php?action=new">Nuova Offerta</a>
            </div>

            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

            <?php if ($action === 'new' || $editId > 0): ?>
                <div class="card mb-4">
                    <div class="card-header"><?= $editId > 0 ? 'Modifica Offerta' : 'Nuova Offerta' ?></div>
                    <div class="card-body">
                        <form method="post" class="row g-3" id="form-offerta">
                            <input type="hidden" name="azione" value="save">
                            <input type="hidden" name="id" value="<?= (int)($formData['id'] ?? 0) ?>">

                            <div class="col-md-6">
                                <label class="form-label">Servizio *</label>
                                <select class="form-select" name="servizio" id="servizio" required>
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($SERVIZI as $servizio): ?>
                                        <option value="<?= htmlspecialchars($servizio) ?>" <?= (($formData['servizio'] ?? '') === $servizio) ? 'selected' : '' ?>><?= htmlspecialchars($servizio) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" id="label-dettaglio">Dettaglio *</label>
                                <select class="form-select" name="dettaglio_servizio" id="dettaglio_servizio" required></select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Specifiche Oggetto</label>
                                <textarea class="form-control" name="specifiche_oggetto" rows="3"><?= htmlspecialchars($formData['specifiche_oggetto'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sede di erogazione del servizio:</label>
                                <input class="form-control" name="sede_erogazione_servizio" value="<?= htmlspecialchars($formData['sede_erogazione_servizio'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">RCO *</label>
                                <select class="form-select" name="rco_utente_id" required>
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($utenti as $utente): ?>
                                        <option value="<?= (int)$utente['id'] ?>" <?= ((int)($formData['rco_utente_id'] ?? 0) === (int)$utente['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Segnalato da</label>
                                <select class="form-select" name="segnalato_da_utente_id">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($utenti as $utente): ?>
                                        <option value="<?= (int)$utente['id'] ?>" <?= ((int)($formData['segnalato_da_utente_id'] ?? 0) === (int)$utente['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Data Offerta *</label>
                                <input type="date" class="form-control" name="data_offerta" id="data_offerta" value="<?= htmlspecialchars($formData['data_offerta'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Validità (giorni) *</label>
                                <input type="number" min="1" class="form-control" name="validita_giorni" id="validita_giorni" value="<?= htmlspecialchars($formData['validita_giorni'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Data di Scadenza</label>
                                <input type="date" class="form-control" name="data_scadenza" id="data_scadenza" value="<?= htmlspecialchars($formData['data_scadenza'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Promotore</label>
                                <select class="form-select" name="promotore_azienda_id">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($aziendePromotori as $aziendaPromotore): ?>
                                        <option value="<?= (int)$aziendaPromotore['id'] ?>" <?= ((int)($formData['promotore_azienda_id'] ?? 0) === (int)$aziendaPromotore['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($aziendaPromotore['ragione_sociale']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Commissione</label>
                                <select class="form-select" name="commissione_tipo">
                                    <option value="">-- Nessuna --</option>
                                    <option value="percentuale" <?= (($formData['commissione_tipo'] ?? '') === 'percentuale') ? 'selected' : '' ?>>Percentuale</option>
                                    <option value="euro" <?= (($formData['commissione_tipo'] ?? '') === 'euro') ? 'selected' : '' ?>>Euro</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Valore Commissione</label>
                                <input class="form-control" name="commissione_valore" value="<?= htmlspecialchars($formData['commissione_valore'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Modalità di Pagamento</label>
                                <select class="form-select" name="modalita_pagamento" required>
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($MODALITA_PAGAMENTO as $modalita): ?>
                                        <option value="<?= htmlspecialchars($modalita) ?>" <?= (($formData['modalita_pagamento'] ?? '') === $modalita) ? 'selected' : '' ?>><?= htmlspecialchars($modalita) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">% Sconto (0-100)</label>
                                <input type="number" min="0" max="100" step="0.01" class="form-control" name="sconto_percentuale" required value="<?= htmlspecialchars($formData['sconto_percentuale'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Note</label>
                                <textarea class="form-control" name="note" rows="3"><?= htmlspecialchars($formData['note'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary" type="submit">Salva Offerta</button>
                                <a class="btn btn-outline-secondary" href="offerte.php">Annulla</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($offertaInVisualizzazione): ?>
                <div class="card mb-4">
                    <div class="card-header">Dettaglio Offerta</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($offertaInVisualizzazione as $campo => $valore): ?>
                                <div class="col-md-4"><strong><?= htmlspecialchars((string) $campo) ?>:</strong> <?= htmlspecialchars((string) ($valore ?? '-')) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">Filtri Offerte</div>
                <div class="card-body">
                    <form method="get" class="row g-2">
                        <div class="col-md-2"><input class="form-control" name="f_protocollo" placeholder="Protocollo" value="<?= htmlspecialchars($_GET['f_protocollo'] ?? '') ?>"></div>
                        <div class="col-md-3">
                            <select class="form-select" name="f_servizio">
                                <option value="">Servizio</option>
                                <?php foreach ($SERVIZI as $servizio): ?>
                                    <option value="<?= htmlspecialchars($servizio) ?>" <?= (($_GET['f_servizio'] ?? '') === $servizio) ? 'selected' : '' ?>><?= htmlspecialchars($servizio) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><input class="form-control" name="f_tipo_dettaglio" placeholder="Tipo dettaglio" value="<?= htmlspecialchars($_GET['f_tipo_dettaglio'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_dettaglio_servizio" placeholder="Dettaglio" value="<?= htmlspecialchars($_GET['f_dettaglio_servizio'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_anno_riferimento" placeholder="Anno" value="<?= htmlspecialchars($_GET['f_anno_riferimento'] ?? '') ?>"></div>

                        <div class="col-md-3"><input class="form-control" name="f_sede_erogazione_servizio" placeholder="Sede erogazione" value="<?= htmlspecialchars($_GET['f_sede_erogazione_servizio'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_data_offerta" placeholder="Data offerta YYYY-MM-DD" value="<?= htmlspecialchars($_GET['f_data_offerta'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_validita_giorni" placeholder="Validità" value="<?= htmlspecialchars($_GET['f_validita_giorni'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_data_scadenza" placeholder="Data scadenza YYYY-MM-DD" value="<?= htmlspecialchars($_GET['f_data_scadenza'] ?? '') ?>"></div>
                        <div class="col-md-1"><input class="form-control" name="f_sconto_percentuale" placeholder="%" value="<?= htmlspecialchars($_GET['f_sconto_percentuale'] ?? '') ?>"></div>

                        <div class="col-md-4"><input class="form-control" name="f_specifiche_oggetto" placeholder="Specifiche Oggetto" value="<?= htmlspecialchars($_GET['f_specifiche_oggetto'] ?? '') ?>"></div>
                        <div class="col-md-4"><input class="form-control" name="f_note" placeholder="Note" value="<?= htmlspecialchars($_GET['f_note'] ?? '') ?>"></div>
                        <div class="col-md-4"><input class="form-control" name="f_modalita_pagamento" placeholder="Modalità pagamento" value="<?= htmlspecialchars($_GET['f_modalita_pagamento'] ?? '') ?>"></div>

                        <div class="col-md-4">
                            <select class="form-select" name="f_rco_utente_id">
                                <option value="">RCO</option>
                                <?php foreach ($utenti as $utente): ?>
                                    <option value="<?= (int)$utente['id'] ?>" <?= ((string)($_GET['f_rco_utente_id'] ?? '') === (string)$utente['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="f_segnalato_da_utente_id">
                                <option value="">Segnalato da</option>
                                <?php foreach ($utenti as $utente): ?>
                                    <option value="<?= (int)$utente['id'] ?>" <?= ((string)($_GET['f_segnalato_da_utente_id'] ?? '') === (string)$utente['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="f_promotore_azienda_id">
                                <option value="">Promotore</option>
                                <?php foreach ($aziendePromotori as $aziendaPromotore): ?>
                                    <option value="<?= (int)$aziendaPromotore['id'] ?>" <?= ((string)($_GET['f_promotore_azienda_id'] ?? '') === (string)$aziendaPromotore['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($aziendaPromotore['ragione_sociale']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3"><input class="form-control" name="f_commissione_tipo" placeholder="Tipo commissione" value="<?= htmlspecialchars($_GET['f_commissione_tipo'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_commissione_valore" placeholder="Valore commissione" value="<?= htmlspecialchars($_GET['f_commissione_valore'] ?? '') ?>"></div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-outline-primary" type="submit">Filtra</button>
                            <a class="btn btn-outline-secondary" href="offerte.php">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco Offerte</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Protocollo</th>
                            <th>Servizio</th>
                            <th>Dettaglio</th>
                            <th>Data Offerta</th>
                            <th>Validità</th>
                            <th>Scadenza</th>
                            <th>RCO</th>
                            <th>% Sconto</th>
                            <th>Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$offerte): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Nessuna offerta trovata.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($offerte as $offerta): ?>
                            <tr>
                                <td><?= htmlspecialchars($offerta['protocollo']) ?></td>
                                <td><?= htmlspecialchars($offerta['servizio']) ?></td>
                                <td><?= htmlspecialchars($offerta['dettaglio_servizio']) ?></td>
                                <td><?= htmlspecialchars($offerta['data_offerta'] ?? '-') ?></td>
                                <td><?= htmlspecialchars((string)($offerta['validita_giorni'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars($offerta['data_scadenza'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(trim((string)($offerta['rco_nome'] ?? '')) ?: '-') ?></td>
                                <td><?= htmlspecialchars((string)($offerta['sconto_percentuale'] ?? '-')) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-secondary" href="offerte.php?view=<?= (int)$offerta['id'] ?>">Visualizza</a>
                                        <a class="btn btn-sm btn-outline-primary" href="offerte.php?edit=<?= (int)$offerta['id'] ?>">Modifica</a>
                                        <a class="btn btn-sm btn-outline-dark" href="lavorazioni.php?offerta_id=<?= (int)$offerta['id'] ?>">Lavorazioni</a>
                                        <form method="post" onsubmit="return confirm('Confermi eliminazione offerta?');">
                                            <input type="hidden" name="azione" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$offerta['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Elimina</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
const dettagliPerServizio = <?= json_encode($DETTAGLI_SERVIZIO, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const servizioSelect = document.getElementById('servizio');
const dettaglioSelect = document.getElementById('dettaglio_servizio');
const labelDettaglio = document.getElementById('label-dettaglio');
const dettaglioPreselezionato = <?= json_encode($formData['dettaglio_servizio'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function renderDettaglioOptions() {
    if (!servizioSelect || !dettaglioSelect || !labelDettaglio) return;
    const servizio = servizioSelect.value;
    const opzioni = dettagliPerServizio[servizio] || [];
    labelDettaglio.textContent = servizio === 'SISTEMI DI GESTIONE AZIENDALE' ? 'SottoServizio *' : 'Servizio Specifico *';

    dettaglioSelect.innerHTML = '';
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = '-- Seleziona --';
    dettaglioSelect.appendChild(defaultOpt);

    opzioni.forEach((val) => {
        const opt = document.createElement('option');
        opt.value = val;
        opt.textContent = val;
        if (dettaglioPreselezionato === val) opt.selected = true;
        dettaglioSelect.appendChild(opt);
    });
}

const dataOffertaInput = document.getElementById('data_offerta');
const validitaInput = document.getElementById('validita_giorni');
const dataScadenzaInput = document.getElementById('data_scadenza');

function addDays(dateString, days) {
    const date = new Date(dateString + 'T00:00:00');
    date.setDate(date.getDate() + Number(days));
    return date.toISOString().slice(0, 10);
}

function daysBetween(start, end) {
    const d1 = new Date(start + 'T00:00:00');
    const d2 = new Date(end + 'T00:00:00');
    return Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
}

if (servizioSelect) {
    servizioSelect.addEventListener('change', renderDettaglioOptions);
    renderDettaglioOptions();
}

if (dataOffertaInput && validitaInput && dataScadenzaInput) {
    validitaInput.addEventListener('input', () => {
        if (dataOffertaInput.value && validitaInput.value) {
            dataScadenzaInput.value = addDays(dataOffertaInput.value, validitaInput.value);
        }
    });

    dataScadenzaInput.addEventListener('change', () => {
        if (dataOffertaInput.value && dataScadenzaInput.value) {
            const diff = daysBetween(dataOffertaInput.value, dataScadenzaInput.value);
            if (diff > 0) validitaInput.value = diff;
        }
    });
}
</script>
<?php renderFooter(); ?>
