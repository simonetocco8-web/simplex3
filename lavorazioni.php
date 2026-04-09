<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$offertaId = isset($_GET['offerta_id']) ? (int) $_GET['offerta_id'] : (int) ($_POST['offerta_id'] ?? 0);
if ($offertaId <= 0) {
    header('Location: offerte.php');
    exit;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS offerta_lavorazioni (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        offerta_id INT UNSIGNED NOT NULL,
        tipologia ENUM('apertura lavori', 'chiusura lavori') NOT NULL,
        importo_euro DECIMAL(12,2) NOT NULL,
        data_prevista DATE NOT NULL,
        fatturazione TINYINT(1) NOT NULL DEFAULT 0,
        valore_giorno_uomo DECIMAL(12,2) NOT NULL,
        ore DECIMAL(8,2) NOT NULL,
        giorni DECIMAL(8,2) NOT NULL,
        creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_lavorazione_offerta FOREIGN KEY (offerta_id) REFERENCES offerte(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$stmtOfferta = $pdo->prepare('SELECT id, protocollo, servizio, dettaglio_servizio FROM offerte WHERE id = :id');
$stmtOfferta->execute([':id' => $offertaId]);
$offerta = $stmtOfferta->fetch();
if (!$offerta) {
    header('Location: offerte.php');
    exit;
}

$errors = [];
$success = null;
$lavorazioneInModifica = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if ($editId > 0) {
    $stmtEdit = $pdo->prepare('SELECT * FROM offerta_lavorazioni WHERE id = :id AND offerta_id = :offerta_id');
    $stmtEdit->execute([':id' => $editId, ':offerta_id' => $offertaId]);
    $lavorazioneInModifica = $stmtEdit->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'delete') {
        $idDelete = (int) ($_POST['id'] ?? 0);
        if ($idDelete > 0) {
            $stmtDelete = $pdo->prepare('DELETE FROM offerta_lavorazioni WHERE id = :id AND offerta_id = :offerta_id');
            $stmtDelete->execute([':id' => $idDelete, ':offerta_id' => $offertaId]);
            $success = 'Lavorazione eliminata correttamente.';
        }
    }

    if ($azione === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $tipologia = trim($_POST['tipologia'] ?? '');
        $importoEuro = str_replace(',', '.', trim($_POST['importo_euro'] ?? ''));
        $dataPrevista = trim($_POST['data_prevista'] ?? '');
        $fatturazione = isset($_POST['fatturazione']) ? 1 : 0;
        $valoreGiornoUomo = str_replace(',', '.', trim($_POST['valore_giorno_uomo'] ?? ''));
        $ore = str_replace(',', '.', trim($_POST['ore'] ?? ''));
        $giorni = str_replace(',', '.', trim($_POST['giorni'] ?? ''));

        if (!in_array($tipologia, ['apertura lavori', 'chiusura lavori'], true)) {
            $errors[] = 'Tipologia non valida.';
        }

        if (!is_numeric($importoEuro) || (float) $importoEuro < 0) {
            $errors[] = 'Importo in Euro non valido.';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPrevista)) {
            $errors[] = 'Data prevista non valida.';
        }

        if (!is_numeric($valoreGiornoUomo) || (float) $valoreGiornoUomo < 0) {
            $errors[] = 'Valore Giorno Uomo non valido.';
        }

        if (!is_numeric($ore) || (float) $ore < 0) {
            $errors[] = 'Ore non valide.';
        }

        if (!is_numeric($giorni) || (float) $giorni < 0) {
            $errors[] = 'Giorni non validi.';
        }

        if (!$errors) {
            if ($id > 0) {
                $sql = 'UPDATE offerta_lavorazioni
                        SET tipologia = :tipologia,
                            importo_euro = :importo_euro,
                            data_prevista = :data_prevista,
                            fatturazione = :fatturazione,
                            valore_giorno_uomo = :valore_giorno_uomo,
                            ore = :ore,
                            giorni = :giorni
                        WHERE id = :id AND offerta_id = :offerta_id';
                $params = [
                    ':tipologia' => $tipologia,
                    ':importo_euro' => number_format((float) $importoEuro, 2, '.', ''),
                    ':data_prevista' => $dataPrevista,
                    ':fatturazione' => $fatturazione,
                    ':valore_giorno_uomo' => number_format((float) $valoreGiornoUomo, 2, '.', ''),
                    ':ore' => number_format((float) $ore, 2, '.', ''),
                    ':giorni' => number_format((float) $giorni, 2, '.', ''),
                    ':id' => $id,
                    ':offerta_id' => $offertaId,
                ];
                $pdo->prepare($sql)->execute($params);
                $success = 'Lavorazione aggiornata correttamente.';
            } else {
                $sql = 'INSERT INTO offerta_lavorazioni (
                            offerta_id, tipologia, importo_euro, data_prevista, fatturazione, valore_giorno_uomo, ore, giorni
                        ) VALUES (
                            :offerta_id, :tipologia, :importo_euro, :data_prevista, :fatturazione, :valore_giorno_uomo, :ore, :giorni
                        )';
                $params = [
                    ':offerta_id' => $offertaId,
                    ':tipologia' => $tipologia,
                    ':importo_euro' => number_format((float) $importoEuro, 2, '.', ''),
                    ':data_prevista' => $dataPrevista,
                    ':fatturazione' => $fatturazione,
                    ':valore_giorno_uomo' => number_format((float) $valoreGiornoUomo, 2, '.', ''),
                    ':ore' => number_format((float) $ore, 2, '.', ''),
                    ':giorni' => number_format((float) $giorni, 2, '.', ''),
                ];
                $pdo->prepare($sql)->execute($params);
                $success = 'Lavorazione aggiunta correttamente.';
            }

            $lavorazioneInModifica = null;
            $editId = 0;
        }
    }
}

