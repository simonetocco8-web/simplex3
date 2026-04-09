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
    azienda_cliente_id INT UNSIGNED NULL,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_commesse_num_anno (protocollo_numero, anno_riferimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$migrations = [
    'data_rali' => 'ALTER TABLE commesse ADD COLUMN data_rali DATE NULL AFTER consulente_nome',
    'dtg_utente_id' => 'ALTER TABLE commesse ADD COLUMN dtg_utente_id INT UNSIGNED NULL AFTER data_rali',
    'budget' => 'ALTER TABLE commesse ADD COLUMN budget DECIMAL(12,2) NULL AFTER dtg_utente_id',
    'azienda_cliente_id' => 'ALTER TABLE commesse ADD COLUMN azienda_cliente_id INT UNSIGNED NULL AFTER budget',
];
foreach ($migrations as $column => $sqlAlter) {
    $exists = (bool) $pdo->query("SHOW COLUMNS FROM commesse LIKE '{$column}'")->fetch();
    if (!$exists) {
        $pdo->exec($sqlAlter);
    }
}

$utenti = $pdo->query('SELECT id, nome, cognome FROM utenti ORDER BY nome, cognome')->fetchAll();
$aziende = [];
if ((bool) $pdo->query("SHOW TABLES LIKE 'aziende'")->fetchColumn()) {
    $aziende = $pdo->query('SELECT id, ragione_sociale FROM aziende ORDER BY ragione_sociale')->fetchAll();
}

$errors = [];
$success = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$commessaInModifica = null;

if ($editId > 0) {
    $stmtEdit = $pdo->prepare('SELECT * FROM commesse WHERE id = :id');
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
        $aziendaClienteId = ($_POST['azienda_cliente_id'] ?? '') !== '' ? (int) $_POST['azienda_cliente_id'] : null;

        if ($id <= 0) {
            $errors[] = 'ID commessa non valido.';
        }

        if ($dataRali !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRali)) {
            $errors[] = 'Data R.A.L.I non valida.';
        }

        if ($budgetRaw !== '' && (!is_numeric($budgetRaw) || (float) $budgetRaw < 0)) {
            $errors[] = 'Budget non valido.';
        }

        if (!$errors) {
            $stmtSave = $pdo->prepare(
                'UPDATE commesse
                 SET data_rali = :data_rali,
                     dtg_utente_id = :dtg_utente_id,
                     budget = :budget,
                     azienda_cliente_id = :azienda_cliente_id
                 WHERE id = :id'
            );
            $stmtSave->execute([
                ':data_rali' => $dataRali !== '' ? $dataRali : null,
                ':dtg_utente_id' => $dtgUtenteId,
                ':budget' => $budgetRaw !== '' ? number_format((float) $budgetRaw, 2, '.', '') : null,
                ':azienda_cliente_id' => $aziendaClienteId,
                ':id' => $id,
            ]);

            $success = 'Commessa aggiornata correttamente.';
            $editId = 0;
            $commessaInModifica = null;
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
                <li class="nav-item"><a class="nav-link" href="utenti.php">Utenti</a></li>
                <li class="nav-item"><a class="nav-link" href="aziende.php">Aziende</a></li>
                <li class="nav-item"><a class="nav-link" href="offerte.php">Offerte</a></li>
                <li class="nav-item"><a class="nav-link active" href="commesse.php">Commesse</a></li>
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

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <?php if ($commessaInModifica): ?>
                <div class="card mb-4">
                    <div class="card-header">Modifica Commessa <?= htmlspecialchars($commessaInModifica['protocollo']) ?></div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="azione" value="save">
                            <input type="hidden" name="id" value="<?= (int)$commessaInModifica['id'] ?>">

                            <div class="col-md-3">
                                <label class="form-label">Data R.A.L.I</label>
                                <input type="date" class="form-control" name="data_rali" value="<?= htmlspecialchars($commessaInModifica['data_rali'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">DTG</label>
                                <select class="form-select" name="dtg_utente_id">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($utenti as $utente): ?>
                                        <option value="<?= (int)$utente['id'] ?>" <?= ((int)($commessaInModifica['dtg_utente_id'] ?? 0) === (int)$utente['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Budget (€)</label>
                                <input type="number" min="0" step="0.01" class="form-control" name="budget" value="<?= htmlspecialchars((string)($commessaInModifica['budget'] ?? '')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ragione Sociale Cliente</label>
                                <select class="form-select" name="azienda_cliente_id">
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach ($aziende as $azienda): ?>
                                        <option value="<?= (int)$azienda['id'] ?>" <?= ((int)($commessaInModifica['azienda_cliente_id'] ?? 0) === (int)$azienda['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($azienda['ragione_sociale']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary" type="submit">Salva modifica</button>
                                <a class="btn btn-outline-secondary" href="commesse.php">Annulla</a>
                            </div>
                        </form>
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
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-outline-primary" type="submit">Filtra</button>
                            <a class="btn btn-outline-secondary" href="commesse.php">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco commesse generate</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Protocollo Commessa</th>
                            <th>Anno</th>
                            <th>Consulente</th>
                            <th>Protocollo Offerta</th>
                            <th>Data R.A.L.I</th>
                            <th>DTG</th>
                            <th>Budget (€)</th>
                            <th>Ragione Sociale Cliente</th>
                            <th>Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$commesse): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Nessuna commessa trovata.</td></tr>
                        <?php endif; ?>
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
