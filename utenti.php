<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

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
        ruolo VARCHAR(30) NOT NULL DEFAULT 'amministratore',
        password_hash VARCHAR(255) NOT NULL,
        attivo TINYINT(1) NOT NULL DEFAULT 1,
        creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$hasPasswordHashColumn = (bool) $pdo->query("SHOW COLUMNS FROM utenti LIKE 'password_hash'")->fetch();
if (!$hasPasswordHashColumn) {
    $pdo->exec("ALTER TABLE utenti ADD COLUMN password_hash VARCHAR(255) NULL AFTER ruolo");
}

$totaleUtenti = (int) $pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn();
if (!isLoggedIn() && $totaleUtenti > 0) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeUtente = trim($_POST['nome_utente'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ruolo = 'amministratore';
    $attivo = isset($_POST['attivo']) ? 1 : 0;

    if ($nomeUtente === '' || $nome === '' || $cognome === '' || $email === '' || $password === '') {
        $errors[] = 'Compila tutti i campi obbligatori.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'La password deve avere almeno 8 caratteri.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Inserisci una email valida.';
    }

    if (!$errors) {
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
                ':ruolo' => $ruolo,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':attivo' => $attivo,
            ]);

            $success = 'Utente creato correttamente.';
        } catch (PDOException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                $errors[] = 'Nome Utente o Email già presente.';
            } else {
                $errors[] = 'Errore durante il salvataggio dell\'utente.';
            }
        }
    }
}

$utenteLoggato = currentUser();
$utenti = $pdo->query('SELECT id, nome_utente, nome, cognome, telefono, email, ruolo, attivo, creato_il FROM utenti ORDER BY id DESC')->fetchAll();

renderHeader('Simplex - Utenti');
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2 mb-3">
                <li class="nav-item"><a class="nav-link active" href="utenti.php">Utenti</a></li>
                <li class="nav-item"><a class="nav-link disabled" href="#">Ruoli</a></li>
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
                <div class="card-header">Nuovo Utente</div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="nome_utente">Nome Utente *</label>
                            <input class="form-control" id="nome_utente" name="nome_utente" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="nome">Nome *</label>
                            <input class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cognome">Cognome *</label>
                            <input class="form-control" id="cognome" name="cognome" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="telefono">Telefono</label>
                            <input class="form-control" id="telefono" name="telefono">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="email">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="ruolo">Ruolo</label>
                            <input class="form-control" id="ruolo" value="Amministratore" readonly>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="attivo" name="attivo" checked>
                                <label class="form-check-label" for="attivo">Attivo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Salva Utente</button>
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
                            <th>Ruolo</th>
                            <th>Stato</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$utenti): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nessun utente inserito.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($utenti as $utente): ?>
                            <tr>
                                <td><?= htmlspecialchars($utente['nome_utente']) ?></td>
                                <td><?= htmlspecialchars($utente['nome']) ?></td>
                                <td><?= htmlspecialchars($utente['cognome']) ?></td>
                                <td><?= htmlspecialchars($utente['telefono'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($utente['email']) ?></td>
                                <td><span class="badge text-bg-primary text-uppercase"><?= htmlspecialchars($utente['ruolo']) ?></span></td>
                                <td>
                                    <?php if ((int)$utente['attivo'] === 1): ?>
                                        <span class="badge text-bg-success">Attivo</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Disattivo</span>
                                    <?php endif; ?>
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
