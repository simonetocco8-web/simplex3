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
    'SICUREZZA' => [
        'DVR (D. Lgs. 81/2008)', 'AGGIORNAMENTO DVR', 'MISURAZIONI TECNICHE', 'VISITE MEDICHE',
    ],
    'FORMAZIONE' => [
        'FORMAZIONE D. Lgs. 81/2008', 'Formazione Profili ENEL', 'ALTRE ATTIVITA’ DI FORMAZIONE',
    ],
    'FINANZA AGEVOLATA' => ['REGIONE CALABRIA', 'INVITALIA', 'MINISTERO', 'ALTRO'],
    'CONSULENZA SOA' => ['NUOVA ATTESTAZIONE', 'VERIFICA TRIENNALE', 'VERIFICA QUINQUENNALE', 'VARIAZIONE', 'ALTRO'],
    'ALTRE CONSULENZE' => ['MARKETING', 'PIANI STRATEGICI', 'CONTROLLO DI GESTIONE', 'ALTRO'],
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
        descrizione TEXT NULL,
        creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_protocollo_anno (protocollo_numero, anno_riferimento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$action = $_GET['action'] ?? 'list';
$viewId = isset($_GET['view']) ? (int) $_GET['view'] : 0;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$offertaInModifica = null;
$offertaInVisualizzazione = null;

if ($viewId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM offerte WHERE id = :id');
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
        $descrizione = trim($_POST['descrizione'] ?? '');

        if (!in_array($servizio, $SERVIZI, true)) {
            $errors[] = 'Servizio non valido.';
        }

        $opzioniDettaglio = $DETTAGLI_SERVIZIO[$servizio] ?? [];
        if ($servizio === '') {
            $errors[] = 'Il campo Servizio è obbligatorio.';
        }

        if (!$opzioniDettaglio || !in_array($dettaglioServizio, $opzioniDettaglio, true)) {
            $errors[] = 'Campo dettaglio servizio non valido o non coerente con il servizio scelto.';
        }

        $tipoDettaglio = $servizio === 'SISTEMI DI GESTIONE AZIENDALE' ? 'SottoServizio' : 'Servizio Specifico';

        if (!$errors) {
            if ($id > 0) {
                $sql = 'UPDATE offerte
                        SET servizio = :servizio,
                            tipo_dettaglio = :tipo_dettaglio,
                            dettaglio_servizio = :dettaglio_servizio,
                            descrizione = :descrizione
                        WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':servizio' => $servizio,
                    ':tipo_dettaglio' => $tipoDettaglio,
                    ':dettaglio_servizio' => $dettaglioServizio,
                    ':descrizione' => $descrizione !== '' ? $descrizione : null,
                    ':id' => $id,
                ]);
                $success = 'Offerta aggiornata correttamente.';
                $editId = 0;
                $action = 'list';
                $offertaInModifica = null;
            } else {
                $anno = (int) date('Y');
                $stmt = $pdo->prepare('SELECT COALESCE(MAX(protocollo_numero), 0) + 1 FROM offerte WHERE anno_riferimento = :anno');
                $stmt->execute([':anno' => $anno]);
                $numeroProgressivo = (int) $stmt->fetchColumn();
                $protocollo = $numeroProgressivo . '/' . $anno;

                $sql = 'INSERT INTO offerte (protocollo_numero, anno_riferimento, protocollo, servizio, tipo_dettaglio, dettaglio_servizio, descrizione)
                        VALUES (:protocollo_numero, :anno_riferimento, :protocollo, :servizio, :tipo_dettaglio, :dettaglio_servizio, :descrizione)';
                $stmt = $pdo->prepare($sql);

                try {
                    $stmt->execute([
                        ':protocollo_numero' => $numeroProgressivo,
                        ':anno_riferimento' => $anno,
                        ':protocollo' => $protocollo,
                        ':servizio' => $servizio,
                        ':tipo_dettaglio' => $tipoDettaglio,
                        ':dettaglio_servizio' => $dettaglioServizio,
                        ':descrizione' => $descrizione !== '' ? $descrizione : null,
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

$filters = ['protocollo', 'servizio', 'tipo_dettaglio', 'dettaglio_servizio', 'descrizione', 'anno_riferimento'];
$where = [];
$params = [];
foreach ($filters as $field) {
    $key = 'f_' . $field;
    $value = trim((string) ($_GET[$key] ?? ''));
    if ($value === '') {
        continue;
    }

    if ($field === 'anno_riferimento') {
        $where[] = "$field = :$key";
        $params[":$key"] = (int) $value;
    } else {
        $where[] = "$field LIKE :$key";
        $params[":$key"] = '%' . $value . '%';
    }
}

$sqlList = 'SELECT * FROM offerte';
if ($where) {
    $sqlList .= ' WHERE ' . implode(' AND ', $where);
}
$sqlList .= ' ORDER BY id DESC';
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
                                <label class="form-label">Descrizione offerta</label>
                                <textarea class="form-control" name="descrizione" rows="3"><?= htmlspecialchars($formData['descrizione'] ?? '') ?></textarea>
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
                        <div class="col-md-3"><input class="form-control" name="f_protocollo" placeholder="Protocollo" value="<?= htmlspecialchars($_GET['f_protocollo'] ?? '') ?>"></div>
                        <div class="col-md-3">
                            <select class="form-select" name="f_servizio">
                                <option value="">Servizio</option>
                                <?php foreach ($SERVIZI as $servizio): ?>
                                    <option value="<?= htmlspecialchars($servizio) ?>" <?= (($_GET['f_servizio'] ?? '') === $servizio) ? 'selected' : '' ?>><?= htmlspecialchars($servizio) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><input class="form-control" name="f_tipo_dettaglio" placeholder="Tipo dettaglio" value="<?= htmlspecialchars($_GET['f_tipo_dettaglio'] ?? '') ?>"></div>
                        <div class="col-md-4"><input class="form-control" name="f_dettaglio_servizio" placeholder="Dettaglio servizio" value="<?= htmlspecialchars($_GET['f_dettaglio_servizio'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_anno_riferimento" placeholder="Anno" value="<?= htmlspecialchars($_GET['f_anno_riferimento'] ?? '') ?>"></div>
                        <div class="col-md-9"><input class="form-control" name="f_descrizione" placeholder="Descrizione" value="<?= htmlspecialchars($_GET['f_descrizione'] ?? '') ?>"></div>
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
                            <th>Tipo</th>
                            <th>Dettaglio</th>
                            <th>Descrizione</th>
                            <th>Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$offerte): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nessuna offerta trovata.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($offerte as $offerta): ?>
                            <tr>
                                <td><?= htmlspecialchars($offerta['protocollo']) ?></td>
                                <td><?= htmlspecialchars($offerta['servizio']) ?></td>
                                <td><?= htmlspecialchars($offerta['tipo_dettaglio']) ?></td>
                                <td><?= htmlspecialchars($offerta['dettaglio_servizio']) ?></td>
                                <td><?= htmlspecialchars($offerta['descrizione'] ?? '-') ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-secondary" href="offerte.php?view=<?= (int)$offerta['id'] ?>">Visualizza</a>
                                        <a class="btn btn-sm btn-outline-primary" href="offerte.php?edit=<?= (int)$offerta['id'] ?>">Modifica</a>
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
    const servizio = servizioSelect ? servizioSelect.value : '';
    const opzioni = dettagliPerServizio[servizio] || [];

    if (!dettaglioSelect || !labelDettaglio) return;

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
        if (dettaglioPreselezionato === val) {
            opt.selected = true;
        }
        dettaglioSelect.appendChild(opt);
    });
}

if (servizioSelect && dettaglioSelect) {
    servizioSelect.addEventListener('change', () => {
        while (dettaglioSelect.firstChild) dettaglioSelect.removeChild(dettaglioSelect.firstChild);
        renderDettaglioOptions();
    });
    renderDettaglioOptions();
}
</script>
<?php renderFooter(); ?>
