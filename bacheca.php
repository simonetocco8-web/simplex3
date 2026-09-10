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
$isConsulenteResponsabileArea = $isConsulente && in_array('Responsabile di Area', $ruoli, true);

$consulentiDisponibili = [];
if ($isConsulenteResponsabileArea && (bool) $pdo->query("SHOW TABLES LIKE 'utenti'")->fetchColumn()) {
    if ((bool) $pdo->query("SHOW TABLES LIKE 'utenti_ruoli'")->fetchColumn()) {
        $righeConsulenti = $pdo->query(
            "SELECT DISTINCT u.nome, u.cognome
             FROM utenti u
             INNER JOIN utenti_ruoli ur ON ur.utente_id = u.id
             WHERE ur.ruolo = 'Consulente' AND u.attivo = 1
             ORDER BY u.nome, u.cognome"
        )->fetchAll();
    } else {
        $righeConsulenti = $pdo->query(
            "SELECT nome, cognome FROM utenti WHERE ruolo = 'Consulente' AND attivo = 1 ORDER BY nome, cognome"
        )->fetchAll();
    }
    foreach ($righeConsulenti as $rigaConsulente) {
        $nomeConsulente = trim(($rigaConsulente['nome'] ?? '') . ' ' . ($rigaConsulente['cognome'] ?? ''));
        if ($nomeConsulente !== '') {
            $consulentiDisponibili[] = $nomeConsulente;
        }
    }
}

