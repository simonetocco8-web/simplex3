<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS pagamenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fattura_id INT UNSIGNED NOT NULL UNIQUE,
    data_pagamento DATE NOT NULL,
    modalita_pagamento ENUM('Contanti', 'Bonifico', 'Carta di Credito') NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$filters = ['fattura_numero', 'data_pagamento', 'modalita_pagamento'];
$where = [];
$params = [];
foreach ($filters as $field) {
    $key = 'f_' . $field;
    $value = trim((string) ($_GET[$key] ?? ''));
    if ($value === '') {
        continue;
    }

    if ($field === 'fattura_numero') {
        $where[] = 'f.numero LIKE :f_fattura_numero';
        $params[':f_fattura_numero'] = '%' . $value . '%';
    } elseif ($field === 'data_pagamento') {
        $where[] = 'p.data_pagamento = :f_data_pagamento';
        $params[':f_data_pagamento'] = $value;
    } elseif ($field === 'modalita_pagamento') {
        $where[] = 'p.modalita_pagamento = :f_modalita_pagamento';
        $params[':f_modalita_pagamento'] = $value;
    }
}

$sql = 'SELECT p.*, f.numero AS fattura_numero, f.importo AS fattura_importo, c.protocollo AS commessa_protocollo
        FROM pagamenti p
        INNER JOIN fatture f ON f.id = p.fattura_id
        LEFT JOIN commesse c ON c.id = f.commessa_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pagamenti = $stmt->fetchAll();

$utenteLoggato = currentUser();
renderHeader('Simplex - Pagamenti');
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
            <h2 class="mb-4">Pagamenti</h2>

            <div class="card mb-4">
                <div class="card-header">Filtri Pagamenti</div>
                <div class="card-body">
                    <form method="get" class="row g-2">
                        <div class="col-md-4"><input class="form-control" name="f_fattura_numero" placeholder="Numero fattura" value="<?= htmlspecialchars($_GET['f_fattura_numero'] ?? '') ?>"></div>
                        <div class="col-md-3"><input type="date" class="form-control" name="f_data_pagamento" value="<?= htmlspecialchars($_GET['f_data_pagamento'] ?? '') ?>"></div>
                        <div class="col-md-3">
                            <select class="form-select" name="f_modalita_pagamento">
                                <option value="">Modalità pagamento</option>
                                <option value="Contanti" <?= (($_GET['f_modalita_pagamento'] ?? '') === 'Contanti') ? 'selected' : '' ?>>Contanti</option>
                                <option value="Bonifico" <?= (($_GET['f_modalita_pagamento'] ?? '') === 'Bonifico') ? 'selected' : '' ?>>Bonifico</option>
                                <option value="Carta di Credito" <?= (($_GET['f_modalita_pagamento'] ?? '') === 'Carta di Credito') ? 'selected' : '' ?>>Carta di Credito</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary" type="submit">Filtra</button><a class="btn btn-outline-secondary" href="pagamenti.php">Reset</a></div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco Pagamenti</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Fattura</th><th>Commessa</th><th>Importo Fattura</th><th>Data Pagamento</th><th>Modalità</th><th>Creato il</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$pagamenti): ?><tr><td colspan="6" class="text-center text-muted py-4">Nessun pagamento trovato.</td></tr><?php endif; ?>
                        <?php foreach ($pagamenti as $pagamento): ?>
                            <tr>
                                <td><a href="fatture.php?view=<?= (int)$pagamento['fattura_id'] ?>"><?= htmlspecialchars($pagamento['fattura_numero']) ?></a></td>
                                <td><?= htmlspecialchars($pagamento['commessa_protocollo'] ?? '-') ?></td>
                                <td>€ <?= number_format((float)$pagamento['fattura_importo'], 2, ',', '.') ?></td>
                                <td><?= htmlspecialchars($pagamento['data_pagamento']) ?></td>
                                <td><?= htmlspecialchars($pagamento['modalita_pagamento']) ?></td>
                                <td><?= htmlspecialchars($pagamento['creato_il']) ?></td>
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
