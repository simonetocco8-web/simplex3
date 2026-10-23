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
$isResponsabileArea = in_array('Responsabile di Area', $ruoli, true);
$isAdminOrArea = in_array('Amministratore', $ruoli, true) || in_array('Responsabile di Area', $ruoli, true);

// Link new assignments to a user ID; retain names for existing reports and legacy jobs.
if (!(bool)$pdo->query("SHOW COLUMNS FROM commesse LIKE 'consulente_utente_id'")->fetch()) {
    $pdo->exec("ALTER TABLE commesse ADD COLUMN consulente_utente_id INT UNSIGNED NULL,
        ADD CONSTRAINT fk_commessa_consulente_utente FOREIGN KEY (consulente_utente_id) REFERENCES utenti(id) ON DELETE SET NULL");
}

$consulentiDisponibili = [];
if ($isAdminOrArea && (bool) $pdo->query("SHOW TABLES LIKE 'utenti'")->fetchColumn()) {
    if ((bool) $pdo->query("SHOW TABLES LIKE 'utenti_ruoli'")->fetchColumn()) {
        $righeConsulenti = $pdo->query(
            "SELECT DISTINCT u.id, u.nome, u.cognome
             FROM utenti u
             LEFT JOIN utenti_ruoli ur ON ur.utente_id = u.id
             WHERE (ur.ruolo = 'Consulente' OR u.ruolo = 'Consulente') AND u.attivo = 1
             ORDER BY u.nome, u.cognome"
        )->fetchAll();
    } else {
        $righeConsulenti = $pdo->query(
            "SELECT id, nome, cognome FROM utenti WHERE ruolo = 'Consulente' AND attivo = 1 ORDER BY nome, cognome"
        )->fetchAll();
    }
    foreach ($righeConsulenti as $rigaConsulente) {
        $nomeConsulente = trim(($rigaConsulente['nome'] ?? '') . ' ' . ($rigaConsulente['cognome'] ?? ''));
        if ($nomeConsulente !== '') {
            $consulentiDisponibili[(int)$rigaConsulente['id']] = $nomeConsulente;
        }
    }
}

if (empty($_SESSION['csrf_token_bacheca'])) {
    $_SESSION['csrf_token_bacheca'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'assegna_consulente') {
    if (!$isAdminOrArea) {
        http_response_code(403);
        exit('Operazione non autorizzata.');
    }
    if (!hash_equals($_SESSION['csrf_token_bacheca'], (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Richiesta non valida.');
    }

    $commessaId = (int) ($_POST['commessa_id'] ?? 0);
    $consulenteId = (int)($_POST['consulente_utente_id'] ?? 0);
    if ($commessaId <= 0 || !isset($consulentiDisponibili[$consulenteId])) {
        $_SESSION['bacheca_errore'] = 'Seleziona un consulente valido.';
    } else {
        $consulenteIncaricato = $consulentiDisponibili[$consulenteId];
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
                'UPDATE commesse SET consulente_nome = :consulente, consulente_codice = :codice, consulente_utente_id = :consulente_id WHERE id = :id'
            )->execute([
                ':consulente' => $consulenteIncaricato,
                ':codice' => substr($consulenteIncaricato, 0, 2),
                ':id' => $commessaId,
                ':consulente_id' => $consulenteId,
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
if ($isConsulente && !$isAdminOrArea && $nomeCompleto !== '' && (bool) $pdo->query("SHOW TABLES LIKE 'commesse'")->fetchColumn()) {
    $sqlCommesse =
        'SELECT c.*, o.protocollo AS offerta_protocollo, o.servizio, o.stato,
                COALESCE(a_commessa.ragione_sociale, a_offerta.ragione_sociale) AS azienda_nome
         FROM commesse c
         LEFT JOIN offerte o ON o.id = c.offerta_id
         LEFT JOIN aziende a_offerta ON a_offerta.id = o.azienda_id
         LEFT JOIN aziende a_commessa ON a_commessa.id = c.azienda_cliente_id';
    $parametriCommesse = [];

    $sqlCommesse .= ' WHERE c.consulente_utente_id = :utente_id
        OR (c.consulente_utente_id IS NULL AND o.consulente_incaricato = :consulente_nome)';
    $parametriCommesse[':utente_id'] = $utenteId;
    $parametriCommesse[':consulente_nome'] = $nomeCompleto;

    $sqlCommesse .= ' ORDER BY c.creata_il DESC';
    $stmtCommesse = $pdo->prepare($sqlCommesse);
    $stmtCommesse->execute($parametriCommesse);
    $commesseVisibili = $stmtCommesse->fetchAll();
}

$commesseDaAssegnare = [];
if ($isAdminOrArea && (bool) $pdo->query("SHOW TABLES LIKE 'commesse'")->fetchColumn()) {
    $stmtDaAssegnare = $pdo->prepare(
        "SELECT c.id, c.protocollo, o.protocollo AS offerta_protocollo, o.consulente_incaricato,
                o.servizio, o.note,
                COALESCE(importi.subtotale, 0) * (1 - COALESCE(o.sconto_percentuale, 0) / 100) AS importo_totale,
                COALESCE(a_commessa.ragione_sociale, a_offerta.ragione_sociale) AS azienda_nome
         FROM commesse c
         INNER JOIN offerte o ON o.id = c.offerta_id
         LEFT JOIN aziende a_offerta ON a_offerta.id = o.azienda_id
         LEFT JOIN aziende a_commessa ON a_commessa.id = c.azienda_cliente_id
         LEFT JOIN (
             SELECT offerta_id, SUM(costo_totale) AS subtotale
             FROM offerta_momenti_lavorazione GROUP BY offerta_id
         ) importi ON importi.offerta_id = o.id
         WHERE o.stato = 'Aggiudicata'
           AND (o.consulente_incaricato IS NULL OR TRIM(o.consulente_incaricato) = '')
         ORDER BY c.creata_il DESC"
    );
    $stmtDaAssegnare->execute();
    $commesseDaAssegnare = $stmtDaAssegnare->fetchAll();
}

renderHeader('Simplex - Bacheca');
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
            <div class="text-white small border-top pt-3">
                <div>Connesso come:</div>
                <strong><?= htmlspecialchars($nomeCompleto ?: '-') ?></strong><br>
                <span class="text-light-emphasis"><?= htmlspecialchars(implode(', ', $ruoli)) ?></span>
                <div class="mt-2"><a class="btn btn-outline-light btn-sm" href="logout.php"><?= menuIcon('Logout') ?> Logout</a></div>
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

            <?php if ($isAdminOrArea): ?>
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
                                <th class="text-end">Importo</th>
                                <th>Note</th>
                                <th>Consulente incaricato</th>
                                <th class="text-center">Assegna</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$commesseDaAssegnare): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Nessuna commessa aggiudicata presente.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($commesseDaAssegnare as $commessa): ?>
                                <tr>
                                    <td><a href="commesse.php?edit=<?= (int) $commessa['id'] ?>"><?= htmlspecialchars($commessa['protocollo']) ?></a></td>
                                    <td><?= htmlspecialchars($commessa['offerta_protocollo'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($commessa['azienda_nome'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($commessa['servizio'] ?? '-') ?></td>
                                    <td class="text-end">€ <?= number_format((float)$commessa['importo_totale'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php if (trim((string)($commessa['note'] ?? '')) !== ''): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-note-offerta"
                                                    data-bs-toggle="modal" data-bs-target="#modalNoteOfferta"
                                                    data-note="<?= htmlspecialchars($commessa['note'], ENT_QUOTES) ?>"
                                                    title="Visualizza note offerta" aria-label="Visualizza note offerta"><?= menuIcon('Note') ?></button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($commessa['consulente_incaricato'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary js-assegna-consulente"
                                                data-bs-toggle="modal" data-bs-target="#modalAssegnaConsulente"
                                                data-commessa-id="<?= (int) $commessa['id'] ?>"
                                                data-commessa-protocollo="<?= htmlspecialchars($commessa['protocollo'], ENT_QUOTES) ?>"
                                                data-consulente="<?= htmlspecialchars($commessa['consulente_incaricato'] ?? '', ENT_QUOTES) ?>"
                                                title="Assegna consulente"
                                                aria-label="Assegna consulente alla commessa <?= htmlspecialchars($commessa['protocollo'], ENT_QUOTES) ?>">👤</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isConsulente && !$isAdminOrArea): ?>
                <div class="card mb-4">
                    <div class="card-header">Commesse assegnate al tuo account (Consulente)</div>
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
                                <tr><td colspan="6" class="text-center text-muted py-4">Nessuna commessa assegnata.</td></tr>
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

            <?php if (!$isConsulente && !$isAdminOrArea): ?>
                <div class="alert alert-info">Nessun contenuto disponibile per i tuoi ruoli correnti.</div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php if ($isAdminOrArea): ?>
<div class="modal fade" id="modalAssegnaConsulente" tabindex="-1" aria-labelledby="modalAssegnaConsulenteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAssegnaConsulenteLabel">Assegna consulente alla commessa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <p>Commessa: <strong id="protocolloCommessaModal"></strong></p>
                    <input type="hidden" name="azione" value="assegna_consulente">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token_bacheca']) ?>">
                    <input type="hidden" name="commessa_id" id="commessaIdModal">
                    <label class="form-label" for="consulenteIncaricatoModal">Consulente incaricato</label>
                    <select class="form-select" name="consulente_utente_id" id="consulenteIncaricatoModal" required>
                        <option value="">-- Seleziona --</option>
                        <?php foreach ($consulentiDisponibili as $consulenteId => $consulente): ?>
                            <option value="<?= (int)$consulenteId ?>"><?= htmlspecialchars($consulente) ?></option>
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
<div class="modal fade" id="modalNoteOfferta" tabindex="-1" aria-labelledby="modalNoteOffertaLabel" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="modalNoteOffertaLabel">Note offerta</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body"><p id="testoNoteOfferta" class="text-break" style="white-space: pre-wrap"></p></div>
    </div></div>
</div>
<script>
document.getElementById('modalAssegnaConsulente').addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    if (!button) return;
    document.getElementById('commessaIdModal').value = button.dataset.commessaId;
    document.getElementById('protocolloCommessaModal').textContent = button.dataset.commessaProtocollo;
    document.getElementById('consulenteIncaricatoModal').value = '';
});
document.getElementById('modalNoteOfferta').addEventListener('show.bs.modal', (event) => {
    document.getElementById('testoNoteOfferta').textContent = event.relatedTarget?.dataset.note || '';
});
</script>
<?php endif; ?>
<?php renderFooter(); ?>
