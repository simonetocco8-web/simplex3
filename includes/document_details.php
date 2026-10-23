<?php
// $document contains the immutable customer and job-line snapshots.
$companyDoc = json_decode($document['azienda_json'] ?? '{}', true) ?: [];
$linesDoc = json_decode($document['righe_json'] ?? '[]', true) ?: [];
$addressDoc = $companyDoc;
foreach (($companyDoc['sedi'] ?? []) as $siteDoc) {
    $addressDoc = $siteDoc;
    if ($siteDoc['tipo_sede'] === 'Legale') break;
}
?>
<div class="card mb-4"><div class="card-header">Azienda e momenti della commessa</div><div class="card-body">
    <h4 class="h6"><?= htmlspecialchars($companyDoc['ragione_sociale'] ?? '-') ?></h4>
    <p>Partita IVA: <?= htmlspecialchars($companyDoc['partita_iva'] ?? '-') ?> · Codice Fiscale: <?= htmlspecialchars($companyDoc['codice_fiscale'] ?? '-') ?><br>
        <?= htmlspecialchars(trim(($addressDoc['via'] ?? '') . ' ' . ($addressDoc['numero_civico'] ?? '') . ' ' . ($addressDoc['cap'] ?? '') . ' ' . ($addressDoc['comune'] ?? $addressDoc['localita'] ?? '') . ' ' . ($addressDoc['provincia'] ?? ''))) ?><br>
        Email: <?= htmlspecialchars($companyDoc['email'] ?? '-') ?> · PEC: <?= htmlspecialchars($companyDoc['pec'] ?? '-') ?> · Codice fatturazione: <?= htmlspecialchars($companyDoc['codice_fatturazione'] ?? '-') ?>
    </p>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Data</th><th>Momento</th><th>Valore giornaliero</th><th>Ore</th><th>Giorni</th><th>Importo</th></tr></thead><tbody>
    <?php foreach ($linesDoc as $lineDoc): ?>
        <tr><td><?= htmlspecialchars(formatDateIt($lineDoc['data_momento'])) ?></td><td><?= htmlspecialchars($lineDoc['tipologia']) ?></td>
            <td>€ <?= number_format((float)$lineDoc['valore_giornaliero_uomo'],2,',','.') ?></td>
            <td><?= htmlspecialchars((string)$lineDoc['ore']) ?></td><td><?= htmlspecialchars((string)$lineDoc['giorni']) ?></td>
            <td>€ <?= number_format((float)$lineDoc['importo'],2,',','.') ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div></div>
