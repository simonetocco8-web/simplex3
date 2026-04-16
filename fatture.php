<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS fatture (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commessa_id INT UNSIGNED NOT NULL,
    momento_id INT UNSIGNED NOT NULL UNIQUE,
    numero VARCHAR(30) NOT NULL UNIQUE,
    anno_riferimento YEAR NOT NULL,
    importo DECIMAL(12,2) NOT NULL,
    pagata TINYINT(1) NOT NULL DEFAULT 0,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pagamenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fattura_id INT UNSIGNED NOT NULL UNIQUE,
    data_pagamento DATE NOT NULL,
    modalita_pagamento ENUM('Contanti', 'Bonifico', 'Carta di Credito') NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'registra_pagamento') {
    $fatturaId = (int) ($_POST['fattura_id'] ?? 0);
    $dataPagamento = trim((string) ($_POST['data_pagamento'] ?? ''));
    $modalitaPagamento = trim((string) ($_POST['modalita_pagamento'] ?? ''));

    if ($fatturaId <= 0) {
        $errors[] = 'Fattura non valida.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPagamento)) {
        $errors[] = 'Data pagamento non valida.';
    }
    if (!in_array($modalitaPagamento, ['Contanti', 'Bonifico', 'Carta di Credito'], true)) {
        $errors[] = 'Modalità pagamento non valida.';
    }

    if (!$errors) {
        $stmtPagamento = $pdo->prepare('SELECT id FROM pagamenti WHERE fattura_id = :fattura_id');
        $stmtPagamento->execute([':fattura_id' => $fatturaId]);
        $pagamentoEsistente = $stmtPagamento->fetchColumn();

        if ($pagamentoEsistente) {
            $stmtUpd = $pdo->prepare('UPDATE pagamenti SET data_pagamento = :data_pagamento, modalita_pagamento = :modalita_pagamento WHERE fattura_id = :fattura_id');
            $stmtUpd->execute([
                ':data_pagamento' => $dataPagamento,
                ':modalita_pagamento' => $modalitaPagamento,
                ':fattura_id' => $fatturaId,
            ]);
        } else {
            $stmtIns = $pdo->prepare('INSERT INTO pagamenti (fattura_id, data_pagamento, modalita_pagamento) VALUES (:fattura_id, :data_pagamento, :modalita_pagamento)');
            $stmtIns->execute([
                ':fattura_id' => $fatturaId,
                ':data_pagamento' => $dataPagamento,
                ':modalita_pagamento' => $modalitaPagamento,
            ]);
        }

        $pdo->prepare('UPDATE fatture SET pagata = 1 WHERE id = :id')->execute([':id' => $fatturaId]);
        $success = 'Pagamento registrato correttamente.';
    }
}

$filters = ['numero', 'anno_riferimento', 'commessa_protocollo', 'pagata'];
$where = [];
$params = [];
foreach ($filters as $field) {
    $key = 'f_' . $field;
    $value = trim((string) ($_GET[$key] ?? ''));
    if ($value === '') {
        continue;
    }

    if ($field === 'anno_riferimento') {
        $where[] = 'f.anno_riferimento = :f_anno_riferimento';
        $params[':f_anno_riferimento'] = (int) $value;
    } elseif ($field === 'pagata') {
        $where[] = 'f.pagata = :f_pagata';
        $params[':f_pagata'] = $value === '1' ? 1 : 0;
    } elseif ($field === 'commessa_protocollo') {
        $where[] = 'c.protocollo LIKE :f_commessa_protocollo';
        $params[':f_commessa_protocollo'] = '%' . $value . '%';
    } else {
        $where[] = "f.$field LIKE :$key";
        $params[":$key"] = '%' . $value . '%';
    }
}

$sql = 'SELECT f.*, c.protocollo AS commessa_protocollo, p.data_pagamento, p.modalita_pagamento
        FROM fatture f
        LEFT JOIN commesse c ON c.id = f.commessa_id
        LEFT JOIN pagamenti p ON p.fattura_id = f.id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY f.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fatture = $stmt->fetchAll();

