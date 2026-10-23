<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/production.php';
require_once __DIR__ . '/includes/consuntivo.php';
// Timestamp values are compared as UTC; calendar periods are computed in Europe/Rome.
$pdo->exec("SET time_zone = '+00:00'");
$periods = productionPeriods();
$period = (string)($_GET['f_periodo'] ?? 'tutto');
if (!isset($periods[$period])) $period = 'tutto';
$service = trim((string)($_GET['f_servizio'] ?? ''));
$specific = trim((string)($_GET['f_servizio_specifico'] ?? ''));
$services = $pdo->query("SELECT DISTINCT servizio FROM offerte WHERE servizio <> '' ORDER BY servizio")->fetchAll(PDO::FETCH_COLUMN);
$specificStmt = $pdo->prepare("SELECT DISTINCT dettaglio_servizio FROM offerte WHERE dettaglio_servizio <> ''"
    . ($service !== '' ? ' AND servizio = :servizio' : '') . ' ORDER BY dettaglio_servizio');
$specificStmt->execute($service !== '' ? ['servizio' => $service] : []);
$specifics = $specificStmt->fetchAll(PDO::FETCH_COLUMN);
$where = [];
$params = [];
[$start, $end] = productionPeriodBounds($period);
if ($start !== null) {
    $where[] = 'c.creata_il >= :inizio AND c.creata_il < :fine';
    $params['inizio'] = $start;
    $params['fine'] = $end;
}
if ($service !== '') { $where[] = 'o.servizio = :servizio'; $params['servizio'] = $service; }
if ($specific !== '') { $where[] = 'o.dettaglio_servizio = :specifico'; $params['specifico'] = $specific; }
// Resolve old name-only assignments only when exactly one account has that name.
$hasUserId = (bool)$pdo->query("SHOW COLUMNS FROM commesse LIKE 'consulente_utente_id'")->fetch();
$userId = $hasUserId ? 'c.consulente_utente_id' : 'NULL';
$stateColumn = $pdo->query("SHOW COLUMNS FROM commesse LIKE 'stato'")->fetch() ? 'c.stato' : "'In corso'";
$sql = "SELECT c.id, $stateColumn AS stato, COALESCE($userId, legacy.id) AS utente_id,
               ROUND(COALESCE(importi.subtotale,0) * (1 - COALESCE(o.sconto_percentuale,0)/100),2) AS importo
        FROM commesse c
        LEFT JOIN offerte o ON o.id=c.offerta_id
        LEFT JOIN (
            SELECT TRIM(CONCAT(nome,' ',cognome)) AS nome, MIN(id) AS id
            FROM utenti GROUP BY TRIM(CONCAT(nome,' ',cognome)) HAVING COUNT(*)=1
        ) legacy ON $userId IS NULL AND legacy.nome =
            COALESCE(NULLIF(TRIM(o.consulente_incaricato),''), NULLIF(TRIM(c.consulente_nome),'N/D'))
        LEFT JOIN (
            SELECT offerta_id,SUM(costo_totale) AS subtotale
            FROM offerta_momenti_lavorazione GROUP BY offerta_id
        ) importi ON importi.offerta_id=o.id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productionRows = $stmt->fetchAll();
$users = $pdo->query('SELECT id,nome,cognome,nome_utente FROM utenti ORDER BY nome,cognome,id')->fetchAll();
$workloads = [];
foreach ($users as $user) {
    $workloads[(int)$user['id']] = $user + ['aperte' => 0, 'gestite' => 0, 'importo_centesimi' => 0];
}
$unassigned = 0;
$unassignedAmount = 0;
foreach ($productionRows as $row) {
    $id = (int)($row['utente_id'] ?? 0);
    $cents = consuntivoCents($row['importo']);
    if (!isset($workloads[$id])) { $unassigned++; $unassignedAmount += $cents; continue; }
    $workloads[$id]['gestite']++;
    if ($row['stato'] !== 'Completato') $workloads[$id]['aperte']++;
    $workloads[$id]['importo_centesimi'] += $cents;
}
$totalOpen = array_sum(array_column($workloads, 'aperte'));
$totalManaged = array_sum(array_column($workloads, 'gestite'));
$totalAmount = array_sum(array_column($workloads, 'importo_centesimi'));

