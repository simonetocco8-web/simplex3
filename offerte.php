<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

$SERVIZI = [
    'SISTEMI DI GESTIONE AZIENDALE', 'SICUREZZA', 'FORMAZIONE', 'FINANZA AGEVOLATA', 'CONSULENZA SOA', 'ALTRE CONSULENZE',
];
$STATI_OFFERTA = ['In Lavorazione', 'Inviata', 'Aggiudicata', 'Scaduta'];

$DETTAGLI_SERVIZIO = [
    'SISTEMI DI GESTIONE AZIENDALE' => ['ISO 9001','MANT ISO 9001','ISO 14001','MANT ISO 14001','EMAS','MANTENIMENTO EMAS','ISO 45001','MANT ISO 45001','SA 8000','MANT SA8000','ISO 50000','MANT ISO 50000','ISO 27001','MANT ISO 27001','ISO 27017','MANT ISO 27017','ISO 27018','MANT ISO 27018','ISO 42000','MANT ISO 42000','ISO 37001','MANT ISO 37001','ISO 39001','MANT 39001','ISO 22000','MANT ISO 22000','ISO 22005','MANT ISO 22005','ISO 1090','MANT ISO 1090','SISTEMA INTEGRATO','MANTENIMENTO SISTEMA INTEGRATO','HALAL','MANTENIMENTO HALAL','GLOBAL GAP','MANTENIMENTO GLOBAL GAP','BIOLOGICO','MANTENIMENTO BIOLOGICO','MARC CE','FPC CLS','MANTENIMENTO FPC CLS','MANTENIMENTO MAR CE','BRC','MANTENIMENTO BRC AEO','Parità di genere','MANTENIMENTO Parità di genere','MODELLO 231','ODV 231','PRIVACY (GDPR)','HACCP','MANT HACCP','ANALISI TAMPONE HACCP'],
    'SICUREZZA' => ['DVR (D. Lgs. 81/2008)', 'AGGIORNAMENTO DVR', 'MISURAZIONI TECNICHE', 'VISITE MEDICHE'],
    'FORMAZIONE' => ['FORMAZIONE D. Lgs. 81/2008', 'Formazione Profili ENEL', 'ALTRE ATTIVITA’ DI FORMAZIONE'],
    'FINANZA AGEVOLATA' => ['REGIONE CALABRIA', 'INVITALIA', 'MINISTERO', 'ALTRO'],
    'CONSULENZA SOA' => ['NUOVA ATTESTAZIONE', 'VERIFICA TRIENNALE', 'VERIFICA QUINQUENNALE', 'VARIAZIONE', 'ALTRO'],
    'ALTRE CONSULENZE' => ['MARKETING', 'PIANI STRATEGICI', 'CONTROLLO DI GESTIONE', 'ALTRO'],
];
$MODALITA_PAGAMENTO = ['30-60 FMDF','Bonifico Bancario f.m.v.f','da definire','RI.BA.','RID','rid 06 mensilità','rimessa diretta','Rimessa Diretta'];
$TIPOLOGIE_AZIENDA = ['Potenziale Cliente', 'Promotore', 'Fornitore', 'Partner'];

function creaAziendaRapida(PDO $pdo, array $input): array
{
    $partitaIva = preg_replace('/\D/', '', trim((string) ($input['partita_iva'] ?? '')));
    $ragioneSociale = trim((string) ($input['ragione_sociale'] ?? ''));
    $tipologie = $input['tipologia_azienda'] ?? [];
    if (!is_array($tipologie)) {
        $tipologie = [];
    }
    $tipologieValide = array_values(array_intersect($tipologie, ['Potenziale Cliente', 'Promotore', 'Fornitore', 'Partner']));
    $organicoMedio = trim((string) ($input['organico_medio'] ?? ''));
    $fatturatoRaw = str_replace(',', '.', trim((string) ($input['fatturato'] ?? '')));

    $errors = [];
    if (!preg_match('/^\d{11}$/', $partitaIva)) $errors[] = 'Partita IVA non valida (11 cifre).';
    if ($ragioneSociale === '') $errors[] = 'Ragione sociale obbligatoria.';
    if (!$tipologieValide) $errors[] = 'Seleziona almeno una tipologia azienda.';
    if ($organicoMedio === '') $errors[] = 'Organico medio obbligatorio.';
    if ($fatturatoRaw === '' || !is_numeric($fatturatoRaw) || (float)$fatturatoRaw < 0) $errors[] = 'Fatturato non valido.';
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $stmtExists = $pdo->prepare('SELECT id FROM aziende WHERE partita_iva = :partita_iva LIMIT 1');
    $stmtExists->execute([':partita_iva' => $partitaIva]);
    if ($stmtExists->fetchColumn()) {
        return ['ok' => false, 'errors' => ['Esiste già un\'azienda con questa Partita IVA.']];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO aziende (partita_iva, ragione_sociale, tipologia_azienda, organico_medio, fatturato, codice_fiscale, iban, codice_fatturazione, telefono, pec, email, url_sito, via, numero_civico, cap, localita, provincia, categoria, ea, conosciuto_come, principali_prodotti, note)
         VALUES (:partita_iva, :ragione_sociale, :tipologia_azienda, :organico_medio, :fatturato, :codice_fiscale, :iban, :codice_fatturazione, :telefono, :pec, :email, :url_sito, :via, :numero_civico, :cap, :localita, :provincia, :categoria, :ea, :conosciuto_come, :principali_prodotti, :note)'
    );
    $stmt->execute([
        ':partita_iva' => $partitaIva,
        ':ragione_sociale' => mb_substr($ragioneSociale, 0, 30),
        ':tipologia_azienda' => implode(',', $tipologieValide),
        ':organico_medio' => mb_substr($organicoMedio, 0, 30),
        ':fatturato' => number_format((float)$fatturatoRaw, 2, '.', ''),
        ':codice_fiscale' => trim((string) ($input['codice_fiscale'] ?? '')) ?: null,
        ':iban' => trim((string) ($input['iban'] ?? '')) ?: null,
        ':codice_fatturazione' => trim((string) ($input['codice_fatturazione'] ?? '')) ?: null,
        ':telefono' => trim((string) ($input['telefono'] ?? '')) ?: null,
        ':pec' => trim((string) ($input['pec'] ?? '')) ?: null,
        ':email' => trim((string) ($input['email'] ?? '')) ?: null,
        ':url_sito' => trim((string) ($input['url_sito'] ?? '')) ?: null,
        ':via' => trim((string) ($input['via'] ?? '')) ?: null,
        ':numero_civico' => trim((string) ($input['numero_civico'] ?? '')) ?: null,
        ':cap' => trim((string) ($input['cap'] ?? '')) ?: null,
        ':localita' => trim((string) ($input['localita'] ?? '')) ?: null,
        ':provincia' => trim((string) ($input['provincia'] ?? '')) ?: null,
        ':categoria' => trim((string) ($input['categoria'] ?? '')) ?: null,
        ':ea' => trim((string) ($input['ea'] ?? '')) ?: null,
        ':conosciuto_come' => trim((string) ($input['conosciuto_come'] ?? '')) ?: null,
        ':principali_prodotti' => trim((string) ($input['principali_prodotti'] ?? '')) ?: null,
        ':note' => trim((string) ($input['note'] ?? '')) ?: null,
    ]);
    return ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'ragione_sociale' => mb_substr($ragioneSociale, 0, 30)];
}