$viewId = isset($_GET['view']) ? (int) $_GET['view'] : 0;
$fatturaDettaglio = null;
if ($viewId > 0) {
    $stmtView = $pdo->prepare('SELECT f.*, c.protocollo AS commessa_protocollo, p.data_pagamento, p.modalita_pagamento
                               FROM fatture f
                               LEFT JOIN commesse c ON c.id = f.commessa_id
                               LEFT JOIN pagamenti p ON p.fattura_id = f.id
                               WHERE f.id = :id');
    $stmtView->execute([':id' => $viewId]);
    $fatturaDettaglio = $stmtView->fetch();
}

$utenteLoggato = currentUser();
renderHeader('Simplex - Fatture');
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
            <h2 class="mb-4">Fatture</h2>

            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

            <?php if ($fatturaDettaglio): ?>
                <div class="card mb-4">
                    <div class="card-header">Dettaglio Fattura <?= htmlspecialchars($fatturaDettaglio['numero']) ?></div>
                    <div class="card-body row g-2">
                        <div class="col-md-3"><strong>Numero:</strong> <?= htmlspecialchars($fatturaDettaglio['numero']) ?></div>
                        <div class="col-md-3"><strong>Anno:</strong> <?= htmlspecialchars((string)$fatturaDettaglio['anno_riferimento']) ?></div>
                        <div class="col-md-3"><strong>Commessa:</strong> <?= htmlspecialchars($fatturaDettaglio['commessa_protocollo'] ?? '-') ?></div>
                        <div class="col-md-3"><strong>Importo:</strong> € <?= number_format((float)$fatturaDettaglio['importo'], 2, ',', '.') ?></div>
                        <div class="col-md-6"><strong>Stato pagamento:</strong> <?= ((int)$fatturaDettaglio['pagata'] === 1) ? 'Pagata' : 'Da pagare' ?></div>
                        <div class="col-md-6"><strong>Dettaglio pagamento:</strong> <?= htmlspecialchars(($fatturaDettaglio['data_pagamento'] ?? '-') . ' / ' . ($fatturaDettaglio['modalita_pagamento'] ?? '-')) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">Filtri Fatture</div>
                <div class="card-body">
                    <form method="get" class="row g-2">
                        <div class="col-md-3"><input class="form-control" name="f_numero" placeholder="Numero" value="<?= htmlspecialchars($_GET['f_numero'] ?? '') ?>"></div>
                        <div class="col-md-2"><input class="form-control" name="f_anno_riferimento" placeholder="Anno" value="<?= htmlspecialchars($_GET['f_anno_riferimento'] ?? '') ?>"></div>
                        <div class="col-md-3"><input class="form-control" name="f_commessa_protocollo" placeholder="Protocollo commessa" value="<?= htmlspecialchars($_GET['f_commessa_protocollo'] ?? '') ?>"></div>
                        <div class="col-md-2">
                            <select class="form-select" name="f_pagata">
                                <option value="">Stato</option>
                                <option value="0" <?= (($_GET['f_pagata'] ?? '') === '0') ? 'selected' : '' ?>>Da pagare</option>
                                <option value="1" <?= (($_GET['f_pagata'] ?? '') === '1') ? 'selected' : '' ?>>Pagata</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary" type="submit">Filtra</button><a class="btn btn-outline-secondary" href="fatture.php">Reset</a></div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco Fatture</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Numero</th><th>Anno</th><th>Commessa</th><th>Importo</th><th>Stato</th><th>Pagamento</th><th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$fatture): ?><tr><td colspan="7" class="text-center text-muted py-4">Nessuna fattura trovata.</td></tr><?php endif; ?>
                        <?php foreach ($fatture as $fattura): ?>
                            <tr>
                                <td><?= htmlspecialchars($fattura['numero']) ?></td>
                                <td><?= htmlspecialchars((string)$fattura['anno_riferimento']) ?></td>
                                <td><?= htmlspecialchars($fattura['commessa_protocollo'] ?? '-') ?></td>
                                <td>€ <?= number_format((float)$fattura['importo'], 2, ',', '.') ?></td>
                                <td><?= ((int)$fattura['pagata'] === 1) ? 'Pagata' : 'Da pagare' ?></td>
                                <td><?= htmlspecialchars(($fattura['data_pagamento'] ?? '-') . ' / ' . ($fattura['modalita_pagamento'] ?? '-')) ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="fatture.php?view=<?= (int)$fattura['id'] ?>">📄</a>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success btn-paga"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalPagamento"
                                        data-fattura-id="<?= (int)$fattura['id'] ?>"
                                        data-fattura-numero="<?= htmlspecialchars($fattura['numero']) ?>"
                                        title="Registra pagamento"
                                    >💵</button>
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

<div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Registra pagamento fattura <span id="fatturaNumeroModal"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="azione" value="registra_pagamento">
                    <input type="hidden" name="fattura_id" id="fatturaIdModal" value="">
                    <div class="mb-3">
                        <label class="form-label">Data ricezione pagamento</label>
                        <input type="date" class="form-control" name="data_pagamento" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Modalità pagamento</label>
                        <select class="form-select" name="modalita_pagamento" required>
                            <option value="">-- Seleziona --</option>
                            <option value="Contanti">Contanti</option>
                            <option value="Bonifico">Bonifico</option>
                            <option value="Carta di Credito">Carta di Credito</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Conferma</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-paga').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('fatturaIdModal').value = this.getAttribute('data-fattura-id') || '';
        document.getElementById('fatturaNumeroModal').textContent = this.getAttribute('data-fattura-numero') || '';
    });
});
</script>

<?php renderFooter(); ?>
