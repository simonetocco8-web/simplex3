"""Integration checks against the local, disposable onboarding database.

Run: python3 tests/company_roles_smoke.py
Requires simplex3-web/simplex3-db running and an empty utenti table.
Creates temporary users/companies and deletes only those fixtures at the end.
"""
import http.cookiejar
import json
import re
import secrets
import subprocess
import unittest
import urllib.parse
import urllib.request
from html.parser import HTMLParser


class Form(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.selects = {}
        self.current = None
        self.feed(body)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'select':
            self.current = attrs.get('name')
            self.selects[self.current] = {}
        elif tag == 'option' and self.current:
            self.selects[self.current][attrs.get('value', '')] = 'selected' in attrs

    def handle_endtag(self, tag):
        if tag == 'select':
            self.current = None


class CompanyRolesSmoke(unittest.TestCase):
    @classmethod
    def sql(cls, query):
        return subprocess.check_output(
            ['docker', 'exec', 'simplex3-db', 'mariadb', '-uroot', '-N', '-B', 'simplex', '-e', query],
            text=True,
        ).strip()

    @classmethod
    def request(cls, page, data=None):
        encoded = None if data is None else urllib.parse.urlencode(data, doseq=True).encode()
        with cls.client.open('http://127.0.0.1:8000/' + page, encoded, timeout=20) as response:
            body = response.read().decode()
            if response.status != 200 or any(error in body for error in (
                'Fatal error', 'Warning:', 'SQLSTATE[', 'Errore connessione database',
            )):
                raise AssertionError('HTTP/PHP failure: ' + page)
            return body

    @classmethod
    def create_user(cls, suffix, roles):
        name = cls.prefix + suffix
        cls.request('utenti.php', {
            'azione': 'crea', 'nome_utente': name, 'nome': suffix, 'cognome': cls.prefix,
            'email': name + '@example.test', 'password': cls.password,
            'ruoli[]': roles, 'attivo': '1',
        })
        user_id = cls.sql(f"SELECT id FROM utenti WHERE nome_utente='{name}'")
        assert user_id, 'User creation failed: ' + suffix
        cls.users[suffix] = user_id

    @classmethod
    def setUpClass(cls):
        cls.client = urllib.request.build_opener(
            urllib.request.ProxyHandler({}),
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
        )
        if cls.sql('SELECT COUNT(*) FROM utenti') != '0':
            raise unittest.SkipTest('Use an empty local development database; existing users are preserved.')
        cls.prefix = 'test_' + secrets.token_hex(5) + '_'
        cls.password = secrets.token_urlsafe(24)
        cls.users = {}
        cls.addClassCleanup(cls.cleanup)
        cls.create_user('admin', ['Amministratore'])
        cls.request('login.php', {'identifier': cls.prefix + 'admin', 'password': cls.password})
        for suffix, roles in [
            ('area', ['Responsabile di Area']), ('consulente', ['Consulente']),
            ('promotore', ['Promotore']), ('tecnico', ['Tecnico Esperto']),
            ('misto', ['Tecnico Esperto', 'Promotore', 'Consulente']),
        ]:
            cls.create_user(suffix, roles)
        cls.request('aziende.php')

    @classmethod
    def cleanup(cls):
        company_ids = f"SELECT id FROM aziende WHERE ragione_sociale LIKE '{cls.prefix}%'"
        offer_ids = f"SELECT id FROM offerte WHERE azienda_id IN ({company_ids})"
        cls.sql(f"DELETE FROM commesse WHERE offerta_id IN ({offer_ids})")
        cls.sql(f"DELETE FROM offerte WHERE azienda_id IN ({company_ids})")
        cls.sql(f"DELETE FROM aziende WHERE ragione_sociale LIKE '{cls.prefix}%'")
        cls.sql(f"DELETE FROM utenti WHERE nome_utente LIKE '{cls.prefix}%'")

    def company_data(self):
        return {
            'azione': 'save', 'id': '0', 'partita_iva': str(secrets.randbelow(10**11)).zfill(11),
            'ragione_sociale': self.prefix + secrets.token_hex(3), 'tipologia_azienda[]': ['Cliente'],
            'organico_medio': '11-25', 'fatturato': '<500K', 'categoria': 'Servizi',
            'rco_utente_id': self.users['consulente'], 'promotore_utente_id': self.users['promotore'],
            'sede_tipo[]': ['Legale'], 'sede_via[]': ['Via Roma'], 'sede_numero_civico[]': ['1'],
            'sede_cap[]': ['00100'], 'sede_regione[]': ['Lazio'], 'sede_provincia[]': ['RM'],
            'sede_comune[]': ['Roma'],
        }

    def save_company(self, data, page='aziende.php?action=new'):
        body = self.request(page, data)
        self.assertIn('correttamente.', body)
        row = self.sql("SELECT JSON_OBJECT('id',id,'organico_medio',organico_medio,'fatturato',fatturato,"
                       "'categoria',categoria,'rco',rco_utente_id,'promotore',promotore_utente_id) "
                       f"FROM aziende WHERE partita_iva='{data['partita_iva']}'")
        self.assertTrue(row, 'Company was not saved')
        return json.loads(row)

    def test_new_form(self):
        body = self.request('aziende.php?action=new')
        form = Form(body).selects
        for label in ('Numero di Dipendenti', 'Fatturato', 'Settore di Attività', 'RCO', 'Promotore'):
            self.assertIn(label, body)
        self.assertEqual(set(form['organico_medio']), {'', '0-10', '11-25', '26-50', '51-250', '>250', 'Sconosciuto'})
        self.assertEqual(set(form['fatturato']), {'', '<500K', '500-1000K', '1000-5000', '>5000'})
        self.assertEqual(set(form['categoria']), {'', 'Agroalimentare', 'Commercio', 'Servizi', 'Edilizia',
                                                'Formazione', 'Manifatturiero', 'Sanità', 'Trasporti', 'Turismo', 'P.A'})
        self.assertNotIn('Filtri Aziende', body)
        self.assertNotIn('Elenco Aziende', body)
        listing = self.request('aziende.php')
        self.assertIn('Filtri Aziende', listing)
        self.assertIn('Elenco Aziende', listing)

    def test_role_selection(self):
        form = Form(self.request('aziende.php?action=new')).selects
        self.assertEqual(set(form['rco_utente_id']), {''} | {self.users[k] for k in ('admin', 'area', 'consulente', 'misto')})
        self.assertEqual(set(form['promotore_utente_id']), {'', self.users['promotore'], self.users['misto']})
        roles = self.sql(f"SELECT ruolo FROM utenti_ruoli WHERE utente_id={self.users['misto']}")
        self.assertEqual(set(roles.splitlines()), {'Tecnico Esperto', 'Promotore', 'Consulente'})

    def test_create_edit_and_filter(self):
        employees = ['0-10', '11-25', '26-50', '51-250', '>250', 'Sconosciuto']
        revenue = ['<500K', '500-1000K', '1000-5000', '>5000']
        sectors = ['Agroalimentare', 'Commercio', 'Servizi', 'Edilizia', 'Formazione',
                   'Manifatturiero', 'Sanità', 'Trasporti', 'Turismo', 'P.A']
        for i, sector in enumerate(sectors):
            with self.subTest(sector=sector):
                data = self.company_data()
                data.update(organico_medio=employees[i % len(employees)], fatturato=revenue[i % len(revenue)], categoria=sector)
                row = self.save_company(data)
                for field in ('organico_medio', 'fatturato', 'categoria'):
                    self.assertEqual(row[field], data[field])
                self.assertEqual(str(row['rco']), self.users['consulente'])
                self.assertEqual(str(row['promotore']), self.users['promotore'])
                edit = Form(self.request('aziende.php?edit=' + str(row['id']))).selects
                for field in ('organico_medio', 'fatturato', 'categoria', 'rco_utente_id', 'promotore_utente_id'):
                    self.assertTrue(edit[field][data[field]], field)
                data.update(id=str(row['id']), rco_utente_id=self.users['misto'], promotore_utente_id=self.users['misto'])
                row = self.save_company(data, 'aziende.php?edit=' + str(row['id']))
                self.assertEqual(str(row['rco']), self.users['misto'])
                self.assertEqual(str(row['promotore']), self.users['misto'])
                listing = self.request('aziende.php?' + urllib.parse.urlencode({
                    'f_promotore_utente_id': self.users['misto'], 'f_partita_iva': data['partita_iva'],
                }))
                self.assertIn(data['ragione_sociale'], listing)

    def test_reject_invalid_values(self):
        for field, value, message in [
            ('rco_utente_id', self.users['promotore'], 'RCO non valido'),
            ('promotore_utente_id', self.users['admin'], 'Promotore non valido'),
            ('rco_utente_id', self.users['tecnico'], 'RCO non valido'),
            ('promotore_utente_id', self.users['tecnico'], 'Promotore non valido'),
            ('organico_medio', '10-50', 'Numero di Dipendenti non valido'),
            ('fatturato', '< 2 Mln', 'Fatturato non valido'),
            ('categoria', 'Inventato', 'Settore di Attività non valido'),
        ]:
            with self.subTest(field=field, value=value):
                data = self.company_data()
                data[field] = value
                body = self.request('aziende.php?action=new', data)
                self.assertIn(message, body)
                self.assertNotIn('Filtri Aziende', body)
                self.assertNotIn('Elenco Aziende', body)
                self.assertEqual(self.sql(f"SELECT COUNT(*) FROM aziende WHERE partita_iva='{data['partita_iva']}'"), '0')

    def test_legacy_values_are_preserved(self):
        data = self.company_data()
        row = self.save_company(data)
        company_id = row['id']
        self.sql(f"UPDATE aziende SET organico_medio='10-50',fatturato='< 2 Mln',categoria='Edile',"
                 f"promotore_azienda_id={company_id} WHERE id={company_id}")
        data.update(id=str(company_id), organico_medio='10-50', fatturato='< 2 Mln', categoria='Edile')
        form = Form(self.request('aziende.php?edit=' + str(company_id))).selects
        for field in ('organico_medio', 'fatturato', 'categoria'):
            self.assertTrue(form[field][data[field]])
        updated = self.save_company(data, 'aziende.php?edit=' + str(company_id))
        for field in ('organico_medio', 'fatturato', 'categoria'):
            self.assertEqual(updated[field], data[field])
        self.assertEqual(self.sql(f'SELECT promotore_azienda_id FROM aziende WHERE id={company_id}'), str(company_id))

    def test_menu_icons(self):
        for page in ('utenti.php', 'aziende.php', 'bacheca.php', 'offerte.php', 'commesse.php',
                     'enti_certificazione.php', 'fatture.php', 'pagamenti.php', 'amministrazione_produzione.php'):
            with self.subTest(page=page):
                body = self.request(page)
                nav = re.search(r'<nav\b.*?</nav>', body, re.S).group()
                links = re.findall(r'<a\b.*?</a>', nav, re.S)
                self.assertEqual(len(links), 13)
                for link in links:
                    self.assertIn('<svg class="menu-icon"', link)
                    self.assertIn('aria-hidden="true"', link)


    def quick_company(self, **overrides):
        data = self.company_data()
        data.update(azione='ajax_create_azienda')
        data.update(overrides)
        result = json.loads(self.request('offerte.php?action=new', data))
        return data, result

    def offer_data(self, company_id):
        return {
            'azione': 'save', 'id': '0', 'azienda_id': str(company_id),
            'servizio': 'SICUREZZA', 'dettaglio_servizio': 'DVR (D. Lgs. 81/2008)',
            'stato': 'In Elaborazione', 'rco_utente_id': self.users['admin'],
            'data_offerta': '2026-10-23', 'validita_giorni': '30', 'data_scadenza': '',
            'modalita_pagamento': 'Bonifico Bancario f.m.v.f', 'sconto_percentuale': '0',
            'momento_data_momento[]': ['2026-11-01'],
            'momento_tipologia[]': ['Verifica personalizzata'],
            'momento_valore_giornaliero_uomo[]': ['100'], 'momento_costo_totale[]': ['200'],
        }

    def test_offer_company_popup(self):
        body = self.request('offerte.php?action=new')
        popup = body[body.index('id="modalNuovaAzienda"'):]
        form = Form(popup).selects
        self.assertIn('Settore di Attività', popup)
        self.assertIn('RCO', popup)
        self.assertIn('Edilizia', form['categoria'])
        self.assertEqual(set(form['rco_utente_id']), {''} | {self.users[k] for k in ('admin', 'area', 'consulente', 'misto')})
        data, result = self.quick_company()
        self.assertTrue(result['ok'], result)
        stored = json.loads(self.sql("SELECT JSON_OBJECT('rco',rco_utente_id,'categoria',categoria) "
                                     f"FROM aziende WHERE id={result['id']}"))
        self.assertEqual(str(stored['rco']), data['rco_utente_id'])
        self.assertEqual(stored['categoria'], 'Servizi')
        _, invalid = self.quick_company(rco_utente_id=self.users['tecnico'])
        self.assertFalse(invalid['ok'])
        self.assertIn('RCO non valido.', invalid['errors'])

    def test_duplicate_identifiers_between_flows(self):
        fiscal = str(secrets.randbelow(10**11)).zfill(11)
        data = self.company_data()
        data.update(partita_iva='', codice_fiscale=fiscal)
        body = self.request('aziende.php?action=new', data)
        self.assertIn('Azienda creata correttamente.', body)
        _, duplicate = self.quick_company(partita_iva='', codice_fiscale=fiscal)
        self.assertFalse(duplicate['ok'])
        self.assertIn('già censita a sistema', duplicate['errors'][0])
        fiscal2 = 'ABCDEF' + secrets.token_hex(5).upper()
        quick, created = self.quick_company(partita_iva='', codice_fiscale=fiscal2.lower())
        self.assertTrue(created['ok'], created)
        self.assertEqual(self.sql(f"SELECT codice_fiscale FROM aziende WHERE id={created['id']}"), fiscal2)
        other = self.company_data()
        other.update(partita_iva='', codice_fiscale=fiscal2)
        self.assertIn('già censita a sistema', self.request('aziende.php?action=new', other))
        quick, created = self.quick_company()
        self.assertTrue(created['ok'], created)
        other = self.company_data()
        other['partita_iva'] = quick['partita_iva']
        self.assertIn('già censita a sistema', self.request('aziende.php?action=new', other))
        _, duplicate = self.quick_company(partita_iva=quick['partita_iva'])
        self.assertFalse(duplicate['ok'])
        self.assertIn('già censita a sistema', duplicate['errors'][0])
        _, missing = self.quick_company(partita_iva='', codice_fiscale='')
        self.assertFalse(missing['ok'])
        self.assertIn('Codice Fiscale', missing['errors'][0])

    def test_offer_dates_typology_and_actions(self):
        body = self.request('offerte.php?action=new')
        self.assertNotIn('Filtri Offerte', body)
        self.assertNotIn('Elenco Offerte', body)
        self.assertNotIn('name="segnalato_da_utente_id"', body)
        self.assertIn('Promotore/Segnalatore', body)
        for name in ('data_offerta', 'data_scadenza', 'momento_data_momento[]'):
            tag = re.search(r'<input[^>]*name="' + re.escape(name) + r'"[^>]*>', body).group()
            self.assertIn('type="date"', tag)
        self.assertIn('list="tipologie-momento"', body)
        # Check JS syntax, dynamically added row markup, and expiry calculation across DST.
        script = re.findall(r'<script>(.*?)</script>', body, re.S)[-1]
        subprocess.run(['node', '-e', 'new Function(' + json.dumps(script) + ')'], check=True)
        dates = script[script.index('function parseDate('):script.index('if(dataOff&&valIn&&scadIn)')]
        subprocess.run(['node', '-e', dates + "const assert=require('node:assert/strict');"
                        "assert.equal(addDays('2026-10-23',30),'2026-11-22');"
                        "assert.equal(daysBetween('2026-10-23','2026-11-22'),30);"
                        "assert.equal(addDays('2028-02-28',1),'2028-02-29');"], check=True)
        row_literal = re.search(r"tr.innerHTML=('.*?');", script).group(1)
        dynamic = subprocess.check_output(['node', '-e', 'console.log(' + row_literal + ')'], text=True)
        self.assertIn('type="date"', dynamic)
        self.assertIn('list="tipologie-momento"', dynamic)
        data, company = self.quick_company()
        self.assertTrue(company['ok'], company)
        offer = self.offer_data(company['id'])
        response = self.request('offerte.php?action=new', offer)
        self.assertIn('Offerta creata correttamente', response)
        row = json.loads(self.sql("SELECT JSON_OBJECT('id',id,'date',data_offerta,'expiry',data_scadenza) "
                                  f"FROM offerte WHERE azienda_id={company['id']}"))
        self.assertEqual(row['date'], '2026-10-23')
        self.assertEqual(row['expiry'], '2026-11-22')
        self.assertEqual(self.sql(f"SELECT tipologia FROM offerta_momenti_lavorazione WHERE offerta_id={row['id']}"),
                         'Verifica personalizzata')
        edit = self.request('offerte.php?edit=' + str(row['id']))
        self.assertIn('value="2026-10-23"', edit)
        self.assertIn('value="Verifica personalizzata"', edit)
        listing = self.request('offerte.php')
        self.assertIn('Filtri Offerte', listing)
        for label in ('Modifica', 'Lavorazioni', 'Elimina'):
            self.assertRegex(listing, 'aria-label="' + label + '"><svg')
        # Awarding an existing offer must copy the custom typology into its job.
        offer.update(id=str(row['id']), stato='Aggiudicata')
        self.assertIn('Offerta aggiornata correttamente', self.request('offerte.php?edit=' + str(row['id']), offer))
        self.assertEqual(self.sql("SELECT m.tipologia FROM commessa_momenti_lavorazione m "
                                  "JOIN commesse c ON c.id=m.commessa_id "
                                  f"WHERE c.offerta_id={row['id']}"), 'Verifica personalizzata')

    def test_offer_rejects_invalid_date_or_typology(self):
        _, company = self.quick_company()
        for field, value, message in [
            ('data_offerta', '2026-02-30', 'Data Offerta obbligatoria e non valida'),
            ('momento_data_momento[]', ['2026-02-30'], 'Data del momento di lavorazione non valida'),
            ('momento_tipologia[]', ['x' * 121], 'Tipologia obbligatoria e massimo 120 caratteri'),
        ]:
            with self.subTest(field=field):
                data = self.offer_data(company['id'])
                data[field] = value
                body = self.request('offerte.php?action=new', data)
                self.assertIn(message, body)
                self.assertNotIn('Elenco Offerte', body)
                self.assertEqual(self.sql(f"SELECT COUNT(*) FROM offerte WHERE azienda_id={company['id']}"), '0')

    def login_as(self, suffix):
        self.request('logout.php')
        self.request('login.php', {'identifier': self.prefix + suffix, 'password': self.password})

    def test_offer_status_and_award_without_area(self):
        form = self.request('offerte.php?action=new')
        self.assertIn('value="Approvata e Inviata"', form)
        self.assertNotIn('value="Inviata"', form)
        self.assertNotIn('name="responsabile_area_utente_id"', form)
        self.assertNotIn('toggleResponsabileArea', form)
        _, company = self.quick_company()
        offer = self.offer_data(company['id'])
        offer['stato'] = 'Approvata e Inviata'
        self.assertIn('Offerta creata correttamente', self.request('offerte.php?action=new', offer))
        offer_id = self.sql(f"SELECT id FROM offerte WHERE azienda_id={company['id']}")
        self.assertEqual(self.sql(f'SELECT stato FROM offerte WHERE id={offer_id}'), 'Approvata e Inviata')
        self.sql(f"UPDATE offerte SET stato='Inviata' WHERE id={offer_id}")
        edit = self.request('offerte.php?edit=' + offer_id)
        self.assertIn('value="Approvata e Inviata" selected', edit)
        self.assertEqual(self.sql(f'SELECT stato FROM offerte WHERE id={offer_id}'), 'Approvata e Inviata')
        offer.update(id=offer_id, stato='Aggiudicata')
        self.assertIn('Offerta aggiornata correttamente', self.request('offerte.php?edit=' + offer_id, offer))
        self.assertEqual(self.sql(f'SELECT responsabile_area_utente_id IS NULL FROM offerte WHERE id={offer_id}'), '1')
        self.assertNotIn('name="responsabile_area_utente_id"', self.request('offerte.php?edit=' + offer_id))

    def test_dashboard_assignment_total_and_notes(self):
        _, company = self.quick_company()
        offer = self.offer_data(company['id'])
        offer.update(stato='Aggiudicata', sconto_percentuale='10', note='Nota <b>riservata</b> & dettagli')
        offer['momento_data_momento[]'] = ['2026-11-01', '2026-11-02']
        offer['momento_tipologia[]'] = ['Apertura', 'Verifica']
        offer['momento_valore_giornaliero_uomo[]'] = ['100', '50']
        offer['momento_costo_totale[]'] = ['200', '50']
        self.assertIn('Offerta creata correttamente', self.request('offerte.php?action=new', offer))
        offer_id = self.sql(f"SELECT id FROM offerte WHERE azienda_id={company['id']}")
        job_id = self.sql(f'SELECT id FROM commesse WHERE offerta_id={offer_id}')
        try:
            for role in ('admin', 'area'):
                self.login_as(role)
                body = self.request('bacheca.php')
                self.assertNotIn('Tutte le commesse', body)
                self.assertIn('id="modalAssegnaConsulente"', body)
                self.assertIn('data-commessa-id="' + job_id + '"', body)
            table = body.split('<div class="card-header">Commesse da Assegnare</div>', 1)[1].split('</table>', 1)[0]
            self.assertIn('Importo', table)
            self.assertIn('€ 225,00', table)
            self.assertIn('data-note="Nota &lt;b&gt;riservata&lt;/b&gt; &amp; dettagli"', table)
            self.assertIn('aria-label="Visualizza note offerta"', table)
            form = Form(body).selects
            self.assertEqual(set(form['consulente_utente_id']), {'', self.users['consulente'], self.users['misto']})
            # Bootstrap's modal event must populate the hidden job ID and render notes as text.
            script = re.findall(r'<script>(.*?)</script>', body, re.S)[-1]
            node_test = r"""
const assert=require('node:assert/strict');
const elements={};
const document={getElementById(id){return elements[id] ||= {events:{},value:'',textContent:'',addEventListener(name,fn){this.events[name]=fn;}};}};
new Function('document', SCRIPT)(document);
elements.modalAssegnaConsulente.events['show.bs.modal']({relatedTarget:{dataset:{commessaId:'123',commessaProtocollo:'42/2026'}}});
assert.equal(elements.commessaIdModal.value,'123');
assert.equal(elements.protocolloCommessaModal.textContent,'42/2026');
assert.equal(elements.consulenteIncaricatoModal.value,'');
elements.modalNoteOfferta.events['show.bs.modal']({relatedTarget:{dataset:{note:'<b>Nota</b>'}}});
assert.equal(elements.testoNoteOfferta.textContent,'<b>Nota</b>');
""".replace('SCRIPT', json.dumps(script))
            subprocess.run(['node', '-e', node_test], check=True)
            token = re.search(r'name="csrf_token" value="([^"]+)"', body).group(1)
            payload = {'azione': 'assegna_consulente', 'commessa_id': job_id,
                       'consulente_utente_id': self.users['consulente'], 'csrf_token': token}
            invalid = dict(payload, consulente_utente_id=self.users['tecnico'])
            self.assertIn('Seleziona un consulente valido.', self.request('bacheca.php', invalid))
            import urllib.error
            with self.assertRaises(urllib.error.HTTPError) as error:
                self.request('bacheca.php', dict(payload, csrf_token='invalid'))
            self.assertEqual(error.exception.code, 403)
            self.sql(f"UPDATE utenti SET attivo=0 WHERE id={self.users['misto']}")
            try:
                choices = Form(self.request('bacheca.php')).selects['consulente_utente_id']
                self.assertNotIn(self.users['misto'], choices)
                self.assertIn('Seleziona un consulente valido.', self.request('bacheca.php', dict(payload, consulente_utente_id=self.users['misto'])))
            finally:
                self.sql(f"UPDATE utenti SET attivo=1 WHERE id={self.users['misto']}")
            assigned = self.request('bacheca.php', payload)
            self.assertIn('Consulente incaricato aggiornato correttamente.', assigned)
            self.assertNotIn('data-commessa-id="' + job_id + '"', assigned)
            self.assertEqual(self.sql(f'SELECT consulente_utente_id FROM commesse WHERE id={job_id}'), self.users['consulente'])
            expected_name = 'consulente ' + self.prefix
            self.assertEqual(self.sql(f'SELECT consulente_nome FROM commesse WHERE id={job_id}'), expected_name)
            self.assertEqual(self.sql(f'SELECT consulente_incaricato FROM offerte WHERE id={offer_id}'), expected_name)
            # Repeated submissions cannot overwrite an existing assignment.
            self.assertIn('Commessa aggiudicata non trovata.', self.request('bacheca.php', dict(payload, consulente_utente_id=self.users['misto'])))
            self.assertEqual(self.sql(f'SELECT consulente_utente_id FROM commesse WHERE id={job_id}'), self.users['consulente'])
            self.login_as('consulente')
            own_dashboard = self.request('bacheca.php')
            self.assertIn('Commesse assegnate al tuo account', own_dashboard)
            self.assertIn(company['ragione_sociale'], own_dashboard)
            self.assertNotIn('Commesse da Assegnare', own_dashboard)
            with self.assertRaises(urllib.error.HTTPError) as error:
                self.request('bacheca.php', payload)
            self.assertEqual(error.exception.code, 403)
        finally:
            self.login_as('admin')

    def test_dashboard_no_note_icon_for_empty_notes(self):
        _, company = self.quick_company()
        offer = self.offer_data(company['id'])
        offer['stato'] = 'Aggiudicata'
        self.request('offerte.php?action=new', offer)
        offer_id = self.sql(f"SELECT id FROM offerte WHERE azienda_id={company['id']}")
        job_id = self.sql(f'SELECT id FROM commesse WHERE offerta_id={offer_id}')
        body = self.request('bacheca.php')
        table = body.split('<div class="card-header">Commesse da Assegnare</div>', 1)[1].split('</table>', 1)[0]
        row = next(row for row in re.findall(r'<tr>.*?</tr>', table, re.S) if 'data-commessa-id="' + job_id + '"' in row)
        self.assertNotIn('js-note-offerta', row)
        self.assertIn('€ 200,00', row)


if __name__ == '__main__':
    unittest.main(verbosity=2)
