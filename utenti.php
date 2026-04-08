<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';

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
        attivo TINYINT(1) NOT NULL DEFAULT 1,
        creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeUtente = trim($_POST['nome_utente'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ruolo = 'amministratore';
    $attivo = isset($_POST['attivo']) ? 1 : 0;

    if ($nomeUtente === '' || $nome === '' || $cognome === '' || $email === '') {
        $errors[] = 'Compila tutti i campi obbligatori.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Inserisci una email valida.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO utenti (nome_utente, nome, cognome, telefono, email, ruolo, attivo)
             VALUES (:nome_utente, :nome, :cognome, :telefono, :email, :ruolo, :attivo)'
        );

        try {
            $stmt->execute([
                ':nome_utente' => $nomeUtente,
                ':nome' => $nome,
                ':cognome' => $cognome,
                ':telefono' => $telefono !== '' ? $telefono : null,
                ':email' => $email,
                ':ruolo' => $ruolo,
                ':attivo' => $attivo,
            ]);

            $success = 'Utente creato correttamente.';
        } catch (PDOException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                $errors[] = 'Nome Utente già presente.';
            } else {
                $errors[] = 'Errore durante il salvataggio dell\'utente.';
            }
        }
    }
}

$utenti = $pdo->query('SELECT * FROM utenti ORDER BY id DESC')->fetchAll();

renderHeader('Simplex - Utenti');
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item"><a class="nav-link active" href="utenti.php">Utenti</a></li>
                <li class="nav-item"><a class="nav-link disabled" href="#">Ruoli</a></li>
                <li class="nav-item"><a class="nav-link disabled" href="#">Impostazioni</a></li>
            </ul>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Gestione Utenti</h2>
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