$stmtList = $pdo->prepare('SELECT * FROM offerta_lavorazioni WHERE offerta_id = :offerta_id ORDER BY data_prevista, id');
$stmtList->execute([':offerta_id' => $offertaId]);
$lavorazioni = $stmtList->fetchAll();

$utenteLoggato = currentUser();
renderHeader('Simplex - Fasi di Lavorazioni');
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2 mb-3">
                <li class="nav-item"><a class="nav-link" href="bacheca.php">Bacheca</a></li>
                <li class="nav-item"><a class="nav-link" href="utenti.php">Utenti</a></li>
                <li class="nav-item"><a class="nav-link" href="aziende.php">Aziende</a></li>
                <li class="nav-item"><a class="nav-link" href="enti_certificazione.php">Enti di Certificazione</a></li>
                <li class="nav-item"><a class="nav-link active" href="offerte.php">Offerte</a></li>
                <li class="nav-item"><a class="nav-link" href="commesse.php">Commesse</a></li>
                <li class="nav-item"><a class="nav-link" href="amministrazione_produzione.php">Amministrazione</a></li>
                <li class="nav-item"><a class="nav-link" href="fatture.php">Fatture</a></li>
                <li class="nav-item"><a class="nav-link" href="pagamenti.php">Pagamenti</a></li>
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
                <div>
                    <h2 class="mb-0">Fasi di Lavorazioni</h2>
                    <p class="text-muted mb-0">Offerta: <strong><?= htmlspecialchars($offerta['protocollo']) ?></strong> - <?= htmlspecialchars($offerta['servizio']) ?></p>
                </div>
                <a class="btn btn-outline-secondary" href="offerte.php">Torna alle Offerte</a>
            </div>

            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

            <div class="card mb-4">
                <div class="card-header"><?= $lavorazioneInModifica ? 'Modifica lavorazione' : 'Nuova lavorazione' ?></div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="azione" value="save">
                        <input type="hidden" name="offerta_id" value="<?= (int)$offertaId ?>">
                        <input type="hidden" name="id" value="<?= (int)($lavorazioneInModifica['id'] ?? 0) ?>">

                        <div class="col-md-3">
                            <label class="form-label">Tipologia</label>
                            <select class="form-select" name="tipologia" required>
                                <?php $tipologiaSel = $lavorazioneInModifica['tipologia'] ?? 'apertura lavori'; ?>
                                <option value="apertura lavori" <?= $tipologiaSel === 'apertura lavori' ? 'selected' : '' ?>>apertura lavori</option>
                                <option value="chiusura lavori" <?= $tipologiaSel === 'chiusura lavori' ? 'selected' : '' ?>>chiusura lavori</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Importo (€)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="importo_euro" required value="<?= htmlspecialchars($lavorazioneInModifica['importo_euro'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Prevista</label>
                            <input type="date" class="form-control" name="data_prevista" required value="<?= htmlspecialchars($lavorazioneInModifica['data_prevista'] ?? '') ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="fatturazione" id="fatturazione" <?= ((int)($lavorazioneInModifica['fatturazione'] ?? 0) === 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="fatturazione">Fatturazione</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Valore Giorno Uomo (€)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="valore_giorno_uomo" required value="<?= htmlspecialchars($lavorazioneInModifica['valore_giorno_uomo'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ore</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="ore" required value="<?= htmlspecialchars($lavorazioneInModifica['ore'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Giorni</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="giorni" required value="<?= htmlspecialchars($lavorazioneInModifica['giorni'] ?? '') ?>">
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary" type="submit"><?= $lavorazioneInModifica ? 'Aggiorna lavorazione' : 'Aggiungi lavorazione' ?></button>
                            <?php if ($lavorazioneInModifica): ?>
                                <a class="btn btn-outline-secondary" href="lavorazioni.php?offerta_id=<?= (int)$offertaId ?>">Annulla</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco lavorazioni dell'offerta</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Tipologia</th>
                            <th>Importo (€)</th>
                            <th>Data Prevista</th>
                            <th>Fatturazione</th>
                            <th>Valore Giorno Uomo (€)</th>
                            <th>Ore</th>
                            <th>Giorni</th>
                            <th>Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$lavorazioni): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nessuna lavorazione presente.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($lavorazioni as $lav): ?>
                            <tr>
                                <td><?= htmlspecialchars($lav['tipologia']) ?></td>
                                <td><?= htmlspecialchars((string)$lav['importo_euro']) ?></td>
                                <td><?= htmlspecialchars($lav['data_prevista']) ?></td>
                                <td><?= ((int)$lav['fatturazione'] === 1) ? 'Sì' : 'No' ?></td>
                                <td><?= htmlspecialchars((string)$lav['valore_giorno_uomo']) ?></td>
                                <td><?= htmlspecialchars((string)$lav['ore']) ?></td>
                                <td><?= htmlspecialchars((string)$lav['giorni']) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="lavorazioni.php?offerta_id=<?= (int)$offertaId ?>&edit=<?= (int)$lav['id'] ?>">Modifica</a>
                                        <form method="post" onsubmit="return confirm('Confermi eliminazione lavorazione?');">
                                            <input type="hidden" name="azione" value="delete">
                                            <input type="hidden" name="offerta_id" value="<?= (int)$offertaId ?>">
                                            <input type="hidden" name="id" value="<?= (int)$lav['id'] ?>">
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
<?php renderFooter(); ?>