function ensureCommessa(PDO $pdo, int $offertaId, string $consulente): ?string
{
    $check = $pdo->prepare('SELECT id, protocollo FROM commesse WHERE offerta_id = :offerta_id LIMIT 1');
    $check->execute([':offerta_id' => $offertaId]);
    $existing = $check->fetch();

    if ($existing) {
        $upd = $pdo->prepare('UPDATE commesse SET consulente_codice = :codice, consulente_nome = :nome WHERE id = :id');
        $upd->execute([
            ':codice' => substr($consulente, 0, 2),
            ':nome' => $consulente,
            ':id' => $existing['id'],
        ]);
        return null;
    }

    $anno = (int) date('Y');
    $stmtNum = $pdo->prepare('SELECT COALESCE(MAX(protocollo_numero), 0) + 1 FROM commesse WHERE anno_riferimento = :anno');
    $stmtNum->execute([':anno' => $anno]);
    $numero = (int) $stmtNum->fetchColumn();
    $protocollo = $numero . '/' . $anno;

    $ins = $pdo->prepare('INSERT INTO commesse (offerta_id, protocollo_numero, anno_riferimento, protocollo, consulente_codice, consulente_nome)
                          VALUES (:offerta_id, :numero, :anno, :protocollo, :codice, :nome)');
    $ins->execute([
        ':offerta_id' => $offertaId,
        ':numero' => $numero,
        ':anno' => $anno,
        ':protocollo' => $protocollo,
        ':codice' => substr($consulente, 0, 2),
        ':nome' => $consulente,
    ]);

    $commessaId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO commessa_momenti_lavorazione (commessa_id, data_momento, tipologia, valore_giornaliero_uomo, ore, giorni, numero_incontri, ore_studio, data_prevista)
         SELECT :commessa_id, om.data_momento, om.tipologia, om.valore_giornaliero_uomo, om.ore, om.giorni, om.numero_incontri, om.ore_studio, om.data_prevista
         FROM offerta_momenti_lavorazione om
         WHERE om.offerta_id = :offerta_id'
    )->execute([
        ':commessa_id' => $commessaId,
        ':offerta_id' => $offertaId,
    ]);

    return $protocollo;
}

