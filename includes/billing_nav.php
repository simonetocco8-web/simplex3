        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <h1 class="h4 text-white mb-4">Simplex</h1>
            <ul class="nav nav-pills flex-column gap-2 mb-3">
                <li class="nav-item"><a <?= navAttributes('bacheca.php') ?> href="bacheca.php"><?= menuIcon('Bacheca') ?> Bacheca</a></li>
                <li class="nav-item"><a <?= navAttributes('offerte.php') ?> href="offerte.php"><?= menuIcon('Offerte') ?> Offerte</a></li>
                <li class="nav-item"><a <?= navAttributes('commesse.php') ?> href="commesse.php"><?= menuIcon('Commesse') ?> Commesse</a></li>

                <li class="nav-item">
                    <a class="nav-link d-flex justify-content-between align-items-center<?= navGroupActive('Anagrafiche') ? ' active' : '' ?>" data-bs-toggle="collapse" href="#menuAnagrafiche" role="button" aria-expanded="<?= navGroupActive('Anagrafiche') ? 'true' : 'false' ?>" aria-controls="menuAnagrafiche">
                        <span><?= menuIcon('Anagrafiche') ?> Anagrafiche</span><span>▾</span>
                    </a>
                    <ul class="nav flex-column ms-3 collapse<?= navGroupActive('Anagrafiche') ? ' show' : '' ?>" id="menuAnagrafiche">
                        <li class="nav-item"><a <?= navAttributes('utenti.php') ?> href="utenti.php"><?= menuIcon('Utenti') ?> Utenti</a></li>
                        <li class="nav-item"><a <?= navAttributes('aziende.php') ?> href="aziende.php"><?= menuIcon('Aziende') ?> Aziende</a></li>
                        <li class="nav-item"><a <?= navAttributes('enti_certificazione.php') ?> href="enti_certificazione.php"><?= menuIcon('Enti di Certificazione') ?> Enti di Certificazione</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link d-flex justify-content-between align-items-center<?= navGroupActive('Amministrazione') ? ' active' : '' ?>" data-bs-toggle="collapse" href="#menuAmministrazione" role="button" aria-expanded="<?= navGroupActive('Amministrazione') ? 'true' : 'false' ?>" aria-controls="menuAmministrazione">
                        <span><?= menuIcon('Amministrazione') ?> Amministrazione</span><span>▾</span>
                    </a>
                    <ul class="nav flex-column ms-3 collapse<?= navGroupActive('Amministrazione') ? ' show' : '' ?>" id="menuAmministrazione">
                        <li class="nav-item"><a <?= navAttributes('amministrazione_produzione.php') ?> href="amministrazione_produzione.php"><?= menuIcon('Produzione') ?> Produzione</a></li>
                        <li class="nav-item"><a <?= navAttributes('proforme.php') ?> href="proforme.php"><?= menuIcon('Pro-Forma') ?> Pro-Forma</a></li>
                        <li class="nav-item"><a <?= navAttributes('fatture.php') ?> href="fatture.php"><?= menuIcon('Fatture') ?> Fatture</a></li>
                        <li class="nav-item"><a <?= navAttributes('pagamenti.php') ?> href="pagamenti.php"><?= menuIcon('Pagamenti') ?> Pagamenti</a></li>
                    </ul>
                </li>

                <li class="nav-item"><a class="nav-link disabled" href="#"><?= menuIcon('Impostazioni') ?> Impostazioni</a></li>
            </ul>
            <?php if ($utenteLoggato): ?>
                <div class="text-white small border-top pt-3">
                    <div>Connesso come:</div>
                    <strong><?= htmlspecialchars($utenteLoggato['nome_completo']) ?></strong><br>
                    <span class="text-light-emphasis"><?= htmlspecialchars($utenteLoggato['nome_utente']) ?></span>
                    <div class="mt-2"><a class="btn btn-outline-light btn-sm" href="logout.php"><?= menuIcon('Logout') ?> Logout</a></div>
                </div>
            <?php endif; ?>
        </nav>
