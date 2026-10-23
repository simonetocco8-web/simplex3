<?php
require_once __DIR__ . '/consuntivo.php';

function billingToday(): string
{
    return (new DateTimeImmutable('today', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
}

function ensureProformaSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS proforme (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        commessa_id INT UNSIGNED NOT NULL UNIQUE,
        numero VARCHAR(30) NOT NULL UNIQUE,
        data_emissione DATE NOT NULL,
        importo DECIMAL(12,2) NOT NULL,
        azienda_json LONGTEXT NOT NULL,
        righe_json LONGTEXT NOT NULL,
        fattura_id INT UNSIGNED NULL UNIQUE,
        CONSTRAINT fk_proforma_commessa FOREIGN KEY (commessa_id) REFERENCES commesse(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS proforma_accrediti (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        proforma_id INT UNSIGNED NOT NULL,
        data_accredito DATE NOT NULL,
        importo DECIMAL(12,2) NOT NULL,
        request_key VARCHAR(64) NOT NULL UNIQUE,
        CONSTRAINT fk_accredito_proforma FOREIGN KEY (proforma_id) REFERENCES proforme(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$pdo->query("SHOW COLUMNS FROM commesse LIKE 'stato'")->fetch()) {
        $pdo->exec("ALTER TABLE commesse ADD COLUMN stato VARCHAR(20) NOT NULL DEFAULT 'In corso'");
    }
}

function ensureInvoiceDocumentColumns(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM fatture LIKE 'momento_id'")->fetch();
    if ($column && $column['Null'] === 'NO') $pdo->exec('ALTER TABLE fatture MODIFY COLUMN momento_id INT UNSIGNED NULL');
    foreach (['proforma_id' => 'INT UNSIGNED NULL UNIQUE', 'azienda_json' => 'LONGTEXT NULL', 'righe_json' => 'LONGTEXT NULL'] as $name => $type) {
        if (!$pdo->query("SHOW COLUMNS FROM fatture LIKE '$name'")->fetch()) $pdo->exec("ALTER TABLE fatture ADD COLUMN $name $type");
    }
}

// Completing all the moments or the whole job emits the same unique document.
function completeJobProforma(PDO $pdo, int $jobId, ?int $momentId = null): ?int
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM commesse WHERE id=:id FOR UPDATE');
        $stmt->execute(['id' => $jobId]);
        $job = $stmt->fetch();
        if (!$job) throw new RuntimeException('Commessa non trovata.');
        $stmt = $pdo->prepare('SELECT id FROM proforme WHERE commessa_id=:id');
        $stmt->execute(['id' => $jobId]);
        if ($existing = $stmt->fetchColumn()) { $pdo->commit(); return (int)$existing; }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM fatture WHERE commessa_id=:id');
        $stmt->execute(['id' => $jobId]);
        if ((int)$stmt->fetchColumn() > 0) throw new RuntimeException('La commessa ha già fatture emesse: nessuna nuova Pro-Forma generata.');
        $stmt = $pdo->prepare('SELECT * FROM commessa_momenti_lavorazione WHERE commessa_id=:id ORDER BY data_momento,id FOR UPDATE');
        $stmt->execute(['id' => $jobId]);
        $lines = $stmt->fetchAll();
        if (!$lines) throw new RuntimeException('La commessa non contiene momenti da riepilogare.');
        if ($momentId !== null && !in_array($momentId, array_column($lines, 'id'))) throw new RuntimeException('Momento non trovato.');
        $sql = 'UPDATE commessa_momenti_lavorazione SET completato=1, completato_il=COALESCE(completato_il,NOW()) WHERE commessa_id=:id';
        $params = ['id' => $jobId];
        if ($momentId !== null) { $sql .= ' AND id=:momento'; $params['momento'] = $momentId; }
        $pdo->prepare($sql)->execute($params);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM commessa_momenti_lavorazione WHERE commessa_id=:id AND completato=0');
        $stmt->execute(['id' => $jobId]);
        if ((int)$stmt->fetchColumn() > 0) { $pdo->commit(); return null; }
        $stmt = $pdo->prepare('SELECT a.* FROM commesse c JOIN offerte o ON o.id=c.offerta_id JOIN aziende a ON a.id=COALESCE(c.azienda_cliente_id,o.azienda_id) WHERE c.id=:id');
        $stmt->execute(['id' => $jobId]);
        $company = $stmt->fetch();
        if (!$company) throw new RuntimeException('Azienda della commessa non disponibile.');
        $stmt = $pdo->prepare('SELECT * FROM aziende_sedi WHERE azienda_id=:id ORDER BY id');
        $stmt->execute(['id' => $company['id']]);
        $company['sedi'] = $stmt->fetchAll();
        $total = 0;
        foreach ($lines as &$line) {
            $amount = consuntivoCents($line['costo_totale']);
            if ($amount <= 0) {
                $days = (float)$line['giorni'] > 0 ? (float)$line['giorni'] : ((float)$line['ore'] / 8);
                $amount = (int)round((float)$line['valore_giornaliero_uomo'] * $days * 100);
            }
            $line['importo'] = number_format(max(0, $amount) / 100, 2, '.', '');
            $total += max(0, $amount);
        }
        unset($line);
        $today = billingToday();
        $stmt = $pdo->prepare('INSERT INTO proforme (commessa_id,numero,data_emissione,importo,azienda_json,righe_json) VALUES (:id,:numero,:data,:importo,:azienda,:righe)');
        $stmt->execute(['id' => $jobId, 'numero' => 'PF-' . $jobId . '/' . substr($today,0,4), 'data' => $today,
            'importo' => number_format($total / 100, 2, '.', ''), 'azienda' => json_encode($company, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'righe' => json_encode($lines, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE commesse SET stato='Completato' WHERE id=:id")->execute(['id' => $jobId]);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function proformaState(array $row): string
{
    $total = consuntivoCents($row['importo']);
    $paid = consuntivoCents($row['accreditato'] ?? '0');
    if ($paid === $total) return 'saldata';
    if ($paid > 0) return 'acconto';
    $limit = (new DateTimeImmutable(billingToday()))->modify('-30 days')->format('Y-m-d');
    return $row['data_emissione'] <= $limit ? 'scaduta' : 'da pagare';
}

function proformaCredit(PDO $pdo, int $id, string $date, string $amount, string $key): void
{
    $amount = consuntivoDecimal($amount, 10);
    if (!consuntivoDate($date) || $amount === null || consuntivoCents($amount) <= 0 || !preg_match('/^[a-f0-9]{32}$/', $key)) throw new RuntimeException('Data o importo accredito non validi.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM proforme WHERE id=:id FOR UPDATE');
        $stmt->execute(['id' => $id]); $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('Pro-Forma non trovata.');
        $stmt = $pdo->prepare('SELECT proforma_id FROM proforma_accrediti WHERE request_key=:key');
        $stmt->execute(['key' => $key]);
        if ($previous = $stmt->fetchColumn()) {
            if ((int)$previous !== $id) throw new RuntimeException('Richiesta accredito già utilizzata.');
            $pdo->commit(); return;
        }
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(importo),0) FROM proforma_accrediti WHERE proforma_id=:id');
        $stmt->execute(['id' => $id]);
        $paid = consuntivoCents($stmt->fetchColumn()) + consuntivoCents($amount);
        if ($paid > consuntivoCents($row['importo'])) throw new RuntimeException('L’accredito supera il residuo della Pro-Forma.');
        $pdo->prepare('INSERT INTO proforma_accrediti (proforma_id,data_accredito,importo,request_key) VALUES (:id,:data,:importo,:key)')->execute(['id' => $id,'data' => $date,'importo' => $amount,'key' => $key]);
        if ($row['fattura_id']) $pdo->prepare('UPDATE fatture SET pagata=:pagata WHERE id=:id')->execute(['pagata' => $paid === consuntivoCents($row['importo']) ? 1 : 0,'id' => $row['fattura_id']]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function proformaInvoice(PDO $pdo, int $id): int
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM proforme WHERE id=:id FOR UPDATE');
        $stmt->execute(['id' => $id]); $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('Pro-Forma non trovata.');
        if ($row['fattura_id']) { $pdo->commit(); return (int)$row['fattura_id']; }
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(importo),0) FROM proforma_accrediti WHERE proforma_id=:id');
        $stmt->execute(['id' => $id]); $row['accreditato'] = $stmt->fetchColumn();
        if (proformaState($row) !== 'scaduta') throw new RuntimeException('La fattura è generabile solo dopo 30 giorni senza accrediti.');
        $today = billingToday();
        $stmt = $pdo->prepare('INSERT INTO fatture (commessa_id,momento_id,numero,anno_riferimento,importo,proforma_id,azienda_json,righe_json) VALUES (:commessa,NULL,:numero,:anno,:importo,:proforma,:azienda,:righe)');
        $stmt->execute(['commessa' => $row['commessa_id'], 'numero' => 'F-PF-' . $id . '/' . substr($today,0,4), 'anno' => substr($today,0,4),
            'importo' => $row['importo'], 'proforma' => $id, 'azienda' => $row['azienda_json'], 'righe' => $row['righe_json']]);
        $invoiceId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE proforme SET fattura_id=:fattura WHERE id=:id')->execute(['fattura' => $invoiceId, 'id' => $id]);
        $pdo->commit(); return $invoiceId;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