$errors = [];
$success = null;
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS offerte (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    protocollo_numero INT UNSIGNED NOT NULL,
    anno_riferimento YEAR NOT NULL,
    protocollo VARCHAR(20) NOT NULL UNIQUE,
    servizio VARCHAR(80) NOT NULL,
    tipo_dettaglio VARCHAR(30) NOT NULL,
    dettaglio_servizio VARCHAR(120) NOT NULL,
    stato VARCHAR(20) NOT NULL DEFAULT 'In Lavorazione',
    specifiche_oggetto TEXT NULL,
    sede_erogazione_servizio VARCHAR(255) NULL,
    azienda_id INT UNSIGNED NULL,
    rco_utente_id INT UNSIGNED NULL,
    segnalato_da_utente_id INT UNSIGNED NULL,
    data_offerta DATE NULL,
    validita_giorni INT UNSIGNED NULL,
    data_scadenza DATE NULL,
    note TEXT NULL,
    promotore_azienda_id INT UNSIGNED NULL,
    commissione_tipo VARCHAR(20) NULL,
    commissione_valore DECIMAL(10,2) NULL,
    modalita_pagamento VARCHAR(80) NULL,
    sconto_percentuale DECIMAL(5,2) NULL,
    consulente_incaricato VARCHAR(100) NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_protocollo_anno (protocollo_numero, anno_riferimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS commesse (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offerta_id INT UNSIGNED NOT NULL UNIQUE,
    protocollo_numero INT UNSIGNED NOT NULL,
    anno_riferimento YEAR NOT NULL,
    protocollo VARCHAR(20) NOT NULL UNIQUE,
    consulente_codice VARCHAR(2) NOT NULL,
    consulente_nome VARCHAR(100) NOT NULL,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_commesse_num_anno (protocollo_numero, anno_riferimento),
    CONSTRAINT fk_commessa_offerta FOREIGN KEY (offerta_id) REFERENCES offerte(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS commessa_momenti_lavorazione (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commessa_id INT UNSIGNED NOT NULL,
    data_momento DATE NOT NULL,
    tipologia ENUM('Apertura', 'Chiusura') NOT NULL,
    valore_giornaliero_uomo DECIMAL(12,2) NOT NULL,
    ore DECIMAL(8,2) NOT NULL DEFAULT 0,
    giorni DECIMAL(8,2) NOT NULL DEFAULT 0,
    numero_incontri INT UNSIGNED NOT NULL DEFAULT 0,
    ore_studio VARCHAR(5) NULL,
    data_prevista DATE NULL,
    completato TINYINT(1) NOT NULL DEFAULT 0,
    completato_il DATETIME NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS offerta_momenti_lavorazione (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offerta_id INT UNSIGNED NOT NULL,
    data_momento DATE NOT NULL,
    tipologia ENUM('Apertura', 'Chiusura') NOT NULL,
    valore_giornaliero_uomo DECIMAL(12,2) NOT NULL,
    ore DECIMAL(8,2) NOT NULL DEFAULT 0,
    giorni DECIMAL(8,2) NOT NULL DEFAULT 0,
    numero_incontri INT UNSIGNED NOT NULL DEFAULT 0,
    ore_studio VARCHAR(5) NULL,
    data_prevista DATE NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_offerta_momento_offerta FOREIGN KEY (offerta_id) REFERENCES offerte(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$migrations=[
    'stato'=>"ALTER TABLE offerte ADD COLUMN stato VARCHAR(20) NOT NULL DEFAULT 'In Lavorazione' AFTER dettaglio_servizio",
    'consulente_incaricato'=>"ALTER TABLE offerte ADD COLUMN consulente_incaricato VARCHAR(100) NULL AFTER sconto_percentuale",
    'azienda_id'=>"ALTER TABLE offerte ADD COLUMN azienda_id INT UNSIGNED NULL AFTER sede_erogazione_servizio",
];
foreach($migrations as $col=>$sql){ if(!(bool)$pdo->query("SHOW COLUMNS FROM offerte LIKE '{$col}'")->fetch()){ $pdo->exec($sql);} }
$pdo->exec("ALTER TABLE offerte MODIFY COLUMN stato VARCHAR(20) NOT NULL DEFAULT 'In Lavorazione'");
$pdo->exec("UPDATE offerte SET stato = 'In Lavorazione' WHERE stato = 'Generata'");

$utenti = $pdo->query('SELECT id, nome, cognome FROM utenti ORDER BY nome, cognome')->fetchAll();
$CONSULENTI = [];
if ((bool)$pdo->query("SHOW TABLES LIKE 'utenti_ruoli'")->fetchColumn()) {
    $consRows = $pdo->query("SELECT DISTINCT u.nome, u.cognome
                             FROM utenti u
                             INNER JOIN utenti_ruoli ur ON ur.utente_id = u.id
                             WHERE ur.ruolo = 'Consulente' AND u.attivo = 1
                             ORDER BY u.nome, u.cognome")->fetchAll();
} else {
    $consRows = $pdo->query("SELECT nome, cognome FROM utenti WHERE ruolo = 'Consulente' AND attivo = 1 ORDER BY nome, cognome")->fetchAll();
}
foreach ($consRows as $r) {
    $nomeCons = trim(($r['nome'] ?? '') . ' ' . ($r['cognome'] ?? ''));
    if ($nomeCons !== '') {
        $CONSULENTI[] = $nomeCons;
    }
}

$aziendePromotori = [];
$aziendeTutte = [];
if ((bool)$pdo->query("SHOW TABLES LIKE 'aziende'")->fetchColumn()) {
    $aziendePromotori = $pdo->query("SELECT id, ragione_sociale FROM aziende WHERE FIND_IN_SET('Promotore', tipologia_azienda) > 0 ORDER BY ragione_sociale")->fetchAll();
    $aziendeTutte = $pdo->query("SELECT id, ragione_sociale FROM aziende ORDER BY ragione_sociale")->fetchAll();
}

$action=$_GET['action']??'list'; $viewId=(int)($_GET['view']??0); $editId=(int)($_GET['edit']??0);
$offertaInModifica=null; $offertaInVisualizzazione=null;
if($viewId>0){$st=$pdo->prepare('SELECT o.*, c.protocollo AS commessa_protocollo, c.consulente_nome AS commessa_consulente FROM offerte o LEFT JOIN commesse c ON c.offerta_id=o.id WHERE o.id=:id');$st->execute([':id'=>$viewId]);$offertaInVisualizzazione=$st->fetch();}
if($editId>0){$st=$pdo->prepare('SELECT * FROM offerte WHERE id=:id');$st->execute([':id'=>$editId]);$offertaInModifica=$st->fetch();}
$momentiOfferta = [];
if ($editId > 0) {
    $stMom = $pdo->prepare('SELECT * FROM offerta_momenti_lavorazione WHERE offerta_id = :offerta_id ORDER BY id ASC');
    $stMom->execute([':offerta_id' => $editId]);
    $momentiOfferta = $stMom->fetchAll();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $azione=$_POST['azione']??'';
    if ($azione === 'ajax_create_azienda') {
        header('Content-Type: application/json; charset=utf-8');
        if (!(bool)$pdo->query("SHOW TABLES LIKE 'aziende'")->fetchColumn()) {
            echo json_encode(['ok' => false, 'errors' => ['Tabella aziende non disponibile.']]);
            exit;
        }
        $result = creaAziendaRapida($pdo, $_POST);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if($azione==='delete'){ $id=(int)($_POST['id']??0); if($id>0){$pdo->prepare('DELETE FROM offerte WHERE id=:id')->execute([':id'=>$id]);$success='Offerta eliminata correttamente.';}}
    if($azione==='save'){
        $id=(int)($_POST['id']??0);
        $servizio=trim($_POST['servizio']??''); $dettaglioServizio=trim($_POST['dettaglio_servizio']??'');
        $stato=trim($_POST['stato']??'In Lavorazione'); $consulenteIncaricato=trim($_POST['consulente_incaricato']??'');
        $specificheOggetto=trim($_POST['specifiche_oggetto']??''); $sedeErogazione=trim($_POST['sede_erogazione_servizio']??'');
        $aziendaId = ($_POST['azienda_id'] ?? '') !== '' ? (int) $_POST['azienda_id'] : null;
        $rcoUtenteId=(int)($_POST['rco_utente_id']??0); $segnalatoDaUtenteId=($_POST['segnalato_da_utente_id']??'')!==''?(int)$_POST['segnalato_da_utente_id']:null;
        $dataOfferta=trim($_POST['data_offerta']??''); $validitaGiorniInput=trim($_POST['validita_giorni']??''); $dataScadenza=trim($_POST['data_scadenza']??'');
        $note=trim($_POST['note']??''); $promotoreAziendaId=($_POST['promotore_azienda_id']??'')!==''?(int)$_POST['promotore_azienda_id']:null;
        $commissioneTipo=trim($_POST['commissione_tipo']??''); $commissioneValoreInput=trim($_POST['commissione_valore']??'');
        $modalitaPagamento=trim($_POST['modalita_pagamento']??''); $scontoInput=trim($_POST['sconto_percentuale']??'');

        if(!in_array($servizio,$SERVIZI,true)) $errors[]='Servizio non valido.';
        if(!in_array($stato,$STATI_OFFERTA,true)) $errors[]='Stato offerta non valido.';
        if($stato==='Aggiudicata' && !in_array($consulenteIncaricato,$CONSULENTI,true)) $errors[]='Se lo stato è Aggiudicata devi selezionare un consulente incaricato valido.';
        $opzioniDettaglio=$DETTAGLI_SERVIZIO[$servizio]??[]; if(!$opzioniDettaglio||!in_array($dettaglioServizio,$opzioniDettaglio,true)) $errors[]='Dettaglio servizio non valido.';
        if($rcoUtenteId<=0) $errors[]='Il campo RCO è obbligatorio.';
        if ($aziendaId === null) $errors[]='Il campo Azienda è obbligatorio. Se non presente, usa il popup \"Nuova Azienda\".';
        if($dataOfferta===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataOfferta)) $errors[]='Data Offerta obbligatoria e non valida.';
        $validitaGiorni=$validitaGiorniInput!==''?(int)$validitaGiorniInput:0; if($validitaGiorniInput!==''&&$validitaGiorni<=0)$errors[]='Validità non valida.';
        if($dataScadenza!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataScadenza))$errors[]='Data di Scadenza non valida.';
        if(!$errors){
            $dOff=new DateTimeImmutable($dataOfferta);
            if($validitaGiorni>0&&$dataScadenza==='') $dataScadenza=$dOff->modify('+' . $validitaGiorni . ' days')->format('Y-m-d');
            if($dataScadenza!==''&&$validitaGiorni===0){$dScad=new DateTimeImmutable($dataScadenza);$validitaGiorni=(int)$dOff->diff($dScad)->format('%r%a');}
            if($validitaGiorni<=0) $errors[]='Il campo Validità è obbligatorio.';
        }
        if(!in_array($modalitaPagamento,$MODALITA_PAGAMENTO,true)) $errors[]='Modalità di Pagamento non valida.';
        if($commissioneTipo!==''&&!in_array($commissioneTipo,['percentuale','euro'],true)) $errors[]='Tipo commissione non valido.';
        $commissioneValore=$commissioneValoreInput!==''?(float)str_replace(',','.',$commissioneValoreInput):null; if($commissioneValore!==null&&$commissioneValore<0)$errors[]='Commissione non valida.';
        $sconto=(float)str_replace(',','.',$scontoInput); if($scontoInput===''||$sconto<0||$sconto>100)$errors[]='% Sconto obbligatorio e tra 0 e 100.';

        if(!$errors){
            $tipoDettaglio=$servizio==='SISTEMI DI GESTIONE AZIENDALE'?'SottoServizio':'Servizio Specifico';
            $params=[
                ':servizio'=>$servizio, ':tipo_dettaglio'=>$tipoDettaglio, ':dettaglio_servizio'=>$dettaglioServizio, ':stato'=>$stato,
                ':specifiche_oggetto'=>$specificheOggetto!==''?$specificheOggetto:null, ':sede_erogazione_servizio'=>$sedeErogazione!==''?$sedeErogazione:null,
                ':azienda_id'=>$aziendaId,
                ':rco_utente_id'=>$rcoUtenteId, ':segnalato_da_utente_id'=>$segnalatoDaUtenteId, ':data_offerta'=>$dataOfferta,
                ':validita_giorni'=>$validitaGiorni, ':data_scadenza'=>$dataScadenza!==''?$dataScadenza:null, ':note'=>$note!==''?$note:null,
                ':promotore_azienda_id'=>$promotoreAziendaId, ':commissione_tipo'=>$commissioneTipo!==''?$commissioneTipo:null,
                ':commissione_valore'=>$commissioneValore, ':modalita_pagamento'=>$modalitaPagamento, ':sconto_percentuale'=>number_format($sconto,2,'.',''),
                ':consulente_incaricato'=>$consulenteIncaricato!==''?$consulenteIncaricato:null,
            ];
            if($id>0){
                $sql='UPDATE offerte SET servizio=:servizio,tipo_dettaglio=:tipo_dettaglio,dettaglio_servizio=:dettaglio_servizio,stato=:stato,specifiche_oggetto=:specifiche_oggetto,sede_erogazione_servizio=:sede_erogazione_servizio,azienda_id=:azienda_id,rco_utente_id=:rco_utente_id,segnalato_da_utente_id=:segnalato_da_utente_id,data_offerta=:data_offerta,validita_giorni=:validita_giorni,data_scadenza=:data_scadenza,note=:note,promotore_azienda_id=:promotore_azienda_id,commissione_tipo=:commissione_tipo,commissione_valore=:commissione_valore,modalita_pagamento=:modalita_pagamento,sconto_percentuale=:sconto_percentuale,consulente_incaricato=:consulente_incaricato WHERE id=:id';
                $params[':id']=$id; $pdo->prepare($sql)->execute($params); $offertaIdForCommessa=$id; $success='Offerta aggiornata correttamente.';
            } else {
                $anno=(int)date('Y'); $stn=$pdo->prepare('SELECT COALESCE(MAX(protocollo_numero),0)+1 FROM offerte WHERE anno_riferimento=:anno'); $stn->execute([':anno'=>$anno]);
                $num=(int)$stn->fetchColumn(); $protocollo=$num.'/'.$anno;
                $sql='INSERT INTO offerte (protocollo_numero,anno_riferimento,protocollo,servizio,tipo_dettaglio,dettaglio_servizio,stato,specifiche_oggetto,sede_erogazione_servizio,azienda_id,rco_utente_id,segnalato_da_utente_id,data_offerta,validita_giorni,data_scadenza,note,promotore_azienda_id,commissione_tipo,commissione_valore,modalita_pagamento,sconto_percentuale,consulente_incaricato)
                      VALUES (:protocollo_numero,:anno_riferimento,:protocollo,:servizio,:tipo_dettaglio,:dettaglio_servizio,:stato,:specifiche_oggetto,:sede_erogazione_servizio,:azienda_id,:rco_utente_id,:segnalato_da_utente_id,:data_offerta,:validita_giorni,:data_scadenza,:note,:promotore_azienda_id,:commissione_tipo,:commissione_valore,:modalita_pagamento,:sconto_percentuale,:consulente_incaricato)';
                $pdo->prepare($sql)->execute($params+[':protocollo_numero'=>$num,':anno_riferimento'=>$anno,':protocollo'=>$protocollo]);
                $offertaIdForCommessa=(int)$pdo->lastInsertId(); $success='Offerta creata correttamente con protocollo: '.$protocollo;
            }

            if($stato==='Aggiudicata'){
                $nuovaCommessa = ensureCommessa($pdo, $offertaIdForCommessa, $consulenteIncaricato);
                if($nuovaCommessa){ $success .= ' | Commessa generata: ' . $nuovaCommessa; }
            }

            $momData = $_POST['momento_data_momento'] ?? [];
            $momTip = $_POST['momento_tipologia'] ?? [];
            $momVal = $_POST['momento_valore_giornaliero_uomo'] ?? [];
            $momOre = $_POST['momento_ore'] ?? [];
            $momGio = $_POST['momento_giorni'] ?? [];
            if (is_array($momData) && is_array($momTip) && is_array($momVal)) {
                $pdo->prepare('DELETE FROM offerta_momenti_lavorazione WHERE offerta_id = :offerta_id')->execute([':offerta_id' => $offertaIdForCommessa]);
                $rows = max(count($momData), count($momTip), count($momVal));
                $insMomOff = $pdo->prepare('INSERT INTO offerta_momenti_lavorazione (offerta_id, data_momento, tipologia, valore_giornaliero_uomo, ore, giorni) VALUES (:offerta_id, :data_momento, :tipologia, :valore, :ore, :giorni)');
                for ($i = 0; $i < $rows; $i++) {
                    $d = trim((string)($momData[$i] ?? ''));
                    $t = trim((string)($momTip[$i] ?? ''));
                    $v = str_replace(',', '.', trim((string)($momVal[$i] ?? '')));
                    $o = str_replace(',', '.', trim((string)($momOre[$i] ?? '0')));
                    $g = str_replace(',', '.', trim((string)($momGio[$i] ?? '0')));
                    if ($d === '' && $t === '' && $v === '') continue;
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) continue;
                    if (!in_array($t, ['Apertura', 'Chiusura'], true)) continue;
                    if (!is_numeric($v) || (float)$v < 0) continue;
                    if (!is_numeric($o) || (float)$o < 0) $o = '0';
                    if (!is_numeric($g) || (float)$g < 0) $g = '0';
                    $insMomOff->execute([
                        ':offerta_id' => $offertaIdForCommessa,
                        ':data_momento' => $d,
                        ':tipologia' => $t,
                        ':valore' => number_format((float)$v, 2, '.', ''),
                        ':ore' => number_format((float)$o, 2, '.', ''),
                        ':giorni' => number_format((float)$g, 2, '.', ''),
                    ]);
                }
            }

            $action='list'; $editId=0; $offertaInModifica=null;
        }
    }
}

$filters=['protocollo','servizio','stato','dettaglio_servizio','rco_utente_id','segnalato_da_utente_id','data_offerta','validita_giorni','data_scadenza','modalita_pagamento','sconto_percentuale','consulente_incaricato','anno_riferimento'];
$where=[];$params=[];
foreach($filters as $f){$k='f_'.$f;$v=trim((string)($_GET[$k]??''));if($v==='')continue; if(in_array($f,['rco_utente_id','segnalato_da_utente_id','validita_giorni','anno_riferimento'],true)){$where[]="o.$f=:$k";$params[":$k"]=(int)$v;}else{$where[]="o.$f LIKE :$k";$params[":$k"]='%'.$v.'%';}}

$sqlList='SELECT o.*, CONCAT(r.nome, " ", r.cognome) AS rco_nome, c.protocollo AS commessa_protocollo, c.consulente_nome AS commessa_consulente,
                 a.ragione_sociale AS azienda_nome
          FROM offerte o
          LEFT JOIN utenti r ON r.id = o.rco_utente_id
          LEFT JOIN commesse c ON c.offerta_id = o.id
          LEFT JOIN aziende a ON a.id = o.azienda_id';
if($where){$sqlList.=' WHERE '.implode(' AND ',$where);} $sqlList.=' ORDER BY o.id DESC';
$stL=$pdo->prepare($sqlList); $stL->execute($params); $offerte=$stL->fetchAll();

$utenteLoggato=currentUser(); $formData=$offertaInModifica?:[];
renderHeader('Simplex - Offerte');
?>
<div class="container-fluid"><div class="row">
<nav class="col-12 col-md-3 col-lg-2 sidebar p-3"><h1 class="h4 text-white mb-4">Simplex</h1><ul class="nav nav-pills flex-column gap-2 mb-3">
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
            </ul><?php if($utenteLoggato): ?><div class="text-white small border-top pt-3"><div>Connesso come:</div><strong><?= htmlspecialchars($utenteLoggato['nome_completo']) ?></strong><br><span class="text-light-emphasis"><?= htmlspecialchars($utenteLoggato['nome_utente']) ?></span><div class="mt-2"><a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a></div></div><?php endif; ?></nav>
<main class="col-12 col-md-9 col-lg-10 p-4">
<div class="d-flex justify-content-between align-items-center mb-4"><h2 class="mb-0">Gestione Offerte</h2><a class="btn btn-primary" href="offerte.php?action=new">Nuova Offerta</a></div>
<?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php foreach($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

<?php if($action==='new'||$editId>0): ?>
<div class="card mb-4"><div class="card-header"><?= $editId>0?'Modifica Offerta':'Nuova Offerta' ?></div><div class="card-body"><form method="post" class="row g-3" id="form-offerta"><input type="hidden" name="azione" value="save"><input type="hidden" name="id" value="<?= (int)($formData['id']??0) ?>">
<div class="col-md-4"><label class="form-label">Servizio *</label><select class="form-select" name="servizio" id="servizio" required><option value="">-- Seleziona --</option><?php foreach($SERVIZI as $sv): ?><option value="<?= htmlspecialchars($sv) ?>" <?= (($formData['servizio']??'')===$sv)?'selected':'' ?>><?= htmlspecialchars($sv) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label" id="label-dettaglio">Dettaglio *</label><select class="form-select" name="dettaglio_servizio" id="dettaglio_servizio" required></select></div>
<div class="col-md-4"><label class="form-label">Status Offerta</label><select class="form-select" name="stato" id="stato_offerta" required><?php foreach($STATI_OFFERTA as $st): ?><option value="<?= $st ?>" <?= (($formData['stato']??'In Lavorazione')===$st)?'selected':'' ?>><?= $st ?></option><?php endforeach; ?></select></div>
<div class="col-md-6" id="box-consulente"><label class="form-label">Consulente incaricato (per Aggiudicata)</label><select class="form-select" name="consulente_incaricato" id="consulente_incaricato"><option value="">-- Seleziona --</option><?php foreach($CONSULENTI as $cons): ?><option value="<?= htmlspecialchars($cons) ?>" <?= (($formData['consulente_incaricato']??'')===$cons)?'selected':'' ?>><?= htmlspecialchars($cons) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Specifiche Oggetto</label><textarea class="form-control" name="specifiche_oggetto" rows="2"><?= htmlspecialchars($formData['specifiche_oggetto']??'') ?></textarea></div>
<div class="col-md-6"><label class="form-label">Sede di erogazione del servizio</label><input class="form-control" name="sede_erogazione_servizio" value="<?= htmlspecialchars($formData['sede_erogazione_servizio']??'') ?>"></div>
<div class="col-md-6"><label class="form-label">Azienda *</label><select class="form-select" name="azienda_id" id="azienda_id"><option value="">-- Seleziona --</option><?php foreach($aziendeTutte as $az): ?><option value="<?= (int)$az['id'] ?>" <?= ((int)($formData['azienda_id']??0)===(int)$az['id'])?'selected':'' ?>><?= htmlspecialchars($az['ragione_sociale']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6 d-flex align-items-end"><button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNuovaAzienda">+ Nuova Azienda</button></div>
<div class="col-md-3"><label class="form-label">RCO *</label><select class="form-select" name="rco_utente_id" required><option value="">-- Seleziona --</option><?php foreach($utenti as $u): ?><option value="<?= (int)$u['id'] ?>" <?= ((int)($formData['rco_utente_id']??0)===(int)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nome'].' '.$u['cognome']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Segnalato da</label><select class="form-select" name="segnalato_da_utente_id"><option value="">-- Seleziona --</option><?php foreach($utenti as $u): ?><option value="<?= (int)$u['id'] ?>" <?= ((int)($formData['segnalato_da_utente_id']??0)===(int)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nome'].' '.$u['cognome']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Data Offerta *</label><input type="date" class="form-control" id="data_offerta" name="data_offerta" required value="<?= htmlspecialchars($formData['data_offerta']??'') ?>"></div>
<div class="col-md-4"><label class="form-label">Validità (giorni) *</label><input type="number" min="1" class="form-control" id="validita_giorni" name="validita_giorni" required value="<?= htmlspecialchars($formData['validita_giorni']??'') ?>"></div>
<div class="col-md-4"><label class="form-label">Data Scadenza</label><input type="date" class="form-control" id="data_scadenza" name="data_scadenza" value="<?= htmlspecialchars($formData['data_scadenza']??'') ?>"></div>
<div class="col-md-4"><label class="form-label">Promotore</label><select class="form-select" name="promotore_azienda_id"><option value="">-- Seleziona --</option><?php foreach($aziendePromotori as $az): ?><option value="<?= (int)$az['id'] ?>" <?= ((int)($formData['promotore_azienda_id']??0)===(int)$az['id'])?'selected':'' ?>><?= htmlspecialchars($az['ragione_sociale']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label class="form-label">Commissione</label><select class="form-select" name="commissione_tipo"><option value="">--</option><option value="percentuale" <?= (($formData['commissione_tipo']??'')==='percentuale')?'selected':'' ?>>%</option><option value="euro" <?= (($formData['commissione_tipo']??'')==='euro')?'selected':'' ?>>€</option></select></div>
<div class="col-md-2"><label class="form-label">Valore</label><input class="form-control" name="commissione_valore" value="<?= htmlspecialchars($formData['commissione_valore']??'') ?>"></div>
<div class="col-md-4"><label class="form-label">Modalità di Pagamento</label><select class="form-select" name="modalita_pagamento" required><option value="">-- Seleziona --</option><?php foreach($MODALITA_PAGAMENTO as $m): ?><option value="<?= htmlspecialchars($m) ?>" <?= (($formData['modalita_pagamento']??'')===$m)?'selected':'' ?>><?= htmlspecialchars($m) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">% Sconto</label><input type="number" min="0" max="100" step="0.01" class="form-control" required name="sconto_percentuale" value="<?= htmlspecialchars($formData['sconto_percentuale']??'') ?>"></div>
<div class="col-12"><label class="form-label">Note</label><textarea class="form-control" name="note" rows="2"><?= htmlspecialchars($formData['note']??'') ?></textarea></div>
<div class="col-12">
    <label class="form-label">Momenti di Lavorazione (da riportare in Commessa)</label>
    <div class="table-responsive">
        <table class="table table-sm table-bordered" id="tabella-momenti-offerta">
            <thead><tr><th>Data</th><th>Tipologia</th><th>Valore €/giorno</th><th>Ore</th><th>Giorni</th><th></th></tr></thead>
            <tbody>
            <?php $momRows = $momentiOfferta ?: [['data_momento'=>'','tipologia'=>'Apertura','valore_giornaliero_uomo'=>'','ore'=>'0','giorni'=>'0']]; ?>
            <?php foreach($momRows as $m): ?>
                <tr>
                    <td><input type="date" class="form-control" name="momento_data_momento[]" value="<?= htmlspecialchars($m['data_momento'] ?? '') ?>"></td>
                    <td><select class="form-select" name="momento_tipologia[]"><option value="Apertura" <?= (($m['tipologia'] ?? '')==='Apertura')?'selected':'' ?>>Apertura</option><option value="Chiusura" <?= (($m['tipologia'] ?? '')==='Chiusura')?'selected':'' ?>>Chiusura</option></select></td>
                    <td><input type="number" min="0" step="0.01" class="form-control" name="momento_valore_giornaliero_uomo[]" value="<?= htmlspecialchars((string)($m['valore_giornaliero_uomo'] ?? '')) ?>"></td>
                    <td><input type="number" min="0" step="0.01" class="form-control" name="momento_ore[]" value="<?= htmlspecialchars((string)($m['ore'] ?? '0')) ?>"></td>
                    <td><input type="number" min="0" step="0.01" class="form-control" name="momento_giorni[]" value="<?= htmlspecialchars((string)($m['giorni'] ?? '0')) ?>"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger btn-rimuovi-momento">✕</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-aggiungi-momento-offerta">+ Aggiungi momento</button>
</div>
<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Salva Offerta</button><a class="btn btn-outline-secondary" href="offerte.php">Annulla</a></div>
</form></div></div>
<?php endif; ?>

<div class="card mb-4"><div class="card-header">Filtri Offerte</div><div class="card-body"><form method="get" class="row g-2">
<div class="col-md-2"><input class="form-control" name="f_protocollo" placeholder="Protocollo" value="<?= htmlspecialchars($_GET['f_protocollo']??'') ?>"></div>
<div class="col-md-2"><input class="form-control" name="f_servizio" placeholder="Servizio" value="<?= htmlspecialchars($_GET['f_servizio']??'') ?>"></div>
<div class="col-md-2"><input class="form-control" name="f_stato" placeholder="Stato" value="<?= htmlspecialchars($_GET['f_stato']??'') ?>"></div>
<div class="col-md-2"><input class="form-control" name="f_data_offerta" placeholder="Data offerta" value="<?= htmlspecialchars($_GET['f_data_offerta']??'') ?>"></div>
<div class="col-md-2"><input class="form-control" name="f_data_scadenza" placeholder="Data scadenza" value="<?= htmlspecialchars($_GET['f_data_scadenza']??'') ?>"></div>
<div class="col-md-2"><input class="form-control" name="f_consulente_incaricato" placeholder="Consulente" value="<?= htmlspecialchars($_GET['f_consulente_incaricato']??'') ?>"></div>
<div class="col-12 d-flex gap-2"><button class="btn btn-outline-primary" type="submit">Filtra</button><a class="btn btn-outline-secondary" href="offerte.php">Reset</a></div>
</form></div></div>

<div class="card"><div class="card-header">Elenco Offerte</div><div class="table-responsive"><table class="table table-striped table-hover mb-0 align-middle"><thead class="table-light"><tr><th>Protocollo</th><th>Status</th><th>Servizio</th><th>Azienda</th><th>Data Offerta</th><th>Scadenza</th><th>Commessa</th><th>Consulente</th><th>Azioni</th></tr></thead><tbody>
<?php if(!$offerte): ?><tr><td colspan="9" class="text-center text-muted py-4">Nessuna offerta trovata.</td></tr><?php endif; ?>
<?php foreach($offerte as $offerta): ?><tr><td><?= htmlspecialchars($offerta['protocollo']) ?></td><td><span class="badge text-bg-<?= $offerta['stato']==='Aggiudicata'?'success':($offerta['stato']==='Scaduta'?'secondary':'primary') ?>"><?= htmlspecialchars($offerta['stato']) ?></span></td><td><?= htmlspecialchars($offerta['servizio']) ?></td><td><?= htmlspecialchars($offerta['azienda_nome'] ?? '-') ?></td><td><?= htmlspecialchars($offerta['data_offerta']??'-') ?></td><td><?= htmlspecialchars($offerta['data_scadenza']??'-') ?></td><td><?= htmlspecialchars($offerta['commessa_protocollo']??'-') ?></td><td><?= htmlspecialchars($offerta['commessa_consulente']??'-') ?></td><td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="offerte.php?edit=<?= (int)$offerta['id'] ?>">Modifica</a><a class="btn btn-sm btn-outline-dark" href="lavorazioni.php?offerta_id=<?= (int)$offerta['id'] ?>">Lavorazioni</a><form method="post" onsubmit="return confirm('Confermi eliminazione offerta?');"><input type="hidden" name="azione" value="delete"><input type="hidden" name="id" value="<?= (int)$offerta['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Elimina</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div></div>

<div class="modal fade" id="modalNuovaAzienda" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="formNuovaAzienda">
        <div class="modal-header">
          <h5 class="modal-title">Nuova Azienda</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="azione" value="ajax_create_azienda">
          <div id="nuovaAziendaError" class="alert alert-danger d-none"></div>
          <div class="row g-2">
            <div class="col-md-4"><label class="form-label">Partita IVA *</label><input class="form-control" name="partita_iva" maxlength="11" required></div>
            <div class="col-md-4"><label class="form-label">Codice Fiscale</label><input class="form-control" name="codice_fiscale" maxlength="16"></div>
            <div class="col-md-4"><label class="form-label">Ragione Sociale *</label><input class="form-control" name="ragione_sociale" maxlength="30" required></div>
            <div class="col-md-6"><label class="form-label">Tipologia Azienda *</label><select class="form-select" name="tipologia_azienda[]" multiple required><?php foreach($TIPOLOGIE_AZIENDA as $tip): ?><option value="<?= htmlspecialchars($tip) ?>"><?= htmlspecialchars($tip) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Organico Medio *</label><input class="form-control" name="organico_medio" maxlength="30" required></div>
            <div class="col-md-3"><label class="form-label">Fatturato *</label><input type="number" step="0.01" min="0" class="form-control" name="fatturato" required></div>
            <div class="col-md-4"><label class="form-label">Telefono</label><input class="form-control" name="telefono"></div>
            <div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
            <div class="col-md-4"><label class="form-label">PEC</label><input class="form-control" name="pec"></div>
            <div class="col-md-6"><label class="form-label">Località</label><input class="form-control" name="localita"></div>
            <div class="col-md-2"><label class="form-label">Prov.</label><input class="form-control" name="provincia" maxlength="2"></div>
            <div class="col-md-4"><label class="form-label">Categoria</label><input class="form-control" name="categoria"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary">Salva Azienda</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const dettagliPerServizio = <?= json_encode($DETTAGLI_SERVIZIO, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const dettaglioPreselezionato = <?= json_encode($formData['dettaglio_servizio'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const servizioSelect=document.getElementById('servizio'); const detSel=document.getElementById('dettaglio_servizio'); const lbl=document.getElementById('label-dettaglio');
function renderDettaglio(){ if(!servizioSelect||!detSel||!lbl) return; const s=servizioSelect.value; const op=dettagliPerServizio[s]||[]; lbl.textContent=s==='SISTEMI DI GESTIONE AZIENDALE'?'SottoServizio *':'Servizio Specifico *'; detSel.innerHTML='<option value="">-- Seleziona --</option>'; op.forEach(v=>{const o=document.createElement('option');o.value=v;o.textContent=v;if(v===dettaglioPreselezionato)o.selected=true;detSel.appendChild(o);}); }
if(servizioSelect){servizioSelect.addEventListener('change',renderDettaglio); renderDettaglio();}
const dataOff=document.getElementById('data_offerta'), valIn=document.getElementById('validita_giorni'), scadIn=document.getElementById('data_scadenza');
function addDays(d,days){const x=new Date(d+'T00:00:00');x.setDate(x.getDate()+Number(days));return x.toISOString().slice(0,10);} function daysBetween(a,b){const d1=new Date(a+'T00:00:00');const d2=new Date(b+'T00:00:00');return Math.round((d2-d1)/(1000*60*60*24));}
if(dataOff&&valIn&&scadIn){valIn.addEventListener('input',()=>{if(dataOff.value&&valIn.value)scadIn.value=addDays(dataOff.value,valIn.value)});scadIn.addEventListener('change',()=>{if(dataOff.value&&scadIn.value){const d=daysBetween(dataOff.value,scadIn.value);if(d>0)valIn.value=d;}});}
const statoSel=document.getElementById('stato_offerta'); const consulBox=document.getElementById('box-consulente'); const consSel=document.getElementById('consulente_incaricato');
function toggleCons(){ if(!statoSel||!consulBox||!consSel) return; const on=statoSel.value==='Aggiudicata'; consulBox.style.display=on?'block':'none'; consSel.required=on; if(!on) consSel.value=''; }
if(statoSel){statoSel.addEventListener('change',toggleCons); toggleCons();}
const aziendaSel=document.getElementById('azienda_id');
const formNuovaAzienda=document.getElementById('formNuovaAzienda');
const nuovaAziendaError=document.getElementById('nuovaAziendaError');
if(formNuovaAzienda&&aziendaSel){
    formNuovaAzienda.addEventListener('submit',async (e)=>{
        e.preventDefault();
        nuovaAziendaError.classList.add('d-none');
        const fd=new FormData(formNuovaAzienda);
        const resp=await fetch('offerte.php',{method:'POST',body:fd});
        const data=await resp.json();
        if(!data.ok){
            nuovaAziendaError.textContent=(data.errors||['Errore nel salvataggio azienda.']).join(' ');
            nuovaAziendaError.classList.remove('d-none');
            return;
        }
        const opt=document.createElement('option');
        opt.value=String(data.id);
        opt.textContent=data.ragione_sociale;
        opt.selected=true;
        aziendaSel.appendChild(opt);
        formNuovaAzienda.reset();
        const modalEl=document.getElementById('modalNuovaAzienda');
        const modal=bootstrap.Modal.getInstance(modalEl);
        if(modal){ modal.hide(); }
    });
}
const tabellaMomentiOfferta=document.querySelector('#tabella-momenti-offerta tbody');
const btnAggiungiMomentoOfferta=document.getElementById('btn-aggiungi-momento-offerta');
if(tabellaMomentiOfferta&&btnAggiungiMomentoOfferta){
    btnAggiungiMomentoOfferta.addEventListener('click',()=>{
        const tr=document.createElement('tr');
        tr.innerHTML='<td><input type=\"date\" class=\"form-control\" name=\"momento_data_momento[]\"></td><td><select class=\"form-select\" name=\"momento_tipologia[]\"><option value=\"Apertura\">Apertura</option><option value=\"Chiusura\">Chiusura</option></select></td><td><input type=\"number\" min=\"0\" step=\"0.01\" class=\"form-control\" name=\"momento_valore_giornaliero_uomo[]\"></td><td><input type=\"number\" min=\"0\" step=\"0.01\" class=\"form-control\" name=\"momento_ore[]\" value=\"0\"></td><td><input type=\"number\" min=\"0\" step=\"0.01\" class=\"form-control\" name=\"momento_giorni[]\" value=\"0\"></td><td><button type=\"button\" class=\"btn btn-sm btn-outline-danger btn-rimuovi-momento\">✕</button></td>';
        tabellaMomentiOfferta.appendChild(tr);
    });
    tabellaMomentiOfferta.addEventListener('click',(e)=>{
        const btn=e.target.closest('.btn-rimuovi-momento');
        if(!btn) return;
        btn.closest('tr')?.remove();
    });
}
</script>
<?php renderFooter(); ?>
