<?php
$numericFields = [
    'valore_giornaliero_uomo' => 'Valore Giornaliero Uomo (€)',
    'costo_totale' => 'Costo Totale (€)',
    'ore' => 'Ore', 'giorni' => 'Giorni', 'numero_incontri' => 'Incontri',
];
$plannedTotal = 0;
$actualTotal = 0;
?>
<section class="card mb-4" aria-labelledby="consuntivo-title" id="momenti-consuntivo">
    <h3 class="card-header h6" id="consuntivo-title">Momenti a Consuntivo</h3>
    <div class="card-body">
        <p class="text-muted">Valori iniziali ereditati dai Momenti di Lavorazione. Modifica e salva i valori effettivi.
            Differenza = preventivo − consuntivo: un risparmio è verde, uno sforamento è rosso.
            Il Costo Totale è modificabile direttamente; il pulsante di calcolo usa tariffa × (giorni + ore / 8).</p>
        <?php if (!$momentiCommessa): ?>
            <p class="mb-0">Nessun momento di lavorazione da consuntivare.</p>
        <?php else: ?>
        <form method="post" id="form-consuntivi" action="commesse.php?edit=<?= (int)$commessaInModifica['id'] ?>#momenti-consuntivo">
            <input type="hidden" name="azione" value="save_consuntivi">
            <input type="hidden" name="id" value="<?= (int)$commessaInModifica['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_consuntivo']) ?>">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead><tr><th>Data / Tipologia</th>
                        <?php foreach ($numericFields as $label): ?><th><?= htmlspecialchars($label) ?></th><?php endforeach; ?>
                        <th>Ore di studio / Data prevista</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($momentiCommessa as $planned):
                        $momentId = (int)$planned['id'];
                        $actual = $consuntivi[$momentId];
                        if (isset($consuntiviInput[$momentId]) && is_array($consuntiviInput[$momentId])) {
                            foreach (consuntivoFields() as $field) {
                                if (isset($consuntiviInput[$momentId][$field]) && is_scalar($consuntiviInput[$momentId][$field])) {
                                    $actual[$field] = (string)$consuntiviInput[$momentId][$field];
                                }
                            }
                        }
                        $plannedTotal += consuntivoCents($planned['costo_totale']);
                        $actualTotal += consuntivoCents(str_replace(',', '.', $actual['costo_totale']));
                    ?>
                    <tr data-consuntivo-id="<?= $momentId ?>">
                        <td style="min-width: 200px">
                            <div class="small text-muted">Preventivo: <?= htmlspecialchars(formatDateIt($planned['data_momento'])) ?> · <?= htmlspecialchars($planned['tipologia']) ?></div>
                            <label class="small" for="cons-data-<?= $momentId ?>">Data a consuntivo</label>
                            <input type="date" class="form-control form-control-sm" id="cons-data-<?= $momentId ?>" name="consuntivi[<?= $momentId ?>][data_momento]" required value="<?= htmlspecialchars($actual['data_momento']) ?>">
                            <label class="small" for="cons-tipo-<?= $momentId ?>">Tipologia a consuntivo</label>
                            <input class="form-control form-control-sm" id="cons-tipo-<?= $momentId ?>" name="consuntivi[<?= $momentId ?>][tipologia]" maxlength="120" required value="<?= htmlspecialchars($actual['tipologia']) ?>">
                        </td>
                        <?php foreach ($numericFields as $field => $label):
                            $delta = consuntivoCents($planned[$field]) - consuntivoCents(str_replace(',', '.', $actual[$field]));
                        ?>
                        <td style="min-width: 150px">
                            <div class="small text-muted">Preventivo: <?= htmlspecialchars((string)$planned[$field]) ?></div>
                            <input type="number" class="form-control form-control-sm" data-field="<?= $field ?>"
                                   name="consuntivi[<?= $momentId ?>][<?= $field ?>]"
                                   aria-label="<?= htmlspecialchars($label) ?> a consuntivo, momento <?= $momentId ?>"
                                   min="0" max="<?= $field === 'numero_incontri' ? '4294967295' : (in_array($field, ['ore', 'giorni'], true) ? '999999.99' : '9999999999.99') ?>"
                                   step="<?= $field === 'numero_incontri' ? '1' : '0.01' ?>" required
                                   value="<?= htmlspecialchars(str_replace(',', '.', (string)$actual[$field])) ?>">
                            <div class="small">Δ <span data-delta="<?= $field ?>" data-planned="<?= htmlspecialchars((string)$planned[$field]) ?>" class="<?= consuntivoDeltaClass($delta) ?>"><?= consuntivoFormat($delta) ?></span></div>
                            <?php if ($field === 'costo_totale'): ?><button type="button" class="btn btn-sm btn-outline-secondary mt-1" data-calculate-cost>Calcola costo</button><?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td style="min-width: 170px">
                            <div class="small text-muted">Preventivo: <?= htmlspecialchars($planned['ore_studio'] ?? '-') ?> · <?= htmlspecialchars(formatDateIt($planned['data_prevista'])) ?></div>
                            <label class="small" for="cons-studio-<?= $momentId ?>">Ore di studio</label>
                            <input class="form-control form-control-sm" id="cons-studio-<?= $momentId ?>" name="consuntivi[<?= $momentId ?>][ore_studio]" maxlength="5" placeholder="Ore o HH:MM" value="<?= htmlspecialchars($actual['ore_studio'] ?? '') ?>">
                            <label class="small" for="cons-prevista-<?= $momentId ?>">Data prevista</label>
                            <input type="date" class="form-control form-control-sm" id="cons-prevista-<?= $momentId ?>" name="consuntivi[<?= $momentId ?>][data_prevista]" value="<?= htmlspecialchars($actual['data_prevista'] ?? '') ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php $totalDelta = $plannedTotal - $actualTotal; ?>
            <div class="d-flex flex-wrap gap-4 my-3" aria-live="polite">
                <div>Totale preventivo: <strong id="consuntivo-preventivo" data-cents="<?= $plannedTotal ?>">€ <?= number_format($plannedTotal / 100, 2, ',', '.') ?></strong></div>
                <div>Totale consuntivo: <strong id="consuntivo-totale">€ <?= number_format($actualTotal / 100, 2, ',', '.') ?></strong></div>
                <div>Differenza totale: <strong id="consuntivo-differenza" class="<?= consuntivoDeltaClass($totalDelta) ?>">€ <?= consuntivoFormat($totalDelta) ?></strong></div>
            </div>
            <button class="btn btn-primary" type="submit">Salva Momenti a Consuntivo</button>
        </form>
        <?php endif; ?>
    </div>
