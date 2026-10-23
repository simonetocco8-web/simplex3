(() => {
    document.querySelectorAll('input[name="partita_iva"]').forEach(input => {
        input.autocomplete = 'off';
        const box = document.createElement('div');
        box.className = 'list-group mt-1';
        box.id = 'iva-suggerimenti-' + Math.random().toString(36).slice(2);
        box.setAttribute('aria-label', 'Aziende già censite');
        input.setAttribute('aria-controls', box.id);
        input.insertAdjacentElement('afterend', box);
        let timer, controller, version = 0;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            controller?.abort();
            const current = ++version;
            box.replaceChildren();
            const q = input.value.trim();
            if (!/^\d{2,11}$/.test(q)) return;
            timer = setTimeout(async () => {
                controller = new AbortController();
                try {
                    const response = await fetch('aziende_suggerimenti.php?q=' + encodeURIComponent(q), {signal: controller.signal});
                    if (!response.ok) return;
                    const companies = await response.json();
                    if (current !== version) return;
                    companies.forEach(company => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'list-group-item list-group-item-action';
                        button.textContent = `${company.partita_iva} — ${company.ragione_sociale} (già censita)`;
                        button.addEventListener('click', () => {
                            const select = document.getElementById('azienda_id');
                            const modal = input.closest('#modalNuovaAzienda');
                            if (select && modal) {
                                let option = Array.from(select.options).find(item => item.value === String(company.id));
                                if (!option) { option = new Option(company.ragione_sociale, company.id); select.add(option); }
                                select.value = String(company.id);
                                select.dispatchEvent(new Event('change', {bubbles: true}));
                                bootstrap.Modal.getInstance(modal)?.hide();
                            } else {
                                window.location.href = 'aziende.php?view=' + encodeURIComponent(company.id);
                            }
                            box.replaceChildren();
                        });
                        box.appendChild(button);
                    });
                } catch (error) {
                    if (error.name !== 'AbortError') box.replaceChildren();
                }
            }, 200);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') { event.preventDefault(); box.querySelector('button')?.focus(); }
            if (event.key === 'Escape') { ++version; controller?.abort(); box.replaceChildren(); }
        });
    });
})();
