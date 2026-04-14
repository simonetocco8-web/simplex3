<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

$TIPOLOGIE_AZIENDA = ['Promotore', 'Fornitore', 'Partner'];
$CATEGORIE_MERCEOLOGICHE = [
    'Agroalimentare', 'Commerciale', 'Commercio', 'Edile', 'Ente', 'Formazione', 'Fornitore', 'Impiantistica',
    'Manifatturiere', 'Officine', 'Privato', 'Sanità', 'Servizi', 'Strategico', 'Trasporti', 'Turismo',
];
$EA_OPTIONS = [
    'Agricoltura e pesca',
    'Estrazione minerali (cave, miniere e giacimenti petroliferi)',
    'Prodotti farmaceutici',
    'Calce, gesso, calcestruzzo, cemento e relativi prodotti',
    'Metalli e loro leghe, fabbricazione dei prodotti in metallo',
    'Macchine apparecchi e impianti meccanici',
    'Macchine elettriche e apparecchiature elettriche e ottiche',
    'Produzione non altrimenti classificata',
    'Produzione e distribuzione di energia elettrica',
    'Imprese di costruzione',
    'Industrie alimentari, delle bevande e del tabacco',
    'Alberghi, ristoranti e bar',
    'Trasporti, magazzinaggi e comunicazioni',
    'Intermediazione finanziaria, attività immobiliari, noleggio, software (ICT)',
    'Servizi diversi',
    'Pubblica amministrazione',
    'Istruzione',
    'Sanità e altri servizi sociali',
    'Servizi pubblici diversi',
    'Prodotti tessili (semilavorati, prodotti finiti e abbigliamento)',
    'Tipografia e attività connesse alla stampa',
];
$CONOSCIUTO_COME_OPTIONS = ['Fiera', 'Newsletter', 'Pagine Gialle', 'Promotore', 'Pubblicità', 'Segnalazione', 'Sito', 'Telemarketing'];

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
    "CREATE TABLE IF NOT EXISTS aziende (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        partita_iva VARCHAR(11) NOT NULL UNIQUE,
        codice_fiscale VARCHAR(16) NULL,
        ragione_sociale VARCHAR(30) NOT NULL,
        iban VARCHAR(34) NULL,
        tipologia_azienda VARCHAR(255) NOT NULL,
        codice_fatturazione VARCHAR(50) NULL,
        telefono VARCHAR(30) NULL,
        pec VARCHAR(100) NULL,
        email VARCHAR(100) NULL,
        url_sito VARCHAR(255) NULL,
        via VARCHAR(120) NULL,
        numero_civico VARCHAR(10) NULL,
        cap VARCHAR(10) NULL,
        localita VARCHAR(80) NULL,
        provincia VARCHAR(2) NULL,
        rco_utente_id INT UNSIGNED NULL,
        segnalata_da_utente_id INT UNSIGNED NULL,
        categoria VARCHAR(50) NULL,
        ea VARCHAR(255) NULL,
        organico_medio VARCHAR(30) NOT NULL,
        fatturato DECIMAL(15,2) NOT NULL,
        conosciuto_come VARCHAR(50) NULL,
        principali_prodotti TEXT NULL,
        note TEXT NULL,
        promotore_azienda_id INT UNSIGNED NULL,
        creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_azienda_rco FOREIGN KEY (rco_utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
        CONSTRAINT fk_azienda_segnalata FOREIGN KEY (segnalata_da_utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
        CONSTRAINT fk_azienda_promotore FOREIGN KEY (promotore_azienda_id) REFERENCES aziende(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$utenti = $pdo->query('SELECT id, nome, cognome FROM utenti ORDER BY nome, cognome')->fetchAll();
$aziendePromotori = $pdo->query("SELECT id, ragione_sociale FROM aziende WHERE FIND_IN_SET('Promotore', tipologia_azienda) > 0 ORDER BY ragione_sociale")->fetchAll();

$action = $_GET['action'] ?? 'list';
$viewId = isset($_GET['view']) ? (int) $_GET['view'] : 0;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$aziendaInModifica = null;
$aziendaInVisualizzazione = null;

if ($viewId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM aziende WHERE id = :id');
    $stmt->execute([':id' => $viewId]);
    $aziendaInVisualizzazione = $stmt->fetch();
}

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM aziende WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $aziendaInModifica = $stmt->fetch();
    if (!$aziendaInModifica) {
        $errors[] = 'Azienda da modificare non trovata.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'delete') {
        $idDelete = (int) ($_POST['id'] ?? 0);
        if ($idDelete > 0) {
            $stmt = $pdo->prepare('DELETE FROM aziende WHERE id = :id');
            $stmt->execute([':id' => $idDelete]);
            $success = 'Azienda eliminata correttamente.';
        }
    }

    if ($azione === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $partitaIva = trim($_POST['partita_iva'] ?? '');
        $codiceFiscale = strtoupper(trim($_POST['codice_fiscale'] ?? ''));
        $ragioneSociale = trim($_POST['ragione_sociale'] ?? '');
        $iban = strtoupper(str_replace(' ', '', trim($_POST['iban'] ?? '')));
        $tipologiaAzienda = $_POST['tipologia_azienda'] ?? [];
        $codiceFatturazione = trim($_POST['codice_fatturazione'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $pec = trim($_POST['pec'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $urlSito = trim($_POST['url_sito'] ?? '');
        $via = trim($_POST['via'] ?? '');
        $numeroCivico = trim($_POST['numero_civico'] ?? '');
        $cap = trim($_POST['cap'] ?? '');
        $localita = trim($_POST['localita'] ?? '');
        $provincia = strtoupper(trim($_POST['provincia'] ?? ''));
        $rcoUtenteId = ($_POST['rco_utente_id'] ?? '') !== '' ? (int) $_POST['rco_utente_id'] : null;
        $segnalataDaUtenteId = ($_POST['segnalata_da_utente_id'] ?? '') !== '' ? (int) $_POST['segnalata_da_utente_id'] : null;
        $categoria = trim($_POST['categoria'] ?? '');
        $ea = trim($_POST['ea'] ?? '');
        $organicoMedio = trim($_POST['organico_medio'] ?? '');
        $fatturato = str_replace(',', '.', trim($_POST['fatturato'] ?? ''));
        $conosciutoCome = trim($_POST['conosciuto_come'] ?? '');
        $principaliProdotti = trim($_POST['principali_prodotti'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $promotoreAziendaId = ($_POST['promotore_azienda_id'] ?? '') !== '' ? (int) $_POST['promotore_azienda_id'] : null;

        if (!preg_match('/^\d{11}$/', $partitaIva)) {
            $errors[] = 'Partita IVA obbligatoria: inserire esattamente 11 numeri.';
        }

        if ($codiceFiscale !== '' && !preg_match('/^(\d{11}|[A-Z0-9]{16})$/', $codiceFiscale)) {
            $errors[] = 'Codice fiscale non valido: usa 11 numeri o 16 caratteri alfanumerici.';
        }

        if ($ragioneSociale === '' || mb_strlen($ragioneSociale) > 30) {
            $errors[] = 'Ragione Sociale obbligatoria e massimo 30 caratteri.';
        }

        if ($iban !== '' && !preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
            $errors[] = 'IBAN non valido.';
        }

        if (!is_array($tipologiaAzienda) || count($tipologiaAzienda) === 0) {
            $errors[] = 'Seleziona almeno una Tipologia di Azienda.';
        }

        if (!preg_match('/^\d+\s*-\s*\d+$/', $organicoMedio)) {
            $errors[] = 'Organico Medio obbligatorio: usa formato range (es. 10-50).';
        }

        if (!is_numeric($fatturato) || (float) $fatturato < 0) {
            $errors[] = 'Fatturato obbligatorio: inserisci un importo valido in euro.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida.';
        }

        if ($pec !== '' && !filter_var($pec, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'PEC non valida.';
        }

        if ($urlSito !== '' && !filter_var($urlSito, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL sito non valido.';
        }

        $tipologiaAzienda = array_values(array_intersect($TIPOLOGIE_AZIENDA, $tipologiaAzienda));
        if (count($tipologiaAzienda) === 0) {
            $errors[] = 'Le tipologie selezionate non sono valide.';
        }

        if (!$errors) {
            $params = [
                ':partita_iva' => $partitaIva,
                ':codice_fiscale' => $codiceFiscale !== '' ? $codiceFiscale : null,
                ':ragione_sociale' => $ragioneSociale,
                ':iban' => $iban !== '' ? $iban : null,
                ':tipologia_azienda' => implode(',', $tipologiaAzienda),
                ':codice_fatturazione' => $codiceFatturazione !== '' ? $codiceFatturazione : null,
                ':telefono' => $telefono !== '' ? $telefono : null,
                ':pec' => $pec !== '' ? $pec : null,
                ':email' => $email !== '' ? $email : null,
                ':url_sito' => $urlSito !== '' ? $urlSito : null,
                ':via' => $via !== '' ? $via : null,
                ':numero_civico' => $numeroCivico !== '' ? $numeroCivico : null,
                ':cap' => $cap !== '' ? $cap : null,
                ':localita' => $localita !== '' ? $localita : null,
                ':provincia' => $provincia !== '' ? $provincia : null,
                ':rco_utente_id' => $rcoUtenteId,
                ':segnalata_da_utente_id' => $segnalataDaUtenteId,
                ':categoria' => $categoria !== '' ? $categoria : null,
                ':ea' => $ea !== '' ? $ea : null,
                ':organico_medio' => $organicoMedio,
                ':fatturato' => number_format((float) $fatturato, 2, '.', ''),
                ':conosciuto_come' => $conosciutoCome !== '' ? $conosciutoCome : null,
                ':principali_prodotti' => $principaliProdotti !== '' ? $principaliProdotti : null,
                ':note' => $note !== '' ? $note : null,
                ':promotore_azienda_id' => $promotoreAziendaId,
            ];

            if ($id > 0) {
                $sql = 'UPDATE aziende SET
                            partita_iva = :partita_iva,
                            codice_fiscale = :codice_fiscale,
                            ragione_sociale = :ragione_sociale,
                            iban = :iban,
                            tipologia_azienda = :tipologia_azienda,
                            codice_fatturazione = :codice_fatturazione,
                            telefono = :telefono,
                            pec = :pec,
                            email = :email,
                            url_sito = :url_sito,
                            via = :via,
                            numero_civico = :numero_civico,
                            cap = :cap,
                            localita = :localita,
                            provincia = :provincia,
                            rco_utente_id = :rco_utente_id,
                            segnalata_da_utente_id = :segnalata_da_utente_id,
                            categoria = :categoria,
                            ea = :ea,
                            organico_medio = :organico_medio,
                            fatturato = :fatturato,
                            conosciuto_come = :conosciuto_come,
                            principali_prodotti = :principali_prodotti,
                            note = :note,
                            promotore_azienda_id = :promotore_azienda_id
                        WHERE id = :id';
                $params[':id'] = $id;
            } else {
                $sql = 'INSERT INTO aziende (
                            partita_iva, codice_fiscale, ragione_sociale, iban, tipologia_azienda, codice_fatturazione,
                            telefono, pec, email, url_sito, via, numero_civico, cap, localita, provincia,
                            rco_utente_id, segnalata_da_utente_id, categoria, ea, organico_medio, fatturato,
                            conosciuto_come, principali_prodotti, note, promotore_azienda_id
                        ) VALUES (
                            :partita_iva, :codice_fiscale, :ragione_sociale, :iban, :tipologia_azienda, :codice_fatturazione,
                            :telefono, :pec, :email, :url_sito, :via, :numero_civico, :cap, :localita, :provincia,
                            :rco_utente_id, :segnalata_da_utente_id, :categoria, :ea, :organico_medio, :fatturato,
                            :conosciuto_come, :principali_prodotti, :note, :promotore_azienda_id
                        )';
            }

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = $id > 0 ? 'Azienda aggiornata correttamente.' : 'Azienda creata correttamente.';
                $action = 'list';
                $editId = 0;
                $aziendaInModifica = null;
                $aziendePromotori = $pdo->query("SELECT id, ragione_sociale FROM aziende WHERE FIND_IN_SET('Promotore', tipologia_azienda) > 0 ORDER BY ragione_sociale")->fetchAll();
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'Partita IVA già presente nel sistema.';
                } else {
                    $errors[] = 'Errore durante il salvataggio dell\'azienda.';
                }
            }
        }
    }
}

$filters = [
    'partita_iva', 'codice_fiscale', 'ragione_sociale', 'iban', 'tipologia_azienda', 'codice_fatturazione', 'telefono',
    'pec', 'email', 'url_sito', 'via', 'numero_civico', 'cap', 'localita', 'provincia', 'categoria', 'ea',
    'organico_medio', 'fatturato', 'conosciuto_come', 'principali_prodotti', 'note', 'rco_utente_id',
    'segnalata_da_utente_id', 'promotore_azienda_id',
];

$where = [];
$params = [];
foreach ($filters as $field) {
    $key = 'f_' . $field;
    $value = trim((string) ($_GET[$key] ?? ''));
    if ($value === '') {
        continue;
    }

    if ($field === 'tipologia_azienda') {
        $where[] = "FIND_IN_SET(:$key, a.tipologia_azienda) > 0";
        $params[":$key"] = $value;
    } elseif (in_array($field, ['rco_utente_id', 'segnalata_da_utente_id', 'promotore_azienda_id'], true)) {
        $where[] = "a.$field = :$key";
        $params[":$key"] = (int) $value;
    } elseif ($field === 'fatturato') {
        $where[] = "a.$field = :$key";
        $params[":$key"] = str_replace(',', '.', $value);
    } else {
        $where[] = "a.$field LIKE :$key";
        $params[":$key"] = '%' . $value . '%';
    }
}

$sqlList = 'SELECT a.*, '
    . 'CONCAT(r.nome, " ", r.cognome) AS rco_nome, '
    . 'CONCAT(s.nome, " ", s.cognome) AS segnalata_nome, '
    . 'p.ragione_sociale AS promotore_nome '
    . 'FROM aziende a '
    . 'LEFT JOIN utenti r ON r.id = a.rco_utente_id '
    . 'LEFT JOIN utenti s ON s.id = a.segnalata_da_utente_id '
    . 'LEFT JOIN aziende p ON p.id = a.promotore_azienda_id ';

if ($where) {
    $sqlList .= 'WHERE ' . implode(' AND ', $where);
}

$sqlList .= ' ORDER BY a.id DESC';
$stmtList = $pdo->prepare($sqlList);
$stmtList->execute($params);
$aziende = $stmtList->fetchAll();

$utenteLoggato = currentUser();
renderHeader('Simplex - Aziende');

$formData = $aziendaInModifica ?: [];
$formTipologie = isset($formData['tipologia_azienda']) ? explode(',', (string) $formData['tipologia_azienda']) : [];
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
                    <div class="mt-2">
                        <a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Gestione Aziende</h2>
                <a class="btn btn-primary" href="aziende.php?action=new">Nuova Azienda</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <?php if ($action === 'new' || $editId > 0): ?>
                <div class="card mb-4">
                    <div class="card-header"><?= $editId > 0 ? 'Modifica Azienda' : 'Nuova Azienda' ?></div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="azione" value="save">
                            <input type="hidden" name="id" value="<?= (int)($formData['id'] ?? 0) ?>">

                            <div class="col-md-4">
                                <label class="form-label">Partita IVA *</label>
                                <input class="form-control" name="partita_iva" maxlength="11" required value="<?= htmlspecialchars($formData['partita_iva'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Codice Fiscale</label>
                                <input class="form-control" name="codice_fiscale" maxlength="16" value="<?= htmlspecialchars($formData['codice_fiscale'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ragione Sociale *</label>
                                <input class="form-control" name="ragione_sociale" maxlength="30" required value="<?= htmlspecialchars($formData['ragione_sociale'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">IBAN</label>
                                <input class="form-control" name="iban" value="<?= htmlspecialchars($formData['iban'] ?? '') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label d-block">Tipologia Azienda *</label>
                                <?php foreach ($TIPOLOGIE_AZIENDA as $tip): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="tipologia_azienda[]" id="tip_<?= md5($tip) ?>" value="<?= htmlspecialchars($tip) ?>" <?= in_array($tip, $formTipologie, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="tip_<?= md5($tip) ?>"><?= htmlspecialchars($tip) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="col-md-4"><label class="form-label">Codice fatturazione</label><input class="form-control" name="codice_fatturazione" value="<?= htmlspecialchars($formData['codice_fatturazione'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Recapiti Telefonici</label><input class="form-control" name="telefono" value="<?= htmlspecialchars($formData['telefono'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">PEC</label><input type="email" class="form-control" name="pec" value="<?= htmlspecialchars($formData['pec'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($formData['email'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Url sito</label><input class="form-control" name="url_sito" value="<?= htmlspecialchars($formData['url_sito'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Via</label><input class="form-control" name="via" value="<?= htmlspecialchars($formData['via'] ?? '') ?>"></div>
                            <div class="col-md-2"><label class="form-label">Numero civico</label><input class="form-control" name="numero_civico" value="<?= htmlspecialchars($formData['numero_civico'] ?? '') ?>"></div>
                            <div class="col-md-2"><label class="form-label">CAP</label><input class="form-control" name="cap" value="<?= htmlspecialchars($formData['cap'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label">Località</label><input class="form-control" name="localita" value="<?= htmlspecialchars($formData['localita'] ?? '') ?>"></div>
                            <div class="col-md-2"><label class="form-label">Provincia</label><input class="form-control" name="provincia" maxlength="2" value="<?= htmlspecialchars($formData['provincia'] ?? '') ?>"></div>

                            <div class="col-md-4">
                                <label class="form-label">RCO</label>
                                <select class="form-select" name="rco_utente_id">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($utenti as $utente): ?>
                                        <option value="<?= (int)$utente['id'] ?>" <?= ((int)($formData['rco_utente_id'] ?? 0) === (int)$utente['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Segnalata da</label>
                                <select class="form-select" name="segnalata_da_utente_id">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($utenti as $utente): ?>
                                        <option value="<?= (int)$utente['id'] ?>" <?= ((int)($formData['segnalata_da_utente_id'] ?? 0) === (int)$utente['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Categoria</label>
                                <select class="form-select" name="categoria">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($CATEGORIE_MERCEOLOGICHE as $categoria): ?>
                                        <option value="<?= htmlspecialchars($categoria) ?>" <?= (($formData['categoria'] ?? '') === $categoria) ? 'selected' : '' ?>><?= htmlspecialchars($categoria) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">EA</label>
                                <select class="form-select" name="ea">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($EA_OPTIONS as $ea): ?>
                                        <option value="<?= htmlspecialchars($ea) ?>" <?= (($formData['ea'] ?? '') === $ea) ? 'selected' : '' ?>><?= htmlspecialchars($ea) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Organico Medio *</label><input class="form-control" name="organico_medio" placeholder="es. 10-50" required value="<?= htmlspecialchars($formData['organico_medio'] ?? '') ?>"></div>
                            <div class="col-md-3"><label class="form-label">Fatturato (€) *</label><input class="form-control" name="fatturato" required value="<?= htmlspecialchars($formData['fatturato'] ?? '') ?>"></div>

                            <div class="col-md-4">
                                <label class="form-label">Conosciuto Come</label>
                                <select class="form-select" name="conosciuto_come">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($CONOSCIUTO_COME_OPTIONS as $opzione): ?>
                                        <option value="<?= htmlspecialchars($opzione) ?>" <?= (($formData['conosciuto_come'] ?? '') === $opzione) ? 'selected' : '' ?>><?= htmlspecialchars($opzione) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Promotore (azienda)</label>
                                <select class="form-select" name="promotore_azienda_id">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($aziendePromotori as $aziendaPromotore): ?>
                                        <option value="<?= (int)$aziendaPromotore['id'] ?>" <?= ((int)($formData['promotore_azienda_id'] ?? 0) === (int)$aziendaPromotore['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($aziendaPromotore['ragione_sociale']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12"><label class="form-label">Principali Prodotti</label><textarea class="form-control" name="principali_prodotti" rows="2"><?= htmlspecialchars($formData['principali_prodotti'] ?? '') ?></textarea></div>
                            <div class="col-12"><label class="form-label">Note</label><textarea class="form-control" name="note" rows="3"><?= htmlspecialchars($formData['note'] ?? '') ?></textarea></div>

                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary" type="submit">Salva Azienda</button>
                                <a class="btn btn-outline-secondary" href="aziende.php">Annulla</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($aziendaInVisualizzazione): ?>
                <div class="card mb-4">
                    <div class="card-header">Dettaglio Azienda</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($aziendaInVisualizzazione as $campo => $valore): ?>
                                <div class="col-md-4"><strong><?= htmlspecialchars((string)$campo) ?>:</strong> <?= htmlspecialchars((string)($valore ?? '-')) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">Filtri Aziende</div>
                <div class="card-body">
                    <form method="get" class="row g-2">
                        <div class="col-md-2"><input class="form-control" name="f_partita_iva" placeholder="P.IVA" value="<?= htmlspecialchars($_GET['f_partita_iva'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_codice_fiscale" placeholder="Cod. Fiscale" value="<?= htmlspecialchars($_GET['f_codice_fiscale'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_ragione_sociale" placeholder="Ragione Sociale" value="<?= htmlspecialchars($_GET['f_ragione_sociale'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_telefono" placeholder="Telefono" value="<?= htmlspecialchars($_GET['f_telefono'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_email" placeholder="Email" value="<?= htmlspecialchars($_GET['f_email'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_pec" placeholder="PEC" value="<?= htmlspecialchars($_GET['f_pec'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_url_sito" placeholder="URL" value="<?= htmlspecialchars($_GET['f_url_sito'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_via" placeholder="Via" value="<?= htmlspecialchars($_GET['f_via'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_numero_civico" placeholder="Civico" value="<?= htmlspecialchars($_GET['f_numero_civico'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_cap" placeholder="CAP" value="<?= htmlspecialchars($_GET['f_cap'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_localita" placeholder="Località" value="<?= htmlspecialchars($_GET['f_localita'] ?? '') ?>"></div>
                        <div class="col-md-1"><input class="form-control" name="f_provincia" placeholder="PR" value="<?= htmlspecialchars($_GET['f_provincia'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_organico_medio" placeholder="Organico" value="<?= htmlspecialchars($_GET['f_organico_medio'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_fatturato" placeholder="Fatturato" value="<?= htmlspecialchars($_GET['f_fatturato'] ?? '') ?>"></div>

                        <div class="col-md-3">
                            <select class="form-select" name="f_tipologia_azienda">
                                <option value="">Tipologia</option>
                                <?php foreach ($TIPOLOGIE_AZIENDA as $tip): ?>
                                    <option value="<?= htmlspecialchars($tip) ?>" <?= (($_GET['f_tipologia_azienda'] ?? '') === $tip) ? 'selected' : '' ?>><?= htmlspecialchars($tip) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="f_categoria">
                                <option value="">Categoria</option>
                                <?php foreach ($CATEGORIE_MERCEOLOGICHE as $categoria): ?>
                                    <option value="<?= htmlspecialchars($categoria) ?>" <?= (($_GET['f_categoria'] ?? '') === $categoria) ? 'selected' : '' ?>><?= htmlspecialchars($categoria) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="f_ea">
                                <option value="">EA</option>
                                <?php foreach ($EA_OPTIONS as $ea): ?>
                                    <option value="<?= htmlspecialchars($ea) ?>" <?= (($_GET['f_ea'] ?? '') === $ea) ? 'selected' : '' ?>><?= htmlspecialchars($ea) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="f_conosciuto_come">
                                <option value="">Conosciuto Come</option>
                                <?php foreach ($CONOSCIUTO_COME_OPTIONS as $opzione): ?>
                                    <option value="<?= htmlspecialchars($opzione) ?>" <?= (($_GET['f_conosciuto_come'] ?? '') === $opzione) ? 'selected' : '' ?>><?= htmlspecialchars($opzione) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

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
                            <select class="form-select" name="f_segnalata_da_utente_id">
                                <option value="">Segnalata da</option>
                                <?php foreach ($utenti as $utente): ?>
                                    <option value="<?= (int)$utente['id'] ?>" <?= ((string)($_GET['f_segnalata_da_utente_id'] ?? '') === (string)$utente['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="f_promotore_azienda_id">
                                <option value="">Promotore (azienda)</option>
                                <?php foreach ($aziendePromotori as $aziendaPromotore): ?>
                                    <option value="<?= (int)$aziendaPromotore['id'] ?>" <?= ((string)($_GET['f_promotore_azienda_id'] ?? '') === (string)$aziendaPromotore['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($aziendaPromotore['ragione_sociale']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6"><input class="form-control" name="f_principali_prodotti" placeholder="Principali prodotti" value="<?= htmlspecialchars($_GET['f_principali_prodotti'] ?? '') ?>"></div>
                        <div class="col-md-6"><input class="form-control" name="f_note" placeholder="Note" value="<?= htmlspecialchars($_GET['f_note'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_iban" placeholder="IBAN" value="<?= htmlspecialchars($_GET['f_iban'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_codice_fatturazione" placeholder="Cod. fatturazione" value="<?= htmlspecialchars($_GET['f_codice_fatturazione'] ?? '') ?>"></div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-outline-primary" type="submit">Filtra</button>
                            <a class="btn btn-outline-secondary" href="aziende.php">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco Aziende</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>P.IVA</th>
                            <th>Ragione Sociale</th>
                            <th>Tipologia</th>
                            <th>Email</th>
                            <th>Telefono</th>
                            <th>RCO</th>
                            <th>Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$aziende): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nessuna azienda trovata.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($aziende as $azienda): ?>
                            <tr>
                                <td><?= htmlspecialchars($azienda['partita_iva']) ?></td>
                                <td><?= htmlspecialchars($azienda['ragione_sociale']) ?></td>
                                <td><?= htmlspecialchars($azienda['tipologia_azienda']) ?></td>
                                <td><?= htmlspecialchars($azienda['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($azienda['telefono'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(trim((string)($azienda['rco_nome'] ?? '')) ?: '-') ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-secondary" href="aziende.php?view=<?= (int)$azienda['id'] ?>">Visualizza</a>
                                        <a class="btn btn-sm btn-outline-primary" href="aziende.php?edit=<?= (int)$azienda['id'] ?>">Modifica</a>
                                        <form method="post" onsubmit="return confirm('Confermi eliminazione azienda?');">
                                            <input type="hidden" name="azione" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$azienda['id'] ?>">
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
<?php renderFooter(); ?>
