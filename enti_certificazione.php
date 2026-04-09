<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS enti_certificazione (
    id INT AUTO_INCREMENT PRIMARY KEY,
    denominazione VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $denominazione = trim((string) ($_POST['denominazione'] ?? ''));

        if ($denominazione === '') {
            $errors[] = 'La denominazione è obbligatoria.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO enti_certificazione (denominazione) VALUES (:denominazione)');
            $stmt->execute([':denominazione' => $denominazione]);
            $success = 'Ente di certificazione inserito con successo.';
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $errors[] = 'Ente non valido.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('DELETE FROM enti_certificazione WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $success = 'Ente di certificazione eliminato con successo.';
        }
    }
}

$enti = $pdo->query('SELECT id, denominazione, created_at FROM enti_certificazione ORDER BY denominazione ASC')->fetchAll();
$utenteLoggato = currentUser();

renderHeader('Simplex - Enti di Certificazione');
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2 mb-3">
                <li class="nav-item"><a class="nav-link" href="bacheca.php">Bacheca</a></li>
                <li class="nav-item"><a class="nav-link" href="utenti.php">Utenti</a></li>
                <li class="nav-item"><a class="nav-link" href="aziende.php">Aziende</a></li>
                <li class="nav-item"><a class="nav-link active" href="enti_certificazione.php">Enti di Certificazione</a></li>
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
                    <div class="mt-2"><a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a></div>
                </div>
            <?php endif; ?>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <h2 class="mb-4">Enti di Certificazione</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <div class="card mb-4">
                <div class="card-header">Nuovo ente di certificazione</div>
                <div class="card-body">
                    <form method="post" class="row g-2">
                        <input type="hidden" name="action" value="create">
                        <div class="col-md-8">
                            <label class="form-label">Denominazione</label>
                            <input type="text" name="denominazione" class="form-control" maxlength="255" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Salva ente</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Elenco enti di certificazione</div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Denominazione</th>
                            <th>Creato il</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$enti): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Nessun ente di certificazione presente.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($enti as $ente): ?>
                            <tr>
                                <td><?= htmlspecialchars($ente['denominazione']) ?></td>
                                <td><?= htmlspecialchars((string)($ente['created_at'] ?? '-')) ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline" onsubmit="return confirm('Confermi l\'eliminazione di questo ente?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$ente['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Elimina</button>
                                    </form>
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
