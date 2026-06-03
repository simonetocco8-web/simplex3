<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

$TIPOLOGIE_AZIENDA = ['Promotore', 'Fornitore', 'Partner', 'Cliente'];
$TIPI_SEDE = ['Legale', 'Amministrativa', 'Operativa'];
$ORGANICO_OPTIONS = ['0-10', '10-50', '50-250', '250+'];
$FATTURATO_OPTIONS = ['< 2 Mln', '< 10 Mln', '< 50 Mln', '> 50 Mln'];
$REGIONI_ITALIA = ['Abruzzo', 'Basilicata', 'Calabria', 'Campania', 'Emilia-Romagna', 'Friuli Venezia Giulia', 'Lazio', 'Liguria', 'Lombardia', 'Marche', 'Molise', 'Piemonte', 'Puglia', 'Sardegna', 'Sicilia', 'Toscana', 'Trentino-Alto Adige/Südtirol', 'Umbria', "Valle d'Aosta/Vallée d'Aoste", 'Veneto'];
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
        fatturato VARCHAR(20) NOT NULL,
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

$columnsAziende = $pdo->query('SHOW COLUMNS FROM aziende')->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array('regione', $columnsAziende, true)) {
    $pdo->exec("ALTER TABLE aziende ADD COLUMN regione VARCHAR(60) NULL AFTER cap");
}
if (!in_array('comune', $columnsAziende, true)) {
    $pdo->exec("ALTER TABLE aziende ADD COLUMN comune VARCHAR(120) NULL AFTER provincia");
}
$pdo->exec("ALTER TABLE aziende MODIFY COLUMN fatturato VARCHAR(20) NOT NULL");

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS aziende_sedi (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        azienda_id INT UNSIGNED NOT NULL,
        tipo_sede VARCHAR(30) NOT NULL,
        via VARCHAR(120) NOT NULL,
        numero_civico VARCHAR(10) NOT NULL,
        cap VARCHAR(10) NOT NULL,
        regione VARCHAR(60) NOT NULL,
        provincia VARCHAR(2) NOT NULL,
        comune VARCHAR(120) NOT NULL,
        creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_aziende_sedi_azienda FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS aziende_file (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        azienda_id INT UNSIGNED NOT NULL,
        nome_originale VARCHAR(255) NOT NULL,
        nome_salvato VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NULL,
        dimensione_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
        caricato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_aziende_file_azienda FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$utenti = $pdo->query('SELECT id, nome, cognome FROM utenti ORDER BY nome, cognome')->fetchAll();
$aziendePromotori = $pdo->query("SELECT id, ragione_sociale FROM aziende WHERE FIND_IN_SET('Promotore', tipologia_azienda) > 0 ORDER BY ragione_sociale")->fetchAll();

$action = $_GET['action'] ?? 'list';
$viewId = isset($_GET['view']) ? (int) $_GET['view'] : 0;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$aziendaInModifica = null;
$aziendaInVisualizzazione = null;
$filesAzienda = [];
$sediAzienda = [];
$sediForm = [];

if ($viewId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM aziende WHERE id = :id');
    $stmt->execute([':id' => $viewId]);
    $aziendaInVisualizzazione = $stmt->fetch();
    if ($aziendaInVisualizzazione) {
        $stmtFiles = $pdo->prepare('SELECT * FROM aziende_file WHERE azienda_id = :azienda_id ORDER BY caricato_il DESC');
        $stmtFiles->execute([':azienda_id' => $viewId]);
        $filesAzienda = $stmtFiles->fetchAll();
        $stmtSedi = $pdo->prepare('SELECT * FROM aziende_sedi WHERE azienda_id = :azienda_id ORDER BY FIELD(tipo_sede, "Legale", "Amministrativa", "Operativa"), id');
        $stmtSedi->execute([':azienda_id' => $viewId]);
        $sediAzienda = $stmtSedi->fetchAll();
    }
}

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM aziende WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $aziendaInModifica = $stmt->fetch();
    if (!$aziendaInModifica) {
        $errors[] = 'Azienda da modificare non trovata.';
    } else {
        $stmtSedi = $pdo->prepare('SELECT * FROM aziende_sedi WHERE azienda_id = :azienda_id ORDER BY FIELD(tipo_sede, "Legale", "Amministrativa", "Operativa"), id');
        $stmtSedi->execute([':azienda_id' => $editId]);
        $sediForm = $stmtSedi->fetchAll();
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

    if ($azione === 'upload_file') {
        $idAzienda = (int) ($_POST['id'] ?? 0);
        if ($idAzienda <= 0) {
            $errors[] = 'Azienda non valida per upload file.';
        } elseif (!isset($_FILES['allegato']) || !is_array($_FILES['allegato'])) {
            $errors[] = 'Nessun file ricevuto.';
        } else {
            $file = $_FILES['allegato'];
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Errore durante il caricamento del file.';
            } else {
                $uploadBase = __DIR__ . '/uploads/aziende/' . $idAzienda;
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
                            'INSERT INTO aziende_file (azienda_id, nome_originale, nome_salvato, mime_type, dimensione_bytes)
                             VALUES (:azienda_id, :nome_originale, :nome_salvato, :mime_type, :dimensione_bytes)'
                        );
                        $stmtIns->execute([
                            ':azienda_id' => $idAzienda,
                            ':nome_originale' => $originalName,
                            ':nome_salvato' => $storedName,
                            ':mime_type' => $mime,
                            ':dimensione_bytes' => (int) $size,
                        ]);
                        $success = 'File caricato correttamente.';
                        $viewId = $idAzienda;
                        $stmt = $pdo->prepare('SELECT * FROM aziende WHERE id = :id');
                        $stmt->execute([':id' => $viewId]);
                        $aziendaInVisualizzazione = $stmt->fetch();
                        $stmtFiles = $pdo->prepare('SELECT * FROM aziende_file WHERE azienda_id = :azienda_id ORDER BY caricato_il DESC');
                        $stmtFiles->execute([':azienda_id' => $viewId]);
                        $filesAzienda = $stmtFiles->fetchAll();
                        $stmtSedi = $pdo->prepare('SELECT * FROM aziende_sedi WHERE azienda_id = :azienda_id ORDER BY FIELD(tipo_sede, "Legale", "Amministrativa", "Operativa"), id');
                        $stmtSedi->execute([':azienda_id' => $viewId]);
                        $sediAzienda = $stmtSedi->fetchAll();
                    }
                }
            }
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
        $sediInput = [];
        $sedeTipi = $_POST['sede_tipo'] ?? [];
        $sedeVie = $_POST['sede_via'] ?? [];
        $sedeNumeriCivici = $_POST['sede_numero_civico'] ?? [];
        $sedeCap = $_POST['sede_cap'] ?? [];
        $sedeRegioni = $_POST['sede_regione'] ?? [];
        $sedeProvince = $_POST['sede_provincia'] ?? [];
        $sedeComuni = $_POST['sede_comune'] ?? [];
        $maxSedi = max(
            is_array($sedeTipi) ? count($sedeTipi) : 0,
            is_array($sedeVie) ? count($sedeVie) : 0,
            is_array($sedeNumeriCivici) ? count($sedeNumeriCivici) : 0,
            is_array($sedeCap) ? count($sedeCap) : 0,
            is_array($sedeRegioni) ? count($sedeRegioni) : 0,
            is_array($sedeProvince) ? count($sedeProvince) : 0,
            is_array($sedeComuni) ? count($sedeComuni) : 0
        );
        for ($i = 0; $i < $maxSedi; $i++) {
            $sede = [
                'tipo_sede' => trim((string)($sedeTipi[$i] ?? '')),
                'via' => trim((string)($sedeVie[$i] ?? '')),
                'numero_civico' => trim((string)($sedeNumeriCivici[$i] ?? '')),
                'cap' => trim((string)($sedeCap[$i] ?? '')),
                'regione' => trim((string)($sedeRegioni[$i] ?? '')),
                'provincia' => strtoupper(trim((string)($sedeProvince[$i] ?? ''))),
                'comune' => trim((string)($sedeComuni[$i] ?? '')),
            ];
            if (implode('', $sede) === '') {
                continue;
            }
            $sediInput[] = $sede;
        }
        $sediForm = $sediInput;
        $rcoUtenteId = ($_POST['rco_utente_id'] ?? '') !== '' ? (int) $_POST['rco_utente_id'] : null;
        $segnalataDaUtenteId = ($_POST['segnalata_da_utente_id'] ?? '') !== '' ? (int) $_POST['segnalata_da_utente_id'] : null;
        $categoria = trim($_POST['categoria'] ?? '');
        $ea = trim($_POST['ea'] ?? '');
        $organicoMedio = trim($_POST['organico_medio'] ?? '');
        $fatturato = trim($_POST['fatturato'] ?? '');
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

        if (!$sediInput) {
            $errors[] = 'Inserisci almeno una sede aziendale.';
        }
        foreach ($sediInput as $index => $sede) {
            $numeroSede = $index + 1;
            if (!in_array($sede['tipo_sede'], $TIPI_SEDE, true)) {
                $errors[] = "Tipo sede non valido per la sede {$numeroSede}.";
            }
            if ($sede['via'] === '' || $sede['numero_civico'] === '' || $sede['cap'] === '' || $sede['regione'] === '' || $sede['provincia'] === '' || $sede['comune'] === '') {
                $errors[] = "Completa tutti i campi della sede {$numeroSede}.";
            }
            if ($sede['cap'] !== '' && !preg_match('/^\d{5}$/', $sede['cap'])) {
                $errors[] = "CAP non valido per la sede {$numeroSede}: inserire 5 numeri.";
            }
            if ($sede['provincia'] !== '' && !preg_match('/^[A-Z]{2}$/', $sede['provincia'])) {
                $errors[] = "Provincia non valida per la sede {$numeroSede}: inserire la sigla di 2 lettere.";
            }
        }

        if (!in_array($organicoMedio, $ORGANICO_OPTIONS, true)) {
            $errors[] = 'Organico Medio non valido.';
        }

        if (!in_array($fatturato, $FATTURATO_OPTIONS, true)) {
            $errors[] = 'Fatturato non valido.';
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
                ':rco_utente_id' => $rcoUtenteId,
                ':segnalata_da_utente_id' => $segnalataDaUtenteId,
                ':categoria' => $categoria !== '' ? $categoria : null,
                ':ea' => $ea !== '' ? $ea : null,
                ':organico_medio' => $organicoMedio,
                ':fatturato' => $fatturato,
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
                            telefono, pec, email, url_sito,
                            rco_utente_id, segnalata_da_utente_id, categoria, ea, organico_medio, fatturato,
                            conosciuto_come, principali_prodotti, note, promotore_azienda_id
                        ) VALUES (
                            :partita_iva, :codice_fiscale, :ragione_sociale, :iban, :tipologia_azienda, :codice_fatturazione,
                            :telefono, :pec, :email, :url_sito,
                            :rco_utente_id, :segnalata_da_utente_id, :categoria, :ea, :organico_medio, :fatturato,
                            :conosciuto_come, :principali_prodotti, :note, :promotore_azienda_id
                        )';
            }

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $aziendaId = $id > 0 ? $id : (int) $pdo->lastInsertId();

                $stmtDeleteSedi = $pdo->prepare('DELETE FROM aziende_sedi WHERE azienda_id = :azienda_id');
                $stmtDeleteSedi->execute([':azienda_id' => $aziendaId]);
                $stmtInsertSede = $pdo->prepare(
                    'INSERT INTO aziende_sedi (azienda_id, tipo_sede, via, numero_civico, cap, regione, provincia, comune)
                     VALUES (:azienda_id, :tipo_sede, :via, :numero_civico, :cap, :regione, :provincia, :comune)'
                );
                foreach ($sediInput as $sede) {
                    $stmtInsertSede->execute([
                        ':azienda_id' => $aziendaId,
                        ':tipo_sede' => $sede['tipo_sede'],
                        ':via' => $sede['via'],
                        ':numero_civico' => $sede['numero_civico'],
                        ':cap' => $sede['cap'],
                        ':regione' => $sede['regione'],
                        ':provincia' => $sede['provincia'],
                        ':comune' => $sede['comune'],
                    ]);
                }
                $pdo->commit();

                $success = $id > 0 ? 'Azienda aggiornata correttamente.' : 'Azienda creata correttamente.';
                $action = 'list';
                $editId = 0;
                $aziendaInModifica = null;
                $sediForm = [];
                $aziendePromotori = $pdo->query("SELECT id, ragione_sociale FROM aziende WHERE FIND_IN_SET('Promotore', tipologia_azienda) > 0 ORDER BY ragione_sociale")->fetchAll();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
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
    'pec', 'email', 'url_sito', 'via', 'numero_civico', 'cap', 'localita', 'regione', 'provincia', 'comune', 'categoria', 'ea',
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'save' && $errors) {
    $formData = $_POST;
}
$formTipologie = isset($formData['tipologia_azienda'])
    ? (is_array($formData['tipologia_azienda']) ? $formData['tipologia_azienda'] : explode(',', (string) $formData['tipologia_azienda']))
    : [];