if (empty($_SESSION['csrf_token_bacheca'])) {
    $_SESSION['csrf_token_bacheca'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'assegna_consulente') {
    if (!$isConsulenteResponsabileArea) {
        http_response_code(403);
        exit('Operazione non autorizzata.');
    }
    if (!hash_equals($_SESSION['csrf_token_bacheca'], (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Richiesta non valida.');
    }

    $commessaId = (int) ($_POST['commessa_id'] ?? 0);
    $consulenteIncaricato = trim((string) ($_POST['consulente_incaricato'] ?? ''));
    if ($commessaId <= 0 || !in_array($consulenteIncaricato, $consulentiDisponibili, true)) {
        $_SESSION['bacheca_errore'] = 'Seleziona un consulente valido.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmtCommessa = $pdo->prepare(
                "SELECT o.id AS offerta_id
                 FROM commesse c INNER JOIN offerte o ON o.id = c.offerta_id
                 WHERE c.id = :commessa_id
                   AND o.stato = 'Aggiudicata'
                   AND (o.consulente_incaricato IS NULL OR TRIM(o.consulente_incaricato) = '')
                 FOR UPDATE"
            );
            $stmtCommessa->execute([':commessa_id' => $commessaId]);
            $offertaId = (int) $stmtCommessa->fetchColumn();
            if ($offertaId <= 0) {
                throw new RuntimeException('Commessa aggiudicata non trovata.');
            }
            $pdo->prepare('UPDATE offerte SET consulente_incaricato = :consulente WHERE id = :id')->execute([
                ':consulente' => $consulenteIncaricato,
                ':id' => $offertaId,
            ]);
            $pdo->prepare(
                'UPDATE commesse SET consulente_nome = :consulente, consulente_codice = :codice WHERE id = :id'
            )->execute([
                ':consulente' => $consulenteIncaricato,
                ':codice' => substr($consulenteIncaricato, 0, 2),
                ':id' => $commessaId,
            ]);
            $pdo->commit();
            $_SESSION['bacheca_successo'] = 'Consulente incaricato aggiornato correttamente.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['bacheca_errore'] = $e->getMessage();
        }
    }
    header('Location: bacheca.php');
    exit;
}

$messaggioSuccesso = $_SESSION['bacheca_successo'] ?? null;
$messaggioErrore = $_SESSION['bacheca_errore'] ?? null;
unset($_SESSION['bacheca_successo'], $_SESSION['bacheca_errore']);

$commesseVisibili = [];
if (($isAdminOrArea || ($isConsulente && $nomeCompleto !== '')) && (bool) $pdo->query("SHOW TABLES LIKE 'commesse'")->fetchColumn()) {
    $sqlCommesse =
        'SELECT c.*, o.protocollo AS offerta_protocollo, o.servizio, o.stato,
                COALESCE(a_commessa.ragione_sociale, a_offerta.ragione_sociale) AS azienda_nome
         FROM commesse c
         LEFT JOIN offerte o ON o.id = c.offerta_id
         LEFT JOIN aziende a_offerta ON a_offerta.id = o.azienda_id
         LEFT JOIN aziende a_commessa ON a_commessa.id = c.azienda_cliente_id';
    $parametriCommesse = [];

    // I responsabili di area (e gli amministratori) vedono tutte le commesse;
    // il filtro si applica esclusivamente agli utenti che sono solo consulenti.
    if ($isConsulente && !$isAdminOrArea) {
        $sqlCommesse .= ' WHERE o.consulente_incaricato = :consulente_nome';
        $parametriCommesse[':consulente_nome'] = $nomeCompleto;
    }

    $sqlCommesse .= ' ORDER BY c.creata_il DESC';
    $stmtCommesse = $pdo->prepare($sqlCommesse);
    $stmtCommesse->execute($parametriCommesse);
    $commesseVisibili = $stmtCommesse->fetchAll();
}

$commesseDaAssegnare = [];
if ($isConsulenteResponsabileArea && (bool) $pdo->query("SHOW TABLES LIKE 'commesse'")->fetchColumn()) {
    $commesseDaAssegnare = $pdo->query(
        "SELECT c.id, c.protocollo, o.protocollo AS offerta_protocollo, o.consulente_incaricato,
                o.servizio, COALESCE(a_commessa.ragione_sociale, a_offerta.ragione_sociale) AS azienda_nome
         FROM commesse c
         INNER JOIN offerte o ON o.id = c.offerta_id
         LEFT JOIN aziende a_offerta ON a_offerta.id = o.azienda_id
         LEFT JOIN aziende a_commessa ON a_commessa.id = c.azienda_cliente_id
         WHERE o.stato = 'Aggiudicata'
           AND (o.consulente_incaricato IS NULL OR TRIM(o.consulente_incaricato) = '')
         ORDER BY c.creata_il DESC"
    )->fetchAll();
}

$offerteScadenza = [];
if ($isAdminOrArea && (bool) $pdo->query("SHOW TABLES LIKE 'offerte'")->fetchColumn()) {
    $offerteScadenza = $pdo->query(
        "SELECT o.id, o.protocollo, o.servizio, o.stato, o.data_offerta, o.data_scadenza, o.validita_giorni,
                a.ragione_sociale AS azienda_nome,
                c.id AS commessa_id, c.protocollo AS commessa_protocollo
         FROM offerte o
         LEFT JOIN aziende a ON a.id = o.azienda_id
         LEFT JOIN commesse c ON c.offerta_id = o.id
         WHERE o.stato = 'Aggiudicata'
         ORDER BY (o.data_scadenza IS NULL), o.data_scadenza ASC, o.id DESC"
    )->fetchAll();
}

renderHeader('Simplex - Bacheca');
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
            <div class="text-white small border-top pt-3">
                <div>Connesso come:</div>
                <strong><?= htmlspecialchars($nomeCompleto ?: '-') ?></strong><br>
                <span class="text-light-emphasis"><?= htmlspecialchars(implode(', ', $ruoli)) ?></span>
                <div class="mt-2"><a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a></div>
            </div>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <h2 class="mb-4">Bacheca</h2>

            <?php if ($messaggioSuccesso): ?>
                <div class="alert alert-success"><?= htmlspecialchars($messaggioSuccesso) ?></div>
            <?php endif; ?>
            <?php if ($messaggioErrore): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($messaggioErrore) ?></div>
            <?php endif; ?>

            <?php if ($isConsulenteResponsabileArea): ?>
                <div class="card mb-4">
                    <div class="card-header">Commesse da Assegnare</div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Protocollo Commessa</th>
                                <th>Protocollo Offerta</th>
                                <th>Azienda</th>
                                <th>Servizio</th>
                                <th>Consulente incaricato</th>
                                <th class="text-center">Assegna</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$commesseDaAssegnare): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">Nessuna commessa aggiudicata presente.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($commesseDaAssegnare as $commessa): ?>
                                <tr>
                                    <td><a href="commesse.php?edit=<?= (int) $commessa['id'] ?>"><?= htmlspecialchars($commessa['protocollo']) ?></a></td>
                                    <td><?= htmlspecialchars($commessa['offerta_protocollo'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($commessa['azienda_nome'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($commessa['servizio'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($commessa['consulente_incaricato'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary js-assegna-consulente"
                                                data-bs-toggle="modal" data-bs-target="#modalAssegnaConsulente"
                                                data-commessa-id="<?= (int) $commessa['id'] ?>"
                                                data-commessa-protocollo="<?= htmlspecialchars($commessa['protocollo'], ENT_QUOTES) ?>"
                                                data-consulente="<?= htmlspecialchars($commessa['consulente_incaricato'] ?? '', ENT_QUOTES) ?>"
                                                title="Visualizza o modifica il consulente incaricato"
                                                aria-label="Assegna consulente alla commessa <?= htmlspecialchars($commessa['protocollo'], ENT_QUOTES) ?>">👤</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isConsulente || $isAdminOrArea): ?>
                <div class="card mb-4">
                    <div class="card-header"><?= $isAdminOrArea ? 'Tutte le commesse' : 'Commesse assegnate al tuo account (Consulente)' ?></div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Protocollo Commessa</th>
                                <th>Protocollo Offerta</th>
                                <th>Azienda</th>
                                <th>Servizio</th>
                                <th>Stato Offerta</th>
                                <th>Data Creazione</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$commesseVisibili): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4"><?= $isAdminOrArea ? 'Nessuna commessa presente.' : 'Nessuna commessa assegnata.' ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($commesseVisibili as $commessa): ?>
                                <tr>
                                    <td><a href="commesse.php?edit=<?= (int)$commessa['id'] ?>"><?= htmlspecialchars($commessa['protocollo']) ?></a></td>
                                    <td><a href="offerte.php?view=<?= (int)$commessa['offerta_id'] ?>"><?= htmlspecialchars($commessa['offerta_protocollo'] ?? '-') ?></a></td>
                                    <td><?= htmlspecialchars($commessa['azienda_nome'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($commessa['servizio'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($commessa['stato'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(formatDateIt($commessa['creata_il'] ?? null)) ?></td>
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
                                <th>Prot. Offerta</th>
                                <th>Prot. Commessa</th>
                                <th>Azienda</th>
                                <th>Servizio</th>
                                <th>Stato</th>
                                <th>Data Offerta</th>
                                <th>Data Scadenza</th>
                                <th>Validità (gg)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$offerteScadenza): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Nessuna offerta presente.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($offerteScadenza as $offerta): ?>
                                <tr>
                                    <td><a href="offerte.php?view=<?= (int)$offerta['id'] ?>"><?= htmlspecialchars($offerta['protocollo']) ?></a></td>
                                    <td>
                                        <?php if (!empty($offerta['commessa_id'])): ?>
                                            <a href="commesse.php?edit=<?= (int)$offerta['commessa_id'] ?>"><?= htmlspecialchars($offerta['commessa_protocollo'] ?? '-') ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($offerta['azienda_nome'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($offerta['servizio']) ?></td>
                                    <td><?= htmlspecialchars($offerta['stato']) ?></td>
                                    <td><?= htmlspecialchars(formatDateIt($offerta['data_offerta'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars(formatDateIt($offerta['data_scadenza'] ?? null)) ?></td>
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

<?php if ($isConsulenteResponsabileArea): ?>
<div class="modal fade" id="modalAssegnaConsulente" tabindex="-1" aria-labelledby="modalAssegnaConsulenteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAssegnaConsulenteLabel">Consulente incaricato (per Aggiudicata)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <p>Commessa: <strong id="protocolloCommessaModal"></strong></p>
                    <input type="hidden" name="azione" value="assegna_consulente">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token_bacheca']) ?>">
                    <input type="hidden" name="commessa_id" id="commessaIdModal">
                    <label class="form-label" for="consulenteIncaricatoModal">Consulente incaricato</label>
                    <select class="form-select" name="consulente_incaricato" id="consulenteIncaricatoModal" required>
                        <option value="">-- Seleziona --</option>
                        <?php foreach ($consulentiDisponibili as $consulente): ?>
                            <option value="<?= htmlspecialchars($consulente) ?>"><?= htmlspecialchars($consulente) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva assegnazione</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.js-assegna-consulente').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById('commessaIdModal').value = button.dataset.commessaId;
        document.getElementById('protocolloCommessaModal').textContent = button.dataset.commessaProtocollo;
        document.getElementById('consulenteIncaricatoModal').value = button.dataset.consulente;
    });
});
</script>
<?php endif; ?>
<?php renderFooter(); ?>
