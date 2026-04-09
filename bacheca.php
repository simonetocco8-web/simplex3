<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$current = currentUser();
$utenteId = (int) ($current['id'] ?? 0);
$nomeCompleto = trim((string) ($current['nome_completo'] ?? ''));

$ruoli = [];
if ((bool) $pdo->query("SHOW TABLES LIKE 'utenti_ruoli'")->fetchColumn()) {
    $stmtRuoli = $pdo->prepare('SELECT ruolo FROM utenti_ruoli WHERE utente_id = :utente_id');
    $stmtRuoli->execute([':utente_id' => $utenteId]);
    $ruoli = array_column($stmtRuoli->fetchAll(), 'ruolo');
}
if (!$ruoli && !empty($current['ruolo'])) {
    $ruoli = [(string) $current['ruolo']];
}

$isConsulente = in_array('Consulente', $ruoli, true);
$isAdminOrArea = in_array('Amministratore', $ruoli, true) || in_array('Responsabile di Area', $ruoli, true);

$commesseAssegnate = [];
if ($isConsulente && $nomeCompleto !== '' && (bool) $pdo->query("SHOW TABLES LIKE 'commesse'")->fetchColumn()) {
    $stmtCommesse = $pdo->prepare(
        'SELECT c.*, o.protocollo AS offerta_protocollo, o.servizio, o.stato
         FROM commesse c
         LEFT JOIN offerte o ON o.id = c.offerta_id
         WHERE c.consulente_nome = :consulente_nome
         ORDER BY c.creata_il DESC'
    );
    $stmtCommesse->execute([':consulente_nome' => $nomeCompleto]);
    $commesseAssegnate = $stmtCommesse->fetchAll();
}

$offerteScadenza = [];
if ($isAdminOrArea && (bool) $pdo->query("SHOW TABLES LIKE 'offerte'")->fetchColumn()) {
    $offerteScadenza = $pdo->query(
        "SELECT id, protocollo, servizio, stato, data_offerta, data_scadenza, validita_giorni
         FROM offerte
         ORDER BY (data_scadenza IS NULL), data_scadenza ASC, id DESC"
    )->fetchAll();
}

renderHeader('Simplex - Bacheca');
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2 mb-3">
                <li class="nav-item"><a class="nav-link active" href="bacheca.php">Bacheca</a></li>
                <li class="nav-item"><a class="nav-link" href="utenti.php">Utenti</a></li>
                <li class="nav-item"><a class="nav-link" href="aziende.php">Aziende</a></li>
                <li class="nav-item"><a class="nav-link" href="enti_certificazione.php">Enti di Certificazione</a></li>
                <li class="nav-item"><a class="nav-link" href="offerte.php">Offerte</a></li>
                <li class="nav-item"><a class="nav-link" href="commesse.php">Commesse</a></li>
                <li class="nav-item"><a class="nav-link" href="amministrazione_produzione.php">Amministrazione</a></li>
                <li class="nav-item"><a class="nav-link" href="fatture.php">Fatture</a></li>
                <li class="nav-item"><a class="nav-link" href="pagamenti.php">Pagamenti</a></li>
                <li class="nav-item"><a class="nav-link disabled" href="#">Impostazioni</a></li>
            </ul>
            <div class="text-white small border-top pt-3">
                <div>Connesso come:</div>
                <strong><?= htmlspecialchars($nomeCompleto ?: '-') ?></strong><br>
                <span class="text-light-emphasis"><?= htmlspecialchars(implode(', ', $ruoli)) ?></span>
                <div class="mt-2"><a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a></div>
            </div>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <h2 class="mb-4">Bacheca</h2>

            <?php if ($isConsulente): ?>
                <div class="card mb-4">
                    <div class="card-header">Commesse assegnate al tuo account (Consulente)</div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Protocollo Commessa</th>
                                <th>Protocollo Offerta</th>
                                <th>Servizio</th>
                                <th>Stato Offerta</th>
                                <th>Data Creazione</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$commesseAssegnate): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Nessuna commessa assegnata.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($commesseAssegnate as $commessa): ?>
                                <tr>
                                    <td><?= htmlspecialchars($commessa['protocollo']) ?></td>
                                    <td><a href="offerte.php?view=<?= (int)$commessa['offerta_id'] ?>"><?= htmlspecialchars($commessa['offerta_protocollo'] ?? '-') ?></a></td>
                                    <td><?= htmlspecialchars($commessa['servizio'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($commessa['stato'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($commessa['creata_il'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isAdminOrArea): ?>
                <div class="card mb-4">
                    <div class="card-header">Offerte ordinate per data di scadenza crescente</div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Protocollo</th>
                                <th>Servizio</th>
                                <th>Stato</th>
                                <th>Data Offerta</th>
                                <th>Data Scadenza</th>
                                <th>Validità (gg)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$offerteScadenza): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">Nessuna offerta presente.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($offerteScadenza as $offerta): ?>
                                <tr>
                                    <td><a href="offerte.php?view=<?= (int)$offerta['id'] ?>"><?= htmlspecialchars($offerta['protocollo']) ?></a></td>
                                    <td><?= htmlspecialchars($offerta['servizio']) ?></td>
                                    <td><?= htmlspecialchars($offerta['stato']) ?></td>
                                    <td><?= htmlspecialchars($offerta['data_offerta'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($offerta['data_scadenza'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars((string)($offerta['validita_giorni'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$isConsulente && !$isAdminOrArea): ?>
                <div class="alert alert-info">Nessun contenuto disponibile per i tuoi ruoli correnti.</div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php renderFooter(); ?>
