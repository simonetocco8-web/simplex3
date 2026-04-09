<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

$RUOLI_DISPONIBILI = ['Amministratore', 'Responsabile di Area', 'Consulente'];

$errors = [];
$success = null;

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS utenti (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome_utente VARCHAR(50) NOT NULL UNIQUE,
        nome VARCHAR(50) NOT NULL,
        cognome VARCHAR(50) NOT NULL,
        telefono VARCHAR(30) DEFAULT NULL,
        email VARCHAR(100) NOT NULL,
        ruolo VARCHAR(50) NOT NULL DEFAULT 'Amministratore',
        password_hash VARCHAR(255) NOT NULL,
        attivo TINYINT(1) NOT NULL DEFAULT 1,
        creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS utenti_ruoli (
        utente_id INT UNSIGNED NOT NULL,
        ruolo VARCHAR(50) NOT NULL,
        PRIMARY KEY (utente_id, ruolo),
        CONSTRAINT fk_utente_ruolo_utente FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$hasPasswordHashColumn = (bool) $pdo->query("SHOW COLUMNS FROM utenti LIKE 'password_hash'")->fetch();
if (!$hasPasswordHashColumn) {
    $pdo->exec("ALTER TABLE utenti ADD COLUMN password_hash VARCHAR(255) NULL AFTER ruolo");
}

// Backfill ruoli per utenti esistenti
$pdo->exec("INSERT IGNORE INTO utenti_ruoli (utente_id, ruolo) SELECT id, ruolo FROM utenti");

$totaleUtenti = (int) $pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn();
if (!isLoggedIn() && $totaleUtenti > 0) {
    header('Location: login.php');
    exit;
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$utenteInModifica = null;
$ruoliUtenteInModifica = [];

if ($editId > 0) {
    $stmtEdit = $pdo->prepare('SELECT id, nome_utente, nome, cognome, telefono, email, attivo FROM utenti WHERE id = :id LIMIT 1');
    $stmtEdit->execute([':id' => $editId]);
    $utenteInModifica = $stmtEdit->fetch();

    if ($utenteInModifica) {
        $stmtRuoli = $pdo->prepare('SELECT ruolo FROM utenti_ruoli WHERE utente_id = :utente_id');
        $stmtRuoli->execute([':utente_id' => $editId]);
        $ruoliUtenteInModifica = array_column($stmtRuoli->fetchAll(), 'ruolo');
    } else {
        $errors[] = 'Utente da modificare non trovato.';
        $editId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? 'crea';
    $nomeUtente = trim($_POST['nome_utente'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ruoliSelezionati = $_POST['ruoli'] ?? [];
    $attivo = isset($_POST['attivo']) ? 1 : 0;

    if (!is_array($ruoliSelezionati)) {
        $ruoliSelezionati = [];
    }
    $ruoliSelezionati = array_values(array_intersect($RUOLI_DISPONIBILI, $ruoliSelezionati));

    if ($nomeUtente === '' || $nome === '' || $cognome === '' || $email === '') {
        $errors[] = 'Compila tutti i campi obbligatori.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Inserisci una email valida.';
    }

    if (count($ruoliSelezionati) === 0) {
        $errors[] = 'Seleziona almeno un ruolo.';
    }

    if ($azione === 'crea' && $password === '') {
        $errors[] = 'La password è obbligatoria in creazione.';
    }

    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'La password deve avere almeno 8 caratteri.';
    }

    if (!$errors && $azione === 'crea') {
        $ruoloPrincipale = $ruoliSelezionati[0];

        $stmt = $pdo->prepare(
            'INSERT INTO utenti (nome_utente, nome, cognome, telefono, email, ruolo, password_hash, attivo)
             VALUES (:nome_utente, :nome, :cognome, :telefono, :email, :ruolo, :password_hash, :attivo)'
        );

        try {
            $stmt->execute([
                ':nome_utente' => $nomeUtente,
                ':nome' => $nome,
                ':cognome' => $cognome,
                ':telefono' => $telefono !== '' ? $telefono : null,
                ':email' => $email,
                ':ruolo' => $ruoloPrincipale,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':attivo' => $attivo,
            ]);

            $utenteId = (int) $pdo->lastInsertId();
            $stmtInsertRuolo = $pdo->prepare('INSERT INTO utenti_ruoli (utente_id, ruolo) VALUES (:utente_id, :ruolo)');
            foreach ($ruoliSelezionati as $ruolo) {
                $stmtInsertRuolo->execute([':utente_id' => $utenteId, ':ruolo' => $ruolo]);
            }

            $success = 'Utente creato correttamente.';
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $errors[] = 'Nome Utente o Email già presente.';
            } else {
                $errors[] = 'Errore durante il salvataggio dell\'utente.';
            }
        }
    }

    if (!$errors && $azione === 'modifica') {
        $idUtente = (int) ($_POST['id_utente'] ?? 0);
        if ($idUtente <= 0) {
            $errors[] = 'ID utente non valido.';
        } else {
            $ruoloPrincipale = $ruoliSelezionati[0];
            $params = [
                ':id' => $idUtente,
                ':nome_utente' => $nomeUtente,
                ':nome' => $nome,
                ':cognome' => $cognome,
                ':telefono' => $telefono !== '' ? $telefono : null,
                ':email' => $email,
                ':ruolo' => $ruoloPrincipale,
                ':attivo' => $attivo,
            ];

            $sql = 'UPDATE utenti
                    SET nome_utente = :nome_utente,
                        nome = :nome,
                        cognome = :cognome,
                        telefono = :telefono,
                        email = :email,
                        ruolo = :ruolo,
                        attivo = :attivo';

            if ($password !== '') {
                $sql .= ', password_hash = :password_hash';
                $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= ' WHERE id = :id';

            try {
                $pdo->prepare($sql)->execute($params);

                $pdo->prepare('DELETE FROM utenti_ruoli WHERE utente_id = :utente_id')->execute([':utente_id' => $idUtente]);
                $stmtInsertRuolo = $pdo->prepare('INSERT INTO utenti_ruoli (utente_id, ruolo) VALUES (:utente_id, :ruolo)');
                foreach ($ruoliSelezionati as $ruolo) {
                    $stmtInsertRuolo->execute([':utente_id' => $idUtente, ':ruolo' => $ruolo]);
                }

                $success = 'Utente aggiornato correttamente.';
                $editId = 0;
                $utenteInModifica = null;
                $ruoliUtenteInModifica = [];
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'Nome Utente o Email già presente.';
                } else {
                    $errors[] = 'Errore durante l\'aggiornamento dell\'utente.';
                }
            }
        }
    }
}

$utenteLoggato = currentUser();
$utenti = $pdo->query(
    "SELECT u.id, u.nome_utente, u.nome, u.cognome, u.telefono, u.email, u.attivo, u.creato_il,
            GROUP_CONCAT(ur.ruolo ORDER BY ur.ruolo SEPARATOR ', ') AS ruoli
     FROM utenti u
     LEFT JOIN utenti_ruoli ur ON ur.utente_id = u.id
     GROUP BY u.id
     ORDER BY u.id DESC"
)->fetchAll();

renderHeader('Simplex - Utenti');
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2 mb-3">
                <li class="nav-item"><a class="nav-link" href="bacheca.php">Bacheca</a></li>
                <li class="nav-item"><a class="nav-link active" href="utenti.php">Utenti</a></li>
                <li class="nav-item"><a class="nav-link" href="aziende.php">Aziende</a></li>
                <li class="nav-item"><a class="nav-link" href="offerte.php">Offerte</a></li>
                <li class="nav-item"><a class="nav-link" href="commesse.php">Commesse</a></li>
                <li class="nav-item"><a class="nav-link" href="amministrazione_produzione.php">Amministrazione</a></li>
                <li class="nav-item"><a class="nav-link disabled" href="#">Impostazioni</a></li>
            </ul>

            <?php if ($utenteLoggato): ?>
                <div class="text-white small border-top pt-3">
                    <div>Connesso come:</div>
                    <strong><?= htmlspecialchars($utenteLoggato['nome_completo']) ?></strong><br>
                    <span class="text-light-emphasis"><?= htmlspecialchars($utenteLoggato['nome_utente']) ?></span>
                    <div class="mt-2">
                        <a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning small">Primo accesso: crea il primo amministratore.</div>
            <?php endif; ?>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Gestione Utenti</h2>
                <?php if (!$utenteLoggato && $totaleUtenti === 0): ?>
                    <span class="badge text-bg-warning">Setup iniziale</span>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <div class="card mb-4">
                <div class="card-header"><?= $utenteInModifica ? 'Modifica Utente' : 'Nuovo Utente' ?></div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="azione" value="<?= $utenteInModifica ? 'modifica' : 'crea' ?>">
                        <?php if ($utenteInModifica): ?>
                            <input type="hidden" name="id_utente" value="<?= (int)$utenteInModifica['id'] ?>">
                        <?php endif; ?>

                        <div class="col-md-4">
                            <label class="form-label" for="nome_utente">Nome Utente *</label>
                            <input class="form-control" id="nome_utente" name="nome_utente" value="<?= htmlspecialchars($utenteInModifica['nome_utente'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="nome">Nome *</label>
                            <input class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($utenteInModifica['nome'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cognome">Cognome *</label>
                            <input class="form-control" id="cognome" name="cognome" value="<?= htmlspecialchars($utenteInModifica['cognome'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="telefono">Telefono</label>
                            <input class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($utenteInModifica['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="email">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($utenteInModifica['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password">Password <?= $utenteInModifica ? '' : '*' ?></label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" <?= $utenteInModifica ? '' : 'required' ?>>
                            <?php if ($utenteInModifica): ?>
                                <small class="text-muted">Lascia vuoto per non modificare la password.</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-10">
                            <label class="form-label d-block">Ruoli *</label>
                            <?php
                            $ruoliChecked = $utenteInModifica ? $ruoliUtenteInModifica : ['Amministratore'];
                            foreach ($RUOLI_DISPONIBILI as $ruolo):
                                $idRuolo = 'ruolo_' . md5($ruolo);
                            ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="<?= $idRuolo ?>" name="ruoli[]" value="<?= htmlspecialchars($ruolo) ?>" <?= in_array($ruolo, $ruoliChecked, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $idRuolo ?>"><?= htmlspecialchars($ruolo) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="attivo" name="attivo" <?= $utenteInModifica ? (((int)($utenteInModifica['attivo'] ?? 0) === 1) ? 'checked' : '') : 'checked' ?>>
                                <label class="form-check-label" for="attivo">Attivo</label>
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary" type="submit"><?= $utenteInModifica ? 'Aggiorna Utente' : 'Salva Utente' ?></button>
                            <?php if ($utenteInModifica): ?>
                                <a class="btn btn-outline-secondary" href="utenti.php">Annulla modifica</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco Utenti</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Nome Utente</th>
                            <th>Nome</th>
                            <th>Cognome</th>
                            <th>Telefono</th>
                            <th>Email</th>
                            <th>Ruoli</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$utenti): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nessun utente inserito.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($utenti as $utente): ?>
                            <tr>
                                <td><?= htmlspecialchars($utente['nome_utente']) ?></td>
                                <td><?= htmlspecialchars($utente['nome']) ?></td>
                                <td><?= htmlspecialchars($utente['cognome']) ?></td>
                                <td><?= htmlspecialchars($utente['telefono'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($utente['email']) ?></td>
                                <td>
                                    <?php foreach (explode(', ', (string)($utente['ruoli'] ?? '')) as $ruolo): ?>
                                        <?php if ($ruolo !== ''): ?>
                                            <span class="badge text-bg-primary me-1"><?= htmlspecialchars($ruolo) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php if ((int)$utente['attivo'] === 1): ?>
                                        <span class="badge text-bg-success">Attivo</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Disattivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="utenti.php?edit=<?= (int)$utente['id'] ?>">Modifica</a>
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
