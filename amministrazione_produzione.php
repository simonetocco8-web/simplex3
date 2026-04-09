<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$filters = [
    'protocollo', 'anno_riferimento', 'consulente_nome', 'offerta_protocollo', 'offerta_stato',
    'dtg_utente_id', 'azienda_cliente_id', 'data_rali', 'budget_min', 'budget_max',
];

$where = [];
$params = [];

foreach ($filters as $field) {
    $key = 'f_' . $field;
    $value = trim((string) ($_GET[$key] ?? ''));
    if ($value === '') {
        continue;
    }

    switch ($field) {
        case 'anno_riferimento':
            $where[] = 'c.anno_riferimento = :f_anno_riferimento';
            $params[':f_anno_riferimento'] = (int) $value;
            break;
        case 'offerta_protocollo':
            $where[] = 'o.protocollo LIKE :f_offerta_protocollo';
            $params[':f_offerta_protocollo'] = '%' . $value . '%';
            break;
        case 'offerta_stato':
            $where[] = 'o.stato LIKE :f_offerta_stato';
            $params[':f_offerta_stato'] = '%' . $value . '%';
            break;
        case 'dtg_utente_id':
            $where[] = 'c.dtg_utente_id = :f_dtg_utente_id';
            $params[':f_dtg_utente_id'] = (int) $value;
            break;
        case 'azienda_cliente_id':
            $where[] = 'c.azienda_cliente_id = :f_azienda_cliente_id';
            $params[':f_azienda_cliente_id'] = (int) $value;
            break;
        case 'data_rali':
            $where[] = 'c.data_rali = :f_data_rali';
            $params[':f_data_rali'] = $value;
            break;
        case 'budget_min':
            if (is_numeric(str_replace(',', '.', $value))) {
                $where[] = 'c.budget >= :f_budget_min';
                $params[':f_budget_min'] = (float) str_replace(',', '.', $value);
            }
            break;
        case 'budget_max':
            if (is_numeric(str_replace(',', '.', $value))) {
                $where[] = 'c.budget <= :f_budget_max';
                $params[':f_budget_max'] = (float) str_replace(',', '.', $value);
            }
            break;
        default:
            $where[] = "c.$field LIKE :$key";
            $params[":$key"] = '%' . $value . '%';
    }
}

$utenti = $pdo->query('SELECT id, nome, cognome FROM utenti ORDER BY nome, cognome')->fetchAll();
$aziende = [];
if ((bool) $pdo->query("SHOW TABLES LIKE 'aziende'")->fetchColumn()) {
    $aziende = $pdo->query('SELECT id, ragione_sociale FROM aziende ORDER BY ragione_sociale')->fetchAll();
}

$sql = 'SELECT c.*, o.protocollo AS offerta_protocollo, o.stato AS offerta_stato,
               CONCAT(u.nome, " ", u.cognome) AS dtg_nome,
               a.ragione_sociale AS azienda_cliente_nome
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

$totaleBudget = 0.0;
foreach ($commesse as $c) {
    $totaleBudget += (float) ($c['budget'] ?? 0);
}

$utenteLoggato = currentUser();
renderHeader('Simplex - Amministrazione / Produzione');
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
                <li class="nav-item"><a class="nav-link" href="commesse.php">Commesse</a></li>
                <li class="nav-item mt-2 text-uppercase small text-light-emphasis px-2">Amministrazione</li>
                <li class="nav-item"><a class="nav-link active" href="amministrazione_produzione.php">Produzione</a></li>
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
            <h2 class="mb-4">Amministrazione / Produzione</h2>

            <div class="card mb-4">
                <div class="card-header">Filtra commesse</div>
                <div class="card-body">
                    <form method="get" class="row g-2">
                        <div class="col-md-2"><input class="form-control" name="f_protocollo" placeholder="Protocollo" value="<?= htmlspecialchars($_GET['f_protocollo'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_anno_riferimento" placeholder="Anno" value="<?= htmlspecialchars($_GET['f_anno_riferimento'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_consulente_nome" placeholder="Consulente" value="<?= htmlspecialchars($_GET['f_consulente_nome'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_offerta_protocollo" placeholder="Prot. Offerta" value="<?= htmlspecialchars($_GET['f_offerta_protocollo'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_offerta_stato" placeholder="Stato Offerta" value="<?= htmlspecialchars($_GET['f_offerta_stato'] ?? '') ?>"></div>
                        <div class="col-md-2"><input type="date" class="form-control" name="f_data_rali" value="<?= htmlspecialchars($_GET['f_data_rali'] ?? '') ?>"></div>

                        <div class="col-md-3">
                            <select class="form-select" name="f_dtg_utente_id">
                                <option value="">DTG</option>
                                <?php foreach ($utenti as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>" <?= ((string)($_GET['f_dtg_utente_id'] ?? '') === (string)$u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nome'] . ' ' . $u['cognome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="f_azienda_cliente_id">
                                <option value="">Ragione Sociale Cliente</option>
                                <?php foreach ($aziende as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>" <?= ((string)($_GET['f_azienda_cliente_id'] ?? '') === (string)$a['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a['ragione_sociale']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><input type="number" step="0.01" min="0" class="form-control" name="f_budget_min" placeholder="Budget min" value="<?= htmlspecialchars($_GET['f_budget_min'] ?? '') ?>"></div>
                        <div class="col-md-3"><input type="number" step="0.01" min="0" class="form-control" name="f_budget_max" placeholder="Budget max" value="<?= htmlspecialchars($_GET['f_budget_max'] ?? '') ?>"></div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-outline-primary" type="submit">Filtra</button>
                            <a class="btn btn-outline-secondary" href="amministrazione_produzione.php">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco commesse (Produzione)</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Protocollo Commessa</th>
                            <th>Anno</th>
                            <th>Consulente</th>
                            <th>Protocollo Offerta</th>
                            <th>Stato Offerta</th>
                            <th>Data R.A.L.I</th>
                            <th>DTG</th>
                            <th>Budget (€)</th>
                            <th>Cliente</th>
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
                                <td><?= htmlspecialchars($commessa['offerta_protocollo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($commessa['offerta_stato'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($commessa['data_rali'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($commessa['dtg_nome'] ?? '-') ?></td>
                                <td>
                                    <?php if (isset($commessa['budget']) && $commessa['budget'] !== null && $commessa['budget'] !== ''): ?>
                                        € <?= number_format((float)$commessa['budget'], 2, ',', '.') ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($commessa['azienda_cliente_nome'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <strong>Totale Budget commesse in tabella:</strong>
                    € <?= number_format($totaleBudget, 2, ',', '.') ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php renderFooter(); ?>
