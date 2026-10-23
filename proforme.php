<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/proforma.php';
requireLogin();
ensureProformaSchema($pdo);
ensureInvoiceDocumentColumns($pdo);
if (empty($_SESSION['csrf_proforme'])) $_SESSION['csrf_proforme'] = bin2hex(random_bytes(32));
$errors = [];
$success = $_SESSION['proforma_success'] ?? null;
unset($_SESSION['proforma_success']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_proforme'], (string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); exit('Richiesta non valida.'); }
    $id = (int)($_POST['proforma_id'] ?? 0);
    try {
        if (($_POST['azione'] ?? '') === 'accredito') {
            proformaCredit($pdo, $id, (string)($_POST['data_accredito'] ?? ''), (string)($_POST['importo'] ?? ''), (string)($_POST['request_key'] ?? ''));
            $_SESSION['proforma_success'] = 'Accredito registrato correttamente.';
        } elseif (($_POST['azione'] ?? '') === 'genera_fattura') {
            proformaInvoice($pdo, $id);
            $_SESSION['proforma_success'] = 'Fattura generata e disponibile nella sezione Fatture.';
        } else throw new RuntimeException('Operazione non valida.');
        header('Location: proforme.php?view=' . $id); exit;
    } catch (Throwable $e) { $errors[] = $e instanceof PDOException ? 'Impossibile salvare l’operazione.' : $e->getMessage(); }
}
$stmt = $pdo->query('SELECT pf.*, c.protocollo AS commessa_protocollo, COALESCE(a.accreditato,0) AS accreditato
    FROM proforme pf JOIN commesse c ON c.id=pf.commessa_id
    LEFT JOIN (SELECT proforma_id,SUM(importo) AS accreditato FROM proforma_accrediti GROUP BY proforma_id) a ON a.proforma_id=pf.id
    ORDER BY pf.id DESC');
$proforme = $stmt->fetchAll();
$selected = null;
foreach ($proforme as $row) if ((int)$row['id'] === (int)($_GET['view'] ?? 0)) $selected = $row;
$utenteLoggato = currentUser();
renderHeader('Simplex - Pro-Forma');
?>
<div class="container-fluid"><div class="row">
<?php require __DIR__ . '/includes/billing_nav.php'; ?>
<main class="col-12 col-md-9 col-lg-10 p-4">
    <h2>Pro-Forma</h2>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
    <?php if ($selected): ?>
        <div class="card my-3"><div class="card-header">Pro-Forma <?= htmlspecialchars($selected['numero']) ?></div><div class="card-body">
            <p>Emissione: <?= htmlspecialchars(formatDateIt($selected['data_emissione'])) ?> · Commessa: <?= htmlspecialchars($selected['commessa_protocollo']) ?></p>
            <p>Totale: <strong>€ <?= number_format((float)$selected['importo'],2,',','.') ?></strong> · Accreditato: € <?= number_format((float)$selected['accreditato'],2,',','.') ?> · Residuo: € <?= number_format((consuntivoCents($selected['importo'])-consuntivoCents($selected['accreditato']))/100,2,',','.') ?></p>
            <?php if ($selected['fattura_id']): ?><a href="fatture.php?view=<?= (int)$selected['fattura_id'] ?>">Visualizza fattura generata</a><?php endif; ?>
        </div></div>
        <?php $document = $selected; require __DIR__ . '/includes/document_details.php'; ?>
        <div class="card mb-4"><div class="card-header">Accrediti</div><div class="card-body">
            <p>Ogni accredito riduce il residuo: acconto fino al raggiungimento del totale, saldo al pagamento completo.</p>
            <?php if (proformaState($selected) !== 'saldata'): ?>
                <form method="post" class="row g-2 mb-3">
                    <input type="hidden" name="azione" value="accredito"><input type="hidden" name="proforma_id" value="<?= (int)$selected['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_proforme']) ?>"><input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(16)) ?>">
                    <div class="col-md-4"><label class="form-label" for="data-accredito">Data accredito</label><input type="date" class="form-control" id="data-accredito" name="data_accredito" required value="<?= billingToday() ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="importo-accredito">Importo (€)</label><input type="number" class="form-control" id="importo-accredito" name="importo" min="0.01" step="0.01" max="<?= number_format((consuntivoCents($selected['importo'])-consuntivoCents($selected['accreditato']))/100,2,'.','') ?>" required></div>
                    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary" type="submit">Registra accredito</button></div>
                </form>
            <?php endif; ?>
            <table class="table table-sm"><thead><tr><th>Data</th><th>Importo</th><th>Tipo</th></tr></thead><tbody>
            <?php
                $creditsStmt = $pdo->prepare('SELECT * FROM proforma_accrediti WHERE proforma_id=:id ORDER BY id');
                $creditsStmt->execute(['id' => $selected['id']]); $credits = $creditsStmt->fetchAll(); $running = 0;
                foreach ($credits as $credit): $running += consuntivoCents($credit['importo']);
            ?>
                <tr><td><?= htmlspecialchars(formatDateIt($credit['data_accredito'])) ?></td><td>€ <?= number_format((float)$credit['importo'],2,',','.') ?></td><td><?= $running === consuntivoCents($selected['importo']) ? 'Saldo' : 'Acconto' ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$credits): ?><tr><td colspan="3">Nessun accredito registrato.</td></tr><?php endif; ?>
            </tbody></table>
        </div></div>
    <?php endif; ?>
    <div class="card"><div class="card-header">Elenco Pro-Forma generate</div><div class="table-responsive">
        <table class="table table-hover align-middle mb-0"><thead><tr><th>Numero</th><th>Emissione</th><th>Azienda</th><th>Commessa</th><th>Totale</th><th>Accreditato</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
        <?php foreach ($proforme as $row): $state = proformaState($row); $company = json_decode($row['azienda_json'],true) ?: []; ?>
            <tr data-proforma-id="<?= (int)$row['id'] ?>" class="<?= ['saldata'=>'table-success','acconto'=>'table-warning','scaduta'=>'table-danger'][$state] ?? '' ?>">
                <td><?= htmlspecialchars($row['numero']) ?></td><td><?= htmlspecialchars(formatDateIt($row['data_emissione'])) ?></td><td><?= htmlspecialchars($company['ragione_sociale'] ?? '-') ?></td>
                <td><a href="commesse.php?edit=<?= (int)$row['commessa_id'] ?>"><?= htmlspecialchars($row['commessa_protocollo']) ?></a></td>
                <td>€ <?= number_format((float)$row['importo'],2,',','.') ?></td><td>€ <?= number_format((float)$row['accreditato'],2,',','.') ?></td><td><?= htmlspecialchars(ucfirst($state)) ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="proforme.php?view=<?= (int)$row['id'] ?>">Dettaglio / Accrediti</a>
                    <?php if ($row['fattura_id']): ?><a href="fatture.php?view=<?= (int)$row['fattura_id'] ?>">Fattura</a>
                    <?php elseif ($state === 'scaduta'): ?>
                        <form method="post" class="d-inline"><input type="hidden" name="azione" value="genera_fattura"><input type="hidden" name="proforma_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_proforme']) ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Genera Fattura" aria-label="Genera Fattura" type="submit"><?= menuIcon('Genera Fattura') ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$proforme): ?><tr><td colspan="8">Nessuna Pro-Forma generata.</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>
</main></div></div>
<?php renderFooter(); ?>
