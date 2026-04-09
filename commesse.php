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
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_commesse_num_anno (protocollo_numero, anno_riferimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

$sql = 'SELECT c.*, o.protocollo AS offerta_protocollo, o.servizio AS offerta_servizio, o.stato AS offerta_stato
        FROM commesse c
        LEFT JOIN offerte o ON o.id = c.offerta_id';
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
                            <th>Servizio Offerta</th>
                            <th>Stato Offerta</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$commesse): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nessuna commessa trovata.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($commesse as $commessa): ?>
                            <tr>
                                <td><?= htmlspecialchars($commessa['protocollo']) ?></td>
                                <td><?= htmlspecialchars((string)$commessa['anno_riferimento']) ?></td>
                                <td><?= htmlspecialchars($commessa['consulente_nome']) ?></td>
                                <td><?php if (!empty($commessa['offerta_id'])): ?><a href="offerte.php?view=<?= (int)$commessa['offerta_id'] ?>"><?= htmlspecialchars($commessa['offerta_protocollo'] ?? '-') ?></a><?php else: ?>-<?php endif; ?></td>
                                <td><?= htmlspecialchars($commessa['offerta_servizio'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($commessa['offerta_stato'] ?? '-') ?></td>
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