</section>
<script>
(() => {
    const form = document.getElementById('form-consuntivi');
    if (!form) return;
    const rows = form.querySelectorAll('[data-consuntivo-id]');
    const format = value => new Intl.NumberFormat('it-IT', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(value);
    const cents = value => Math.round(Number(value || 0) * 100);
    const paint = (element, delta, euro = false) => {
        element.textContent = (euro ? '€ ' : '') + (delta > 0 ? '+' : '') + format(delta / 100);
        element.className = delta > 0 ? 'text-success' : (delta < 0 ? 'text-danger' : 'text-body-secondary');
    };
    function update() {
        let actualTotal = 0;
        rows.forEach(row => {
            row.querySelectorAll('[data-delta]').forEach(output => {
                const input = row.querySelector(`[data-field="${output.dataset.delta}"]`);
                paint(output, cents(output.dataset.planned) - cents(input.value));
            });
            actualTotal += cents(row.querySelector('[data-field="costo_totale"]').value);
        });
        document.getElementById('consuntivo-totale').textContent = '€ ' + format(actualTotal / 100);
        paint(document.getElementById('consuntivo-differenza'), Number(document.getElementById('consuntivo-preventivo').dataset.cents) - actualTotal, true);
    }
    form.addEventListener('input', update);
    form.addEventListener('click', event => {
        const button = event.target.closest('[data-calculate-cost]');
        if (!button) return;
        const row = button.closest('[data-consuntivo-id]');
        const get = field => Number(row.querySelector(`[data-field="${field}"]`).value || 0);
        row.querySelector('[data-field="costo_totale"]').value = (Math.round(get('valore_giornaliero_uomo') * (get('giorni') + get('ore') / 8) * 100) / 100).toFixed(2);
        update();
    });
    update();
})();
</script>