$utenteLoggato = currentUser();
renderHeader('Simplex - Amministrazione / Produzione');
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2 mb-3">
                <li class="nav-item"><a <?= navAttributes('bacheca.php') ?> href="bacheca.php"><?= menuIcon('Bacheca') ?> Bacheca</a></li>
                <li class="nav-item"><a <?= navAttributes('offerte.php') ?> href="offerte.php"><?= menuIcon('Offerte') ?> Offerte</a></li>
                <li class="nav-item"><a <?= navAttributes('commesse.php') ?> href="commesse.php"><?= menuIcon('Commesse') ?> Commesse</a></li>

                <li class="nav-item">
                    <a class="nav-link d-flex justify-content-between align-items-center<?= navGroupActive('Anagrafiche') ? ' active' : '' ?>" data-bs-toggle="collapse" href="#menuAnagrafiche" role="button" aria-expanded="<?= navGroupActive('Anagrafiche') ? 'true' : 'false' ?>" aria-controls="menuAnagrafiche">
                        <span><?= menuIcon('Anagrafiche') ?> Anagrafiche</span><span>▾</span>
                    </a>
                    <ul class="nav flex-column ms-3 collapse<?= navGroupActive('Anagrafiche') ? ' show' : '' ?>" id="menuAnagrafiche">
                        <li class="nav-item"><a <?= navAttributes('utenti.php') ?> href="utenti.php"><?= menuIcon('Utenti') ?> Utenti</a></li>
                        <li class="nav-item"><a <?= navAttributes('aziende.php') ?> href="aziende.php"><?= menuIcon('Aziende') ?> Aziende</a></li>
                        <li class="nav-item"><a <?= navAttributes('enti_certificazione.php') ?> href="enti_certificazione.php"><?= menuIcon('Enti di Certificazione') ?> Enti di Certificazione</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex justify-content-between align-items-center<?= navGroupActive('Amministrazione') ? ' active' : '' ?>" data-bs-toggle="collapse" href="#menuAmministrazione" role="button" aria-expanded="<?= navGroupActive('Amministrazione') ? 'true' : 'false' ?>" aria-controls="menuAmministrazione">
                        <span><?= menuIcon('Amministrazione') ?> Amministrazione</span><span>▾</span>
                    </a>
                    <ul class="nav flex-column ms-3 collapse<?= navGroupActive('Amministrazione') ? ' show' : '' ?>" id="menuAmministrazione">
                        <li class="nav-item"><a <?= navAttributes('amministrazione_produzione.php') ?> href="amministrazione_produzione.php"><?= menuIcon('Produzione') ?> Produzione</a></li>
                        <li class="nav-item"><a <?= navAttributes('proforme.php') ?> href="proforme.php"><?= menuIcon('Pro-Forma') ?> Pro-Forma</a></li>
                        <li class="nav-item"><a <?= navAttributes('fatture.php') ?> href="fatture.php"><?= menuIcon('Fatture') ?> Fatture</a></li>
                        <li class="nav-item"><a <?= navAttributes('pagamenti.php') ?> href="pagamenti.php"><?= menuIcon('Pagamenti') ?> Pagamenti</a></li>
                    </ul>
                </li>

                <li class="nav-item"><a class="nav-link disabled" href="#"><?= menuIcon('Impostazioni') ?> Impostazioni</a></li>
            </ul>
            <?php if ($utenteLoggato): ?>
                <div class="text-white small border-top pt-3">
                    <div>Connesso come:</div>
                    <strong><?= htmlspecialchars($utenteLoggato['nome_completo']) ?></strong><br>
                    <span class="text-light-emphasis"><?= htmlspecialchars($utenteLoggato['nome_utente']) ?></span>
                    <div class="mt-2"><a class="btn btn-outline-light btn-sm" href="logout.php"><?= menuIcon('Logout') ?> Logout</a></div>
                </div>
            <?php endif; ?>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <h2 class="mb-4">Amministrazione / Produzione</h2>

            <p class="text-muted">Carico di Lavoro: commesse aperte assegnate all'utente. Importo: totale delle offerte collegate, al netto dello sconto,
                per tutte le commesse gestite (aperte e chiuse). Il periodo filtra la data di creazione della commessa, nel fuso orario italiano.</p>
            <div class="card mb-4">
                <div class="card-header">Filtra produzione</div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-3"><label class="form-label" for="periodo">Periodo</label>
                            <select class="form-select" name="f_periodo" id="periodo">
                                <?php foreach ($periods as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $period === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label" for="servizio">Servizio</label>
                            <select class="form-select" name="f_servizio" id="servizio" onchange="this.form.elements.f_servizio_specifico.value='';this.form.submit()">
                                <option value="">Tutti i servizi</option>
                                <?php foreach ($services as $value): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $service === $value ? 'selected' : '' ?>><?= htmlspecialchars($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label" for="specifico">Servizio Specifico</label>
                            <select class="form-select" name="f_servizio_specifico" id="specifico">
                                <option value="">Tutti i servizi specifici</option>
                                <?php foreach ($specifics as $value): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $specific === $value ? 'selected' : '' ?>><?= htmlspecialchars($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2"><button class="btn btn-primary" type="submit">Filtra</button><a class="btn btn-outline-secondary" href="amministrazione_produzione.php">Reset</a></div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Carico di Lavoro e Importo per utente</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead><tr><th>Utente</th><th class="text-end">Carico di Lavoro (aperte)</th><th class="text-end">Commesse gestite</th><th class="text-end">Importo</th></tr></thead>
                        <tbody>
                        <?php foreach ($workloads as $row): ?>
                            <tr data-utente-id="<?= (int)$row['id'] ?>">
                                <td><?= htmlspecialchars(trim($row['nome'].' '.$row['cognome'])) ?> <span class="text-muted small">(<?= htmlspecialchars($row['nome_utente']) ?>)</span></td>
                                <td class="text-end" data-metric="carico"><?= $row['aperte'] ?></td>
                                <td class="text-end" data-metric="gestite"><?= $row['gestite'] ?></td>
                                <td class="text-end" data-metric="importo">€ <?= number_format($row['importo_centesimi']/100,2,',','.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$workloads): ?><tr><td colspan="4">Nessun utente presente.</td></tr><?php endif; ?>
                        </tbody>
                        <tfoot><tr class="fw-bold"><td>Totale utenti</td><td class="text-end"><?= $totalOpen ?></td><td class="text-end"><?= $totalManaged ?></td><td class="text-end">€ <?= number_format($totalAmount/100,2,',','.') ?></td></tr></tfoot>
                    </table>
                </div>
            </div>
            <?php if ($unassigned > 0): ?>
                <p class="text-muted mt-3">Commesse senza utente identificabile: <?= $unassigned ?> · Importo: € <?= number_format($unassignedAmount/100,2,',','.') ?>.
                    Queste commesse sono escluse dai totali per utente.</p>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php renderFooter(); ?>
