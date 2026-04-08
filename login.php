<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: utenti.php');
    exit;
}

$errors = [];
$success = null;

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $success = 'Logout effettuato correttamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $errors[] = 'Inserisci Nome Utente/Email e Password.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT * FROM utenti WHERE (nome_utente = :identifier OR email = :identifier) AND attivo = 1 LIMIT 1');
        $stmt->execute([':identifier' => $identifier]);
        $utente = $stmt->fetch();

        if ($utente && password_verify($password, $utente['password_hash'])) {
            loginUser($utente);
            header('Location: utenti.php');
            exit;
        }

        $errors[] = 'Credenziali non valide o utente disattivo.';
    }
}

renderHeader('Simplex - Login');
?>
<div class="container-fluid">
    <div class="row min-vh-100">
        <aside class="col-12 col-md-4 col-lg-3 sidebar p-4">
            <h1 class="h3 text-white mb-3">Simplex</h1>
            <p class="text-light-emphasis">Accesso area amministrazione.</p>
        </aside>
        <main class="col-12 col-md-8 col-lg-9 d-flex align-items-center justify-content-center p-4">
            <div class="card shadow-sm w-100" style="max-width: 420px;">
                <div class="card-body p-4">
                    <h2 class="h4 mb-3">Login</h2>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>

                    <form method="post" class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="identifier">Nome Utente o Email</label>
                            <input class="form-control" type="text" id="identifier" name="identifier" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" type="password" id="password" name="password" required>
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-primary" type="submit">Accedi</button>
                        </div>
                    </form>

                    <div class="mt-3 d-flex justify-content-between">
                        <a href="recupera-credenziali.php">Recupero credenziali</a>
                        <a href="utenti.php">Gestione Utenti</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php renderFooter(); ?>
