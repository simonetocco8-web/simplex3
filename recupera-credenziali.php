<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';

$errors = [];
$success = null;
$tempPassword = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if ($identifier === '') {
        $errors[] = 'Inserisci Nome Utente o Email.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id, nome_utente, email, attivo FROM utenti WHERE nome_utente = :nome_utente OR email = :email LIMIT 1');
        $stmt->execute([':nome_utente' => $identifier, ':email' => $identifier]);
        $utente = $stmt->fetch();

        if (!$utente) {
            $errors[] = 'Utente non trovato.';
        } elseif ((int) $utente['attivo'] !== 1) {
            $errors[] = 'Utente disattivo. Contatta un amministratore.';
        } else {
            $tempPassword = bin2hex(random_bytes(4));
            $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE utenti SET password_hash = :password_hash WHERE id = :id');
            $upd->execute([
                ':password_hash' => $hash,
                ':id' => $utente['id'],
            ]);

            $success = 'Password temporanea generata. Usala per accedere e poi cambiala dal pannello utente (funzione da implementare).';
        }
    }
}

renderHeader('Simplex - Recupero Credenziali');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h4 mb-3">Recupero credenziali</h2>
                    <p class="text-muted">Inserisci Nome Utente o Email per generare una password temporanea.</p>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>

                    <?php if ($tempPassword): ?>
                        <div class="alert alert-warning">
                            <strong>Password temporanea:</strong>
                            <code><?= htmlspecialchars($tempPassword) ?></code>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="identifier">Nome Utente o Email</label>
                            <input class="form-control" type="text" id="identifier" name="identifier" required>
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-primary" type="submit">Genera password temporanea</button>
                        </div>
                    </form>
                    <div class="mt-3">
                        <a href="login.php">Torna al login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php renderFooter(); ?>
