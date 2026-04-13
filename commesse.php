<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS commesse (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offerta_id INT UNSIGNED NOT NULL UNIQUE,
    protocollo_numero INT UNSIGNED NOT NULL,
    anno_riferimento YEAR NOT NULL,
    protocollo VARCHAR(20) NOT NULL UNIQUE,
    consulente_codice VARCHAR(2) NOT NULL,
    consulente_nome VARCHAR(100) NOT NULL,
    data_rali DATE NULL,
    dtg_utente_id INT UNSIGNED NULL,
    budget DECIMAL(12,2) NULL,
    ente_certificazione_id INT UNSIGNED NULL,
    importo_ente_certificazione DECIMAL(12,2) NULL,
    azienda_cliente_id INT UNSIGNED NULL,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_commesse_num_anno (protocollo_numero, anno_riferimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS commesse_file (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commessa_id INT UNSIGNED NOT NULL,
    nome_originale VARCHAR(255) NOT NULL,
    nome_salvato VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    dimensione_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    caricato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_commessa_file_commessa FOREIGN KEY (commessa_id) REFERENCES commesse(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS commessa_momenti_lavorazione (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commessa_id INT UNSIGNED NOT NULL,
    data_momento DATE NOT NULL,
    tipologia ENUM('Apertura', 'Chiusura') NOT NULL,
    valore_giornaliero_uomo DECIMAL(12,2) NOT NULL,
    ore DECIMAL(8,2) NOT NULL DEFAULT 0,
    giorni DECIMAL(8,2) NOT NULL DEFAULT 0,
    numero_incontri INT UNSIGNED NOT NULL DEFAULT 0,
    ore_studio VARCHAR(5) NULL,
    data_prevista DATE NULL,
    completato TINYINT(1) NOT NULL DEFAULT 0,
    completato_il DATETIME NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_momento_commessa FOREIGN KEY (commessa_id) REFERENCES commesse(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS offerta_momenti_lavorazione (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offerta_id INT UNSIGNED NOT NULL,
    data_momento DATE NOT NULL,
    tipologia ENUM('Apertura', 'Chiusura') NOT NULL,
    valore_giornaliero_uomo DECIMAL(12,2) NOT NULL,
    ore DECIMAL(8,2) NOT NULL DEFAULT 0,
    giorni DECIMAL(8,2) NOT NULL DEFAULT 0,
    numero_incontri INT UNSIGNED NOT NULL DEFAULT 0,
    ore_studio VARCHAR(5) NULL,
    data_prevista DATE NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS fatture (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commessa_id INT UNSIGNED NOT NULL,
    momento_id INT UNSIGNED NOT NULL UNIQUE,
    numero VARCHAR(30) NOT NULL UNIQUE,
    anno_riferimento YEAR NOT NULL,
    importo DECIMAL(12,2) NOT NULL,
    pagata TINYINT(1) NOT NULL DEFAULT 0,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fattura_commessa FOREIGN KEY (commessa_id) REFERENCES commesse(id) ON DELETE CASCADE,
    CONSTRAINT fk_fattura_momento FOREIGN KEY (momento_id) REFERENCES commessa_momenti_lavorazione(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pagamenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fattura_id INT UNSIGNED NOT NULL UNIQUE,
    data_pagamento DATE NOT NULL,
    modalita_pagamento ENUM('Contanti', 'Bonifico', 'Carta di Credito') NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagamento_fattura FOREIGN KEY (fattura_id) REFERENCES fatture(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$migrations = [
    'data_rali' => 'ALTER TABLE commesse ADD COLUMN data_rali DATE NULL AFTER consulente_nome',
    'dtg_utente_id' => 'ALTER TABLE commesse ADD COLUMN dtg_utente_id INT UNSIGNED NULL AFTER data_rali',
    'budget' => 'ALTER TABLE commesse ADD COLUMN budget DECIMAL(12,2) NULL AFTER dtg_utente_id',
    'ente_certificazione_id' => 'ALTER TABLE commesse ADD COLUMN ente_certificazione_id INT UNSIGNED NULL AFTER budget',
    'importo_ente_certificazione' => 'ALTER TABLE commesse ADD COLUMN importo_ente_certificazione DECIMAL(12,2) NULL AFTER ente_certificazione_id',
    'azienda_cliente_id' => 'ALTER TABLE commesse ADD COLUMN azienda_cliente_id INT UNSIGNED NULL AFTER importo_ente_certificazione',
    'completato' => 'ALTER TABLE commessa_momenti_lavorazione ADD COLUMN completato TINYINT(1) NOT NULL DEFAULT 0 AFTER data_prevista',
    'completato_il' => 'ALTER TABLE commessa_momenti_lavorazione ADD COLUMN completato_il DATETIME NULL AFTER completato',
];
foreach ($migrations as $column => $sqlAlter) {
    $table = in_array($column, ['completato', 'completato_il'], true) ? 'commessa_momenti_lavorazione' : 'commesse';
    $exists = (bool) $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'")->fetch();
    if (!$exists) {
        $pdo->exec($sqlAlter);
    }
}

$utenti = $pdo->query('SELECT id, nome, cognome FROM utenti ORDER BY nome, cognome')->fetchAll();
$aziende = [];
if ((bool) $pdo->query("SHOW TABLES LIKE 'aziende'")->fetchColumn()) {
    $aziende = $pdo->query('SELECT id, ragione_sociale FROM aziende ORDER BY ragione_sociale')->fetchAll();
}
$entiCertificazione = [];
if ((bool) $pdo->query("SHOW TABLES LIKE 'enti_certificazione'")->fetchColumn()) {
    $entiCertificazione = $pdo->query('SELECT id, denominazione FROM enti_certificazione ORDER BY denominazione')->fetchAll();
}

$errors = [];
$success = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$commessaInModifica = null;
$filesCommessa = [];
$momentiCommessa = [];
$totaleMomentiEuro = 0.0;
$fatturePerMomento = [];

if ($editId > 0) {
    $stmtEdit = $pdo->prepare(
        'SELECT c.*, o.servizio AS offerta_servizio
         FROM commesse c
         LEFT JOIN offerte o ON o.id = c.offerta_id
         WHERE c.id = :id'
    );
    $stmtEdit->execute([':id' => $editId]);
    $commessaInModifica = $stmtEdit->fetch();

    if (!$commessaInModifica) {
        $errors[] = 'Commessa non trovata.';
        $editId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $dataRali = trim($_POST['data_rali'] ?? '');
        $dtgUtenteId = ($_POST['dtg_utente_id'] ?? '') !== '' ? (int) $_POST['dtg_utente_id'] : null;
        $budgetRaw = str_replace(',', '.', trim($_POST['budget'] ?? ''));
        $enteCertificazioneId = ($_POST['ente_certificazione_id'] ?? '') !== '' ? (int) $_POST['ente_certificazione_id'] : null;
        $importoEnteRaw = str_replace(',', '.', trim($_POST['importo_ente_certificazione'] ?? ''));
        $aziendaClienteId = ($_POST['azienda_cliente_id'] ?? '') !== '' ? (int) $_POST['azienda_cliente_id'] : null;
        $offertaServizio = '';

        if ($id > 0) {
            $stmtServizio = $pdo->prepare('SELECT o.servizio FROM commesse c LEFT JOIN offerte o ON o.id = c.offerta_id WHERE c.id = :id');
            $stmtServizio->execute([':id' => $id]);
            $offertaServizio = (string) ($stmtServizio->fetchColumn() ?: '');
        }
        $isSistemiGestioneAziendale = $offertaServizio === 'SISTEMI DI GESTIONE AZIENDALE';

        if ($id <= 0) {
            $errors[] = 'ID commessa non valido.';
        }
        if ($dataRali !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRali)) {
            $errors[] = 'Data R.A.L.I non valida.';
        }
        if ($budgetRaw !== '' && (!is_numeric($budgetRaw) || (float) $budgetRaw < 0)) {
            $errors[] = 'Budget non valido.';
        }
        if ($isSistemiGestioneAziendale && $enteCertificazioneId === null) {
            $errors[] = 'Per commesse da offerta "SISTEMI DI GESTIONE AZIENDALE" è obbligatorio selezionare l\'Ente di Certificazione.';
        }
        if ($isSistemiGestioneAziendale && ($importoEnteRaw === '' || !is_numeric($importoEnteRaw) || (float) $importoEnteRaw < 0)) {
            $errors[] = 'Per commesse da offerta "SISTEMI DI GESTIONE AZIENDALE" l\'Importo dell\'Ente di Certificazione è obbligatorio e deve essere valido.';
        }
        if (!$isSistemiGestioneAziendale) {
            $enteCertificazioneId = null;
            $importoEnteRaw = '';
        }

        if (!$errors) {
            $stmtSave = $pdo->prepare(
                'UPDATE commesse
                 SET data_rali = :data_rali,
                     dtg_utente_id = :dtg_utente_id,
                     budget = :budget,
                     ente_certificazione_id = :ente_certificazione_id,
                     importo_ente_certificazione = :importo_ente_certificazione,
                     azienda_cliente_id = :azienda_cliente_id
                 WHERE id = :id'
            );
            $stmtSave->execute([
                ':data_rali' => $dataRali !== '' ? $dataRali : null,
                ':dtg_utente_id' => $dtgUtenteId,
                ':budget' => $budgetRaw !== '' ? number_format((float) $budgetRaw, 2, '.', '') : null,
                ':ente_certificazione_id' => $enteCertificazioneId,
                ':importo_ente_certificazione' => $importoEnteRaw !== '' ? number_format((float) $importoEnteRaw, 2, '.', '') : null,
                ':azienda_cliente_id' => $aziendaClienteId,
                ':id' => $id,
            ]);

            $success = 'Commessa aggiornata correttamente.';
            $editId = $id;
        }
    }

    if ($azione === 'upload_file') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Commessa non valida per upload file.';
        } elseif (!isset($_FILES['allegato']) || !is_array($_FILES['allegato'])) {
            $errors[] = 'Nessun file ricevuto.';
        } else {
            $file = $_FILES['allegato'];
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Errore durante il caricamento del file.';
            } else {
                $uploadBase = __DIR__ . '/uploads/commesse/' . $id;
                if (!is_dir($uploadBase) && !mkdir($uploadBase, 0775, true) && !is_dir($uploadBase)) {
                    $errors[] = 'Impossibile creare la cartella di upload.';
                } else {
                    $originalName = basename((string) $file['name']);
                    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                    $storedName = uniqid('file_', true) . ($ext ? '.' . $ext : '');
                    $target = $uploadBase . '/' . $storedName;

                    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
                        $errors[] = 'Impossibile salvare il file sul server.';
                    } else {
                        $mime = function_exists('mime_content_type') ? mime_content_type($target) : null;
                        $size = filesize($target);
                        $stmtIns = $pdo->prepare(
                            'INSERT INTO commesse_file (commessa_id, nome_originale, nome_salvato, mime_type, dimensione_bytes)
                             VALUES (:commessa_id, :nome_originale, :nome_salvato, :mime_type, :dimensione_bytes)'
                        );
                        $stmtIns->execute([
                            ':commessa_id' => $id,
                            ':nome_originale' => $originalName,
                            ':nome_salvato' => $storedName,
                            ':mime_type' => $mime,
                            ':dimensione_bytes' => (int) $size,
                        ]);
                        $success = 'File caricato correttamente.';
                        $editId = $id;
                    }
                }
            }
        }
    }

    if ($azione === 'complete_momento') {
        $id = (int) ($_POST['id'] ?? 0);
        $momentoId = (int) ($_POST['momento_id'] ?? 0);
        if ($id <= 0 || $momentoId <= 0) {
            $errors[] = 'Dati completamento momento non validi.';
        } else {
            $stmtMom = $pdo->prepare('SELECT * FROM commessa_momenti_lavorazione WHERE id = :id AND commessa_id = :commessa_id');
            $stmtMom->execute([':id' => $momentoId, ':commessa_id' => $id]);
            $momento = $stmtMom->fetch();
            if (!$momento) {
                $errors[] = 'Momento non trovato.';
            } else {
                $pdo->prepare('UPDATE commessa_momenti_lavorazione SET completato = 1, completato_il = NOW() WHERE id = :id')->execute([':id' => $momentoId]);
                $stmtExists = $pdo->prepare('SELECT id FROM fatture WHERE momento_id = :momento_id LIMIT 1');
                $stmtExists->execute([':momento_id' => $momentoId]);
                if (!$stmtExists->fetchColumn()) {
                    $valoreG = (float) $momento['valore_giornaliero_uomo'];
                    $oreM = (float) $momento['ore'];
                    $giorniM = (float) $momento['giorni'];
                    $giornateCalcolate = $giorniM > 0 ? $giorniM : ($oreM / 8);
                    $importoFattura = max(0, $valoreG * $giornateCalcolate);
                    $numeroFattura = $momentoId . '/' . date('Y');
                    $pdo->prepare('INSERT INTO fatture (commessa_id, momento_id, numero, anno_riferimento, importo) VALUES (:commessa_id, :momento_id, :numero, :anno, :importo)')
                        ->execute([
                            ':commessa_id' => $id,
                            ':momento_id' => $momentoId,
                            ':numero' => $numeroFattura,
                            ':anno' => (int) date('Y'),
                            ':importo' => number_format($importoFattura, 2, '.', ''),
                        ]);
                }
                $success = 'Momento completato e fattura generata.';
                $editId = $id;
            }
        }
    }
}

if ($editId > 0) {
    $stmtEdit = $pdo->prepare(
        'SELECT c.*, o.servizio AS offerta_servizio
         FROM commesse c
         LEFT JOIN offerte o ON o.id = c.offerta_id
         WHERE c.id = :id'
    );
    $stmtEdit->execute([':id' => $editId]);
    $commessaInModifica = $stmtEdit->fetch();

    if ($commessaInModifica) {
        $pdo->prepare(
            'INSERT INTO commessa_momenti_lavorazione (commessa_id, data_momento, tipologia, valore_giornaliero_uomo, ore, giorni, numero_incontri, ore_studio, data_prevista)
             SELECT :commessa_id, om.data_momento, om.tipologia, om.valore_giornaliero_uomo, om.ore, om.giorni, om.numero_incontri, om.ore_studio, om.data_prevista
             FROM offerta_momenti_lavorazione om
             WHERE om.offerta_id = :offerta_id
               AND NOT EXISTS (
                   SELECT 1 FROM commessa_momenti_lavorazione cm
                   WHERE cm.commessa_id = :commessa_id
                     AND cm.data_momento = om.data_momento
                     AND cm.tipologia = om.tipologia
                     AND cm.valore_giornaliero_uomo = om.valore_giornaliero_uomo
                     AND cm.ore = om.ore
                     AND cm.giorni = om.giorni
               )'
        )->execute([
            ':commessa_id' => $editId,
            ':offerta_id' => (int) ($commessaInModifica['offerta_id'] ?? 0),
        ]);

        $stmtFiles = $pdo->prepare('SELECT * FROM commesse_file WHERE commessa_id = :commessa_id ORDER BY caricato_il DESC');
        $stmtFiles->execute([':commessa_id' => $editId]);
        $filesCommessa = $stmtFiles->fetchAll();

        $stmtMomenti = $pdo->prepare('SELECT * FROM commessa_momenti_lavorazione WHERE commessa_id = :commessa_id ORDER BY data_momento DESC, id DESC');
        $stmtMomenti->execute([':commessa_id' => $editId]);
        $momentiCommessa = $stmtMomenti->fetchAll();

        foreach ($momentiCommessa as $momento) {
            $valoreG = (float) $momento['valore_giornaliero_uomo'];
            $oreM = (float) $momento['ore'];
            $giorniM = (float) $momento['giorni'];
            $giornateCalcolate = $giorniM > 0 ? $giorniM : ($oreM / 8);
            $totaleMomentiEuro += $valoreG * $giornateCalcolate;
        }

        $stmtFatture = $pdo->prepare('SELECT id, momento_id, numero FROM fatture WHERE commessa_id = :commessa_id');
        $stmtFatture->execute([':commessa_id' => $editId]);
        foreach ($stmtFatture->fetchAll() as $fattura) {
            $fatturePerMomento[(int) $fattura['momento_id']] = $fattura;
        }
    }
}

$filters = ['protocollo', 'anno_riferimento', 'consulente_nome', 'offerta_protocollo', 'offerta_servizio', 'offerta_stato'];
$where = [];
$params = [];
foreach ($filters as $field) {
    $key = 'f_' . $field;
    $value = trim((string) ($_GET[$key] ?? ''));
    if ($value === '') {
        continue;
    }

    if ($field === 'anno_riferimento') {
        $where[] = "c.anno_riferimento = :$key";
        $params[":$key"] = (int) $value;
    } elseif ($field === 'offerta_protocollo') {
        $where[] = "o.protocollo LIKE :$key";
        $params[":$key"] = '%' . $value . '%';
    } elseif ($field === 'offerta_servizio') {
        $where[] = "o.servizio LIKE :$key";
        $params[":$key"] = '%' . $value . '%';
    } elseif ($field === 'offerta_stato') {
        $where[] = "o.stato LIKE :$key";
        $params[":$key"] = '%' . $value . '%';
    } else {
        $where[] = "c.$field LIKE :$key";
        $params[":$key"] = '%' . $value . '%';
    }
}

$sql = 'SELECT c.*, o.protocollo AS offerta_protocollo, o.servizio AS offerta_servizio, o.stato AS offerta_stato,
               CONCAT(u.nome, " ", u.cognome) AS dtg_nome, a.ragione_sociale AS azienda_cliente_nome
        FROM commesse c
        LEFT JOIN offerte o ON o.id = c.offerta_id
        LEFT JOIN utenti u ON u.id = c.dtg_utente_id
        LEFT JOIN aziende a ON a.id = c.azienda_cliente_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY c.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commesse = $stmt->fetchAll();

$utenteLoggato = currentUser();
renderHeader('Simplex - Commesse');
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2 mb-3">
                <li class="nav-item"><a class="nav-link" href="bacheca.php">Bacheca</a></li>
                <li class="nav-item"><a class="nav-link" href="offerte.php">Offerte</a></li>
                <li class="nav-item"><a class="nav-link" href="commesse.php">Commesse</a></li>

                <li class="nav-item">
                    <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuAnagrafiche" role="button" aria-expanded="false" aria-controls="menuAnagrafiche">
                        <span>Anagrafiche</span><span>▾</span>
                    </a>
                    <ul class="nav flex-column ms-3 collapse" id="menuAnagrafiche">
                        <li class="nav-item"><a class="nav-link" href="utenti.php">Utenti</a></li>
                        <li class="nav-item"><a class="nav-link" href="aziende.php">Aziende</a></li>
                        <li class="nav-item"><a class="nav-link" href="enti_certificazione.php">Enti di Certificazione</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuAmministrazione" role="button" aria-expanded="false" aria-controls="menuAmministrazione">
                        <span>Amministrazione</span><span>▾</span>
                    </a>
                    <ul class="nav flex-column ms-3 collapse" id="menuAmministrazione">
                        <li class="nav-item"><a class="nav-link" href="amministrazione_produzione.php">Produzione</a></li>
                        <li class="nav-item"><a class="nav-link" href="fatture.php">Fatture</a></li>
                        <li class="nav-item"><a class="nav-link" href="pagamenti.php">Pagamenti</a></li>
                    </ul>
                </li>

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
                <h2 class="mb-0">Commesse</h2>
            </div>

            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

            <?php if ($commessaInModifica): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Modifica Commessa <?= htmlspecialchars($commessaInModifica['protocollo']) ?></span>
                        <span class="badge text-bg-secondary">Momenti importati da Offerta</span>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="azione" value="save">
                            <input type="hidden" name="id" value="<?= (int)$commessaInModifica['id'] ?>">

                            <div class="col-md-3"><label class="form-label">Data R.A.L.I</label><input type="date" class="form-control" name="data_rali" value="<?= htmlspecialchars($commessaInModifica['data_rali'] ?? '') ?>"></div>
                            <div class="col-md-3"><label class="form-label">DTG</label><select class="form-select" name="dtg_utente_id"><option value="">-- Seleziona --</option><?php foreach ($utenti as $utente): ?><option value="<?= (int)$utente['id'] ?>" <?= ((int)($commessaInModifica['dtg_utente_id'] ?? 0) === (int)$utente['id']) ? 'selected' : '' ?>><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-3"><label class="form-label">Budget (€)</label><input type="number" min="0" step="0.01" class="form-control" name="budget" value="<?= htmlspecialchars((string)($commessaInModifica['budget'] ?? '')) ?>"></div>
                            <div class="col-md-3"><label class="form-label">Ragione Sociale Cliente</label><select class="form-select" name="azienda_cliente_id"><option value="">-- Seleziona --</option><?php foreach ($aziende as $azienda): ?><option value="<?= (int)$azienda['id'] ?>" <?= ((int)($commessaInModifica['azienda_cliente_id'] ?? 0) === (int)$azienda['id']) ? 'selected' : '' ?>><?= htmlspecialchars($azienda['ragione_sociale']) ?></option><?php endforeach; ?></select></div>
                            <?php if (($commessaInModifica['offerta_servizio'] ?? '') === 'SISTEMI DI GESTIONE AZIENDALE'): ?>
                                <div class="col-md-6"><label class="form-label">Ente di Certificazione</label><select class="form-select" name="ente_certificazione_id"><option value="">-- Seleziona --</option><?php foreach ($entiCertificazione as $ente): ?><option value="<?= (int)$ente['id'] ?>" <?= ((int)($commessaInModifica['ente_certificazione_id'] ?? 0) === (int)$ente['id']) ? 'selected' : '' ?>><?= htmlspecialchars($ente['denominazione']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6"><label class="form-label">Importo dell'Ente di Certificazione (€)</label><input type="number" min="0" step="0.01" class="form-control" name="importo_ente_certificazione" value="<?= htmlspecialchars((string)($commessaInModifica['importo_ente_certificazione'] ?? '')) ?>"></div>
                            <?php endif; ?>

                            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Salva modifica</button><a class="btn btn-outline-secondary" href="commesse.php">Annulla</a></div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Momenti di Lavorazione</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Data</th><th>Tipologia</th><th>Valore Giornaliero Uomo (€)</th><th>Ore</th><th>Giorni</th><th># Incontri</th><th># ore di studio</th><th>Data Prevista</th><th>Completato</th><th>Fattura</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$momentiCommessa): ?>
                                    <tr><td colspan="10" class="text-center text-muted py-3">Nessun momento inserito.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($momentiCommessa as $momento): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($momento['data_momento']) ?></td>
                                        <td><?= htmlspecialchars($momento['tipologia']) ?></td>
                                        <td><?= htmlspecialchars((string)$momento['valore_giornaliero_uomo']) ?></td>
                                        <td><?= htmlspecialchars((string)$momento['ore']) ?></td>
                                        <td><?= htmlspecialchars((string)$momento['giorni']) ?></td>
                                        <td><?= htmlspecialchars((string)$momento['numero_incontri']) ?></td>
                                        <td><?= htmlspecialchars($momento['ore_studio'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($momento['data_prevista'] ?? '-') ?></td>
                                        <td>
                                            <?php if ((int)($momento['completato'] ?? 0) === 1): ?>
                                                <span class="badge text-bg-success">Sì</span>
                                            <?php else: ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="azione" value="complete_momento">
                                                    <input type="hidden" name="id" value="<?= (int)$commessaInModifica['id'] ?>">
                                                    <input type="hidden" name="momento_id" value="<?= (int)$momento['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">✔</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $fatturaMomento = $fatturePerMomento[(int) $momento['id']] ?? null; ?>
                                            <?php if ($fatturaMomento): ?>
                                                <a class="btn btn-sm btn-outline-success" title="Visualizza fattura" href="fatture.php?view=<?= (int) $fatturaMomento['id'] ?>">📄</a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <strong>Somma totale (€/uomo) su base ore/giorni (8h = 1 giorno):</strong>
                        € <?= number_format($totaleMomentiEuro, 2, ',', '.') ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">File della commessa</div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" class="row g-2 mb-3">
                            <input type="hidden" name="azione" value="upload_file"><input type="hidden" name="id" value="<?= (int)$commessaInModifica['id'] ?>">
                            <div class="col-md-9"><input type="file" class="form-control" name="allegato" required></div>
                            <div class="col-md-3 d-grid"><button class="btn btn-outline-primary" type="submit">Carica file</button></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead><tr><th>Nome file</th><th>Tipo</th><th>Dimensione</th><th>Caricato il</th><th>Azioni</th></tr></thead>
                                <tbody>
                                <?php if (!$filesCommessa): ?><tr><td colspan="5" class="text-center text-muted py-3">Nessun file caricato.</td></tr><?php endif; ?>
                                <?php foreach ($filesCommessa as $file): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($file['nome_originale']) ?></td><td><?= htmlspecialchars($file['mime_type'] ?? '-') ?></td><td><?= htmlspecialchars((string)$file['dimensione_bytes']) ?> bytes</td><td><?= htmlspecialchars($file['caricato_il']) ?></td>
                                        <td><a class="btn btn-sm btn-outline-secondary" href="download_commessa_file.php?id=<?= (int)$file['id'] ?>&mode=view" target="_blank">Visualizza</a> <a class="btn btn-sm btn-outline-primary" href="download_commessa_file.php?id=<?= (int)$file['id'] ?>&mode=download">Scarica</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">Filtro ricerca commesse</div>
                <div class="card-body">
                    <form method="get" class="row g-2">
                        <div class="col-md-2"><input class="form-control" name="f_protocollo" placeholder="Protocollo commessa" value="<?= htmlspecialchars($_GET['f_protocollo'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_anno_riferimento" placeholder="Anno" value="<?= htmlspecialchars($_GET['f_anno_riferimento'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_consulente_nome" placeholder="Consulente" value="<?= htmlspecialchars($_GET['f_consulente_nome'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_offerta_protocollo" placeholder="Prot. Offerta" value="<?= htmlspecialchars($_GET['f_offerta_protocollo'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_offerta_servizio" placeholder="Servizio offerta" value="<?= htmlspecialchars($_GET['f_offerta_servizio'] ?? '') ?>"></div>
                        <div class="col-md-1"><input class="form-control" name="f_offerta_stato" placeholder="Stato" value="<?= htmlspecialchars($_GET['f_offerta_stato'] ?? '') ?>"></div>
                        <div class="col-12 d-flex gap-2"><button class="btn btn-outline-primary" type="submit">Filtra</button><a class="btn btn-outline-secondary" href="commesse.php">Reset</a></div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco commesse generate</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Protocollo Commessa</th><th>Anno</th><th>Consulente</th><th>Protocollo Offerta</th><th>Data R.A.L.I</th><th>DTG</th><th>Budget (€)</th><th>Ragione Sociale Cliente</th><th>Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$commesse): ?><tr><td colspan="9" class="text-center text-muted py-4">Nessuna commessa trovata.</td></tr><?php endif; ?>
                        <?php foreach ($commesse as $commessa): ?>
                            <tr>
                                <td><?= htmlspecialchars($commessa['protocollo']) ?></td>
                                <td><?= htmlspecialchars((string)$commessa['anno_riferimento']) ?></td>
                                <td><?= htmlspecialchars($commessa['consulente_nome']) ?></td>
                                <td><?php if (!empty($commessa['offerta_id'])): ?><a href="offerte.php?view=<?= (int)$commessa['offerta_id'] ?>"><?= htmlspecialchars($commessa['offerta_protocollo'] ?? '-') ?></a><?php else: ?>-<?php endif; ?></td>
                                <td><?= htmlspecialchars($commessa['data_rali'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($commessa['dtg_nome'] ?? '-') ?></td>
                                <td><?= htmlspecialchars((string)($commessa['budget'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars($commessa['azienda_cliente_nome'] ?? '-') ?></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="commesse.php?edit=<?= (int)$commessa['id'] ?>">Modifica</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php renderFooter(); ?>