$sediFormJson = json_encode($sediForm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
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

                            <div class="col-12">
                                <div class="card border-secondary-subtle">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span>Sedi aziendali *</span>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalSede">Aggiungi Sede</button>
                                    </div>
                                    <div class="card-body">
                                        <div id="sedi-hidden-fields"></div>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Tipo sede</th>
                                                        <th>Indirizzo</th>
                                                        <th>CAP</th>
                                                        <th>Comune</th>
                                                        <th>Provincia</th>
                                                        <th>Regione</th>
                                                        <th>Azioni</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="sedi-table-body">
                                                    <tr id="sedi-empty-row"><td colspan="7" class="text-center text-muted py-3">Nessuna sede inserita.</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

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
                            <div class="col-md-3">
                                <label class="form-label">Organico Medio *</label>
                                <select class="form-select" name="organico_medio" required>
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($ORGANICO_OPTIONS as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($formData['organico_medio'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fatturato *</label>
                                <select class="form-select" name="fatturato" required>
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($FATTURATO_OPTIONS as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= (($formData['fatturato'] ?? '') === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

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

                <div class="modal fade" id="modalSede" tabindex="-1" aria-labelledby="modalSedeLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalSedeLabel">Aggiungi Sede</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3" id="sede-modal-form">
                                    <div class="col-md-4">
                                        <label class="form-label">Tipo sede *</label>
                                        <select class="form-select" id="sede_tipo">
                                            <option value="">-- Seleziona --</option>
                                            <?php foreach ($TIPI_SEDE as $tipoSede): ?>
                                                <option value="<?= htmlspecialchars($tipoSede) ?>"><?= htmlspecialchars($tipoSede) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">Via *</label><input class="form-control" id="sede_via"></div>
                                    <div class="col-md-2"><label class="form-label">Numero civico *</label><input class="form-control" id="sede_numero_civico"></div>
                                    <div class="col-md-3"><label class="form-label">CAP *</label><input class="form-control" id="sede_cap" maxlength="5"></div>
                                    <div class="col-md-3">
                                        <label class="form-label">Regione *</label>
                                        <select class="form-select js-regione" id="sede_regione" data-selected="">
                                            <option value="">-- Seleziona --</option>
                                            <?php foreach ($REGIONI_ITALIA as $regione): ?>
                                                <option value="<?= htmlspecialchars($regione) ?>"><?= htmlspecialchars($regione) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Provincia *</label>
                                        <select class="form-select js-provincia" id="sede_provincia" data-selected="">
                                            <option value="">-- Seleziona prima la regione --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Comune *</label>
                                        <select class="form-select js-comune" id="sede_comune" data-selected="">
                                            <option value="">-- Seleziona prima la provincia --</option>
                                        </select>
                                    </div>
                                    <div class="col-12"><div class="alert alert-danger d-none mb-0" id="sede-modal-error"></div></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                                <button type="button" class="btn btn-primary" id="salva-sede">Salva Sede</button>
                            </div>
                        </div>
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

                <div class="card mb-4">
                    <div class="card-header">Sedi Azienda</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead><tr><th>Tipo sede</th><th>Via</th><th>Numero civico</th><th>CAP</th><th>Regione</th><th>Provincia</th><th>Comune</th></tr></thead>
                            <tbody>
                            <?php if (!$sediAzienda): ?><tr><td colspan="7" class="text-center text-muted py-3">Nessuna sede inserita.</td></tr><?php endif; ?>
                            <?php foreach ($sediAzienda as $sede): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sede['tipo_sede']) ?></td>
                                    <td><?= htmlspecialchars($sede['via']) ?></td>
                                    <td><?= htmlspecialchars($sede['numero_civico']) ?></td>
                                    <td><?= htmlspecialchars($sede['cap']) ?></td>
                                    <td><?= htmlspecialchars($sede['regione']) ?></td>
                                    <td><?= htmlspecialchars($sede['provincia']) ?></td>
                                    <td><?= htmlspecialchars($sede['comune']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Documenti Azienda</div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" class="row g-2 mb-3">
                            <input type="hidden" name="azione" value="upload_file">
                            <input type="hidden" name="id" value="<?= (int)$aziendaInVisualizzazione['id'] ?>">
                            <div class="col-md-9"><input type="file" class="form-control" name="allegato" required></div>
                            <div class="col-md-3 d-grid"><button class="btn btn-outline-primary" type="submit">Carica file</button></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead><tr><th>Nome file</th><th>Tipo</th><th>Dimensione</th><th>Caricato il</th><th>Azioni</th></tr></thead>
                                <tbody>
                                <?php if (!$filesAzienda): ?><tr><td colspan="5" class="text-center text-muted py-3">Nessun file caricato.</td></tr><?php endif; ?>
                                <?php foreach ($filesAzienda as $file): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($file['nome_originale']) ?></td>
                                        <td><?= htmlspecialchars($file['mime_type'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars((string)$file['dimensione_bytes']) ?> bytes</td>
                                        <td><?= htmlspecialchars($file['caricato_il']) ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-secondary" href="download_azienda_file.php?id=<?= (int)$file['id'] ?>&mode=view" target="_blank">Visualizza</a>
                                            <a class="btn btn-sm btn-outline-primary" href="download_azienda_file.php?id=<?= (int)$file['id'] ?>&mode=download">Scarica</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
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
<script>
const comuniDatasetUrl = 'https://raw.githubusercontent.com/matteocontrini/comuni-json/master/comuni.json';
let comuniItaliaCache = null;

function normalizeProvincia(item) {
    return (item?.provincia?.sigla || item?.sigla || '').toString().trim().toUpperCase();
}

function normalizeRegione(item) {
    return (item?.regione?.nome || item?.regione || '').toString().trim();
}

function normalizeComune(item) {
    return (item?.nome || item?.comune || '').toString().trim();
}

async function loadComuniDataset() {
    if (comuniItaliaCache) return comuniItaliaCache;
    const response = await fetch(comuniDatasetUrl);
    if (!response.ok) throw new Error('Impossibile caricare dataset comuni/province.');
    comuniItaliaCache = await response.json();
    return comuniItaliaCache;
}

function fillSelectOptions(select, values, placeholder, selectedValue = '') {
    if (!select) return;
    const normalizedSelected = (selectedValue || '').toString();
    select.innerHTML = `<option value="">${placeholder}</option>`;
    values.forEach((value) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        if (value === normalizedSelected) option.selected = true;
        select.appendChild(option);
    });
}

async function setupGeoSelectors(scope) {
    const regioneSelect = scope.querySelector('.js-regione');
    const provinciaSelect = scope.querySelector('.js-provincia');
    const comuneSelect = scope.querySelector('.js-comune');
    if (!regioneSelect || !provinciaSelect || !comuneSelect) return;

    try {
        const dataset = await loadComuniDataset();
        const selectedProvincia = provinciaSelect.dataset.selected || '';
        const selectedComune = comuneSelect.dataset.selected || '';

        const refreshProvince = () => {
            const regione = regioneSelect.value;
            const province = [...new Set(dataset.filter((item) => normalizeRegione(item) === regione).map(normalizeProvincia).filter(Boolean))].sort();
            fillSelectOptions(provinciaSelect, province, regione ? '-- Seleziona --' : '-- Seleziona prima la regione --', provinciaSelect.dataset.selected || '');
            provinciaSelect.dataset.selected = '';
            refreshComuni();
        };

        const refreshComuni = () => {
            const regione = regioneSelect.value;
            const provincia = provinciaSelect.value;
            const comuni = [...new Set(dataset
                .filter((item) => normalizeRegione(item) === regione && normalizeProvincia(item) === provincia)
                .map(normalizeComune)
                .filter(Boolean))]
                .sort((a, b) => a.localeCompare(b, 'it'));
            fillSelectOptions(comuneSelect, comuni, provincia ? '-- Seleziona --' : '-- Seleziona prima la provincia --', comuneSelect.dataset.selected || '');
            comuneSelect.dataset.selected = '';
        };

        regioneSelect.addEventListener('change', refreshProvince);
        provinciaSelect.addEventListener('change', refreshComuni);

        if (selectedProvincia) provinciaSelect.dataset.selected = selectedProvincia;
        if (selectedComune) comuneSelect.dataset.selected = selectedComune;
        refreshProvince();
    } catch (error) {
        fillSelectOptions(provinciaSelect, [], 'Errore caricamento province');
        fillSelectOptions(comuneSelect, [], 'Errore caricamento comuni');
    }
}


const sediAziendali = <?= $sediFormJson ?: '[]' ?>;
const sediHiddenFields = document.getElementById('sedi-hidden-fields');
const sediTableBody = document.getElementById('sedi-table-body');
const sedeModalForm = document.getElementById('sede-modal-form');
const sedeModalElement = document.getElementById('modalSede');
const sedeModalError = document.getElementById('sede-modal-error');

function addHiddenInput(container, name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value || '';
    container.appendChild(input);
}

function resetSedeModal() {
    if (!sedeModalForm) return;
    ['sede_tipo', 'sede_via', 'sede_numero_civico', 'sede_cap', 'sede_regione'].forEach((id) => {
        const field = document.getElementById(id);
        if (field) field.value = '';
    });
    const provincia = document.getElementById('sede_provincia');
    const comune = document.getElementById('sede_comune');
    if (provincia) fillSelectOptions(provincia, [], '-- Seleziona prima la regione --');
    if (comune) fillSelectOptions(comune, [], '-- Seleziona prima la provincia --');
    if (sedeModalError) {
        sedeModalError.classList.add('d-none');
        sedeModalError.textContent = '';
    }
}

function renderSedi() {
    if (!sediHiddenFields || !sediTableBody) return;
    sediHiddenFields.innerHTML = '';
    sediTableBody.innerHTML = '';

    if (sediAziendali.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 7;
        cell.className = 'text-center text-muted py-3';
        cell.textContent = 'Nessuna sede inserita.';
        row.appendChild(cell);
        sediTableBody.appendChild(row);
        return;
    }

    sediAziendali.forEach((sede, index) => {
        addHiddenInput(sediHiddenFields, 'sede_tipo[]', sede.tipo_sede);
        addHiddenInput(sediHiddenFields, 'sede_via[]', sede.via);
        addHiddenInput(sediHiddenFields, 'sede_numero_civico[]', sede.numero_civico);
        addHiddenInput(sediHiddenFields, 'sede_cap[]', sede.cap);
        addHiddenInput(sediHiddenFields, 'sede_regione[]', sede.regione);
        addHiddenInput(sediHiddenFields, 'sede_provincia[]', sede.provincia);
        addHiddenInput(sediHiddenFields, 'sede_comune[]', sede.comune);

        const row = document.createElement('tr');
        [
            sede.tipo_sede,
            `${sede.via || ''} ${sede.numero_civico || ''}`.trim(),
            sede.cap,
            sede.comune,
            sede.provincia,
            sede.regione,
        ].forEach((value) => {
            const cell = document.createElement('td');
            cell.textContent = value || '-';
            row.appendChild(cell);
        });

        const actionsCell = document.createElement('td');
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-sm btn-outline-danger';
        removeButton.textContent = 'Rimuovi';
        removeButton.addEventListener('click', () => {
            sediAziendali.splice(index, 1);
            renderSedi();
        });
        actionsCell.appendChild(removeButton);
        row.appendChild(actionsCell);
        sediTableBody.appendChild(row);
    });
}

function readSedeModal() {
    return {
        tipo_sede: document.getElementById('sede_tipo')?.value.trim() || '',
        via: document.getElementById('sede_via')?.value.trim() || '',
        numero_civico: document.getElementById('sede_numero_civico')?.value.trim() || '',
        cap: document.getElementById('sede_cap')?.value.trim() || '',
        regione: document.getElementById('sede_regione')?.value.trim() || '',
        provincia: document.getElementById('sede_provincia')?.value.trim().toUpperCase() || '',
        comune: document.getElementById('sede_comune')?.value.trim() || '',
    };
}

function validateSede(sede) {
    if (!sede.tipo_sede || !sede.via || !sede.numero_civico || !sede.cap || !sede.regione || !sede.provincia || !sede.comune) {
        return 'Compila tutti i campi della sede.';
    }
    if (!/^\d{5}$/.test(sede.cap)) {
        return 'Il CAP deve contenere 5 numeri.';
    }
    if (!/^[A-Z]{2}$/.test(sede.provincia)) {
        return 'La provincia deve essere una sigla di 2 lettere.';
    }
    return '';
}

if (sedeModalElement) {
    sedeModalElement.addEventListener('hidden.bs.modal', resetSedeModal);
}

const salvaSedeButton = document.getElementById('salva-sede');
if (salvaSedeButton) {
    salvaSedeButton.addEventListener('click', () => {
        const sede = readSedeModal();
        const error = validateSede(sede);
        if (error) {
            if (sedeModalError) {
                sedeModalError.textContent = error;
                sedeModalError.classList.remove('d-none');
            }
            return;
        }
        sediAziendali.push(sede);
        renderSedi();
        const modal = bootstrap.Modal.getInstance(sedeModalElement) || new bootstrap.Modal(sedeModalElement);
        modal.hide();
    });
}

if (sedeModalForm) setupGeoSelectors(sedeModalForm);
document.querySelectorAll('form').forEach((form) => { setupGeoSelectors(form); });
renderSedi();
</script>
<?php renderFooter(); ?>
