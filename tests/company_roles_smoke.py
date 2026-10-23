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
            ['docker', 'exec', 'simplex3-db', 'mariadb', '-uroot', '-N', '-B', '--raw', 'simplex', '-e', query],
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
        cls.sql(f"DELETE FROM fatture WHERE commessa_id IN (SELECT id FROM commesse WHERE offerta_id IN ({offer_ids}))")
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
                self.assertEqual(len(links), 14)
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
            self.assertIn('type="text"', tag)
            self.assertIn('js-offer-date', tag)
        self.assertIn('list="tipologie-momento"', body)
        # Check JS syntax, dynamically added row markup, and expiry calculation across DST.
        script = re.findall(r'<script>(.*?)</script>', body, re.S)[-1]
        subprocess.run(['node', '-e', 'new Function(' + json.dumps(script) + ')'], check=True)
        dates = script[script.index('function parseDate('):script.index('if(dataOff&&valIn&&scadIn)')]
        subprocess.run(['node', '-e', dates + "const assert=require('node:assert/strict');"
                        "assert.equal(addDays('23/10/2026',30),'22/11/2026');"
                        "assert.equal(daysBetween('2026-10-23','2026-11-22'),30);"
                        "assert.equal(addDays('28/02/2028',1),'29/02/2028');"], check=True)
        row_literal = re.search(r"tr.innerHTML=('.*?');", script).group(1)
        dynamic = subprocess.check_output(['node', '-e', 'console.log(' + row_literal + ')'], text=True)
        self.assertIn('type="text"', dynamic)
        self.assertIn('js-offer-date', dynamic)
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
        self.assertIn('value="23/10/2026"', edit)
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

    def create_consuntivo_job(self):
        _, company = self.quick_company()
        offer = self.offer_data(company['id'])
        offer['momento_data_momento[]'] = ['2026-11-01', '2026-11-02']
        offer['momento_tipologia[]'] = ['Apertura', 'Verifica personalizzata']
        offer['momento_valore_giornaliero_uomo[]'] = ['100', '50']
        offer['momento_costo_totale[]'] = ['200', '50']
        self.assertIn('Offerta creata correttamente', self.request('offerte.php?action=new', offer))
        offer_id = self.sql(f"SELECT id FROM offerte WHERE azienda_id={company['id']}")
        job_id = self.sql(f'SELECT id FROM commesse WHERE offerta_id={offer_id}')
        page = self.request('commesse.php?edit=' + job_id)
        rows = json.loads(self.sql("SELECT JSON_ARRAYAGG(JSON_OBJECT('id',id,'data_momento',data_momento,"
                                   "'tipologia',tipologia,'valore_giornaliero_uomo',valore_giornaliero_uomo,"
                                   "'costo_totale',costo_totale,'ore',ore,'giorni',giorni,"
                                   "'numero_incontri',numero_incontri,'ore_studio',ore_studio,'data_prevista',data_prevista)) "
                                   f'FROM commessa_momenti_lavorazione WHERE commessa_id={job_id}'))
        rows.sort(key=lambda row: row['id'])
        token = re.search(r'name="csrf_token" value="([^"]+)"', page).group(1)
        return job_id, offer_id, rows, token, page

    def consuntivo_payload(self, job_id, token, rows):
        data = {'azione': 'save_consuntivi', 'id': job_id, 'csrf_token': token}
        for row in rows:
            for field, value in row.items():
                if field != 'id':
                    data[f"consuntivi[{row['id']}][{field}]"] = '' if value is None else str(value)
        return data

    def test_consuntivo_inheritance_and_idempotency(self):
        job_id, _, rows, _, page = self.create_consuntivo_job()
        self.assertIn('Momenti a Consuntivo', page)
        self.assertIn('preventivo − consuntivo', page)
        self.assertIn('id="consuntivo-differenza" class="text-body-secondary">€ 0,00', page)
        for row in rows:
            match = self.sql("SELECT COUNT(*) FROM commessa_momenti_consuntivo a "
                             "JOIN commessa_momenti_lavorazione p ON p.id=a.momento_id "
                             f"WHERE p.id={row['id']} AND a.costo_totale=p.costo_totale "
                             "AND a.valore_giornaliero_uomo=p.valore_giornaliero_uomo AND a.giorni=p.giorni AND a.ore=p.ore")
            self.assertEqual(match, '1')
        self.request('commesse.php?edit=' + job_id)
        self.request('commesse.php?edit=' + job_id)
        self.assertEqual(self.sql('SELECT COUNT(*) FROM commessa_momenti_consuntivo a '
                                  'JOIN commessa_momenti_lavorazione p ON p.id=a.momento_id '
                                  f'WHERE p.commessa_id={job_id}'), '2')
        self.assertNotIn('Offerte ordinate per data di scadenza crescente', self.request('bacheca.php'))

    def test_consuntivo_save_differences_and_preserve_forecast(self):
        job_id, offer_id, rows, token, _ = self.create_consuntivo_job()
        forecast = self.sql(f'SELECT * FROM commessa_momenti_lavorazione WHERE commessa_id={job_id} ORDER BY id')
        offer_forecast = self.sql(f'SELECT * FROM offerta_momenti_lavorazione WHERE offerta_id={offer_id} ORDER BY id')
        data = self.consuntivo_payload(job_id, token, rows)
        first, second = rows[0]['id'], rows[1]['id']
        data.update({f'consuntivi[{first}][costo_totale]': '150',
                     f'consuntivi[{second}][costo_totale]': '80',
                     f'consuntivi[{first}][valore_giornaliero_uomo]': '12,50',
                     f'consuntivi[{first}][ore]': '1.25', f'consuntivi[{first}][giorni]': '3.5',
                     f'consuntivi[{first}][numero_incontri]': '4', f'consuntivi[{first}][ore_studio]': '02:30',
                     f'consuntivi[{first}][data_prevista]': '2026-12-01', f'consuntivi[{first}][tipologia]': 'Attività effettiva'})
        page = self.request('commesse.php?edit=' + job_id, data)
        self.assertIn('Momenti a consuntivo salvati correttamente.', page)
        self.assertIn('id="consuntivo-totale">€ 230,00', page)
        self.assertIn('id="consuntivo-differenza" class="text-success">€ +20,00', page)
        self.assertRegex(page, r'data-delta="costo_totale" data-planned="200.00" class="text-success">\+50,00')
        self.assertRegex(page, r'data-delta="costo_totale" data-planned="50.00" class="text-danger">-30,00')
        saved = json.loads(self.sql("SELECT JSON_OBJECT('valore',valore_giornaliero_uomo,'ore',ore,'giorni',giorni,"
                                    "'incontri',numero_incontri,'studio',ore_studio,'data',data_prevista) "
                                    f'FROM commessa_momenti_consuntivo WHERE momento_id={first}'))
        self.assertEqual(float(saved['valore']), 12.5)
        self.assertEqual(float(saved['ore']), 1.25)
        self.assertEqual(float(saved['giorni']), 3.5)
        self.assertEqual(saved['incontri'], 4)
        self.assertEqual(saved['studio'], '02:30')
        self.assertEqual(saved['data'], '2026-12-01')
        reload = self.request('commesse.php?edit=' + job_id)
        self.assertIn('value="Attività effettiva"', reload)
        self.assertIn('id="consuntivo-totale">€ 230,00', reload)
        data[f'consuntivi[{first}][costo_totale]'] = '240.01'
        page = self.request('commesse.php?edit=' + job_id, data)
        self.assertIn('id="consuntivo-differenza" class="text-danger">€ -70,01', page)
        data[f'consuntivi[{first}][costo_totale]'] = '0'
        data[f'consuntivi[{second}][costo_totale]'] = '0'
        page = self.request('commesse.php?edit=' + job_id, data)
        self.assertIn('id="consuntivo-totale">€ 0,00', page)
        self.assertIn('id="consuntivo-differenza" class="text-success">€ +250,00', page)
        self.assertEqual(self.sql(f'SELECT * FROM commessa_momenti_lavorazione WHERE commessa_id={job_id} ORDER BY id'), forecast)
        self.assertEqual(self.sql(f'SELECT * FROM offerta_momenti_lavorazione WHERE offerta_id={offer_id} ORDER BY id'), offer_forecast)

    def test_consuntivo_validation_and_ownership(self):
        import urllib.error
        job_id, _, rows, token, _ = self.create_consuntivo_job()
        first = rows[0]['id']
        baseline = self.sql(f'SELECT costo_totale FROM commessa_momenti_consuntivo WHERE momento_id={first}')
        for field, value in [('costo_totale', '-1'), ('ore', '1.234'), ('giorni', '1000000'),
                             ('numero_incontri', '1.5'), ('data_momento', '2026-02-30'), ('ore_studio', '02:99')]:
            with self.subTest(field=field):
                data = self.consuntivo_payload(job_id, token, rows)
                data[f'consuntivi[{first}][{field}]'] = value
                # A valid change in the other row must not be saved if any row is invalid.
                data[f"consuntivi[{rows[1]['id']}][costo_totale]"] = '999'
                page = self.request('commesse.php?edit=' + job_id, data)
                self.assertNotIn('Momenti a consuntivo salvati correttamente.', page)
                self.assertIn('alert alert-danger', page)
                self.assertEqual(self.sql(f'SELECT costo_totale FROM commessa_momenti_consuntivo WHERE momento_id={first}'), baseline)
                self.assertEqual(float(self.sql(f"SELECT costo_totale FROM commessa_momenti_consuntivo WHERE momento_id={rows[1]['id']}")), 50)
        other_id, _, other_rows, _, _ = self.create_consuntivo_job()
        data = self.consuntivo_payload(job_id, token, other_rows)
        self.assertIn('non appartiene a questa commessa', self.request('commesse.php?edit=' + job_id, data))
        data = self.consuntivo_payload(job_id, token, rows)
        with self.assertRaises(urllib.error.HTTPError) as error:
            self.request('commesse.php?edit=' + job_id, dict(data, csrf_token='bad'))
        self.assertEqual(error.exception.code, 403)

    def test_consuntivo_live_calculation(self):
        _, _, _, _, page = self.create_consuntivo_job()
        script = next(code for code in re.findall(r'<script>(.*?)</script>', page, re.S) if "document.getElementById('form-consuntivi')" in code)
        node_test = r"""
const assert=require('node:assert/strict');
const fields={valore_giornaliero_uomo:{value:'20'},costo_totale:{value:'40'},ore:{value:'4'},giorni:{value:'2'},numero_incontri:{value:'0'}};
const outputs=Object.keys(fields).map(field=>({dataset:{delta:field,planned:field==='costo_totale'?'100':'0'}}));
const row={querySelectorAll(){return outputs;},querySelector(selector){return fields[/data-field="(.*?)"/.exec(selector)[1]];}};
const form={events:{},querySelectorAll(){return [row];},addEventListener(name,fn){this.events[name]=fn;}};
const elements={'form-consuntivi':form,'consuntivo-preventivo':{dataset:{cents:'10000'}},'consuntivo-totale':{},'consuntivo-differenza':{}};
new Function('document', SCRIPT)({getElementById(id){return elements[id];}});
assert.equal(elements['consuntivo-differenza'].className,'text-success');
assert.equal(elements['consuntivo-differenza'].textContent,'€ +60,00');
form.events.click({target:{closest(){return {closest(){return row;}};}}});
assert.equal(fields.costo_totale.value,'50.00');
assert.equal(elements['consuntivo-differenza'].textContent,'€ +50,00');
fields.costo_totale.value='120.01';form.events.input();
assert.equal(elements['consuntivo-differenza'].className,'text-danger');
assert.equal(elements['consuntivo-differenza'].textContent,'€ -20,01');
fields.costo_totale.value='100';form.events.input();
assert.equal(elements['consuntivo-differenza'].className,'text-body-secondary');
""".replace('SCRIPT', json.dumps(script))
        subprocess.run(['node', '-e', node_test], check=True)

    def complete_proforma(self):
        job_id, _, rows, token, page = self.create_consuntivo_job()
        self.assertNotIn('Filtro ricerca commesse', page)
        self.assertNotIn('Elenco commesse generate', page)
        data = {'azione': 'complete_commessa', 'id': job_id, 'csrf_token': token}
        response = self.request('commesse.php?edit=' + job_id, data)
        self.assertIn('Commessa completata. Pro-Forma disponibile.', response)
        row = json.loads(self.sql("SELECT JSON_OBJECT('id',id,'data',data_emissione,'importo',importo,"
                                  "'azienda',azienda_json,'righe',righe_json) FROM proforme "
                                  f'WHERE commessa_id={job_id}'))
        return job_id, rows, token, row

    def credit_payload(self, pf_id, amount, date=None):
        page = self.request('proforme.php?view=' + str(pf_id))
        token = re.search(r'name="csrf_token" value="([^"]+)"', page).group(1)
        key = re.search(r'name="request_key" value="([^"]+)"', page)
        return {'azione': 'accredito', 'proforma_id': pf_id, 'csrf_token': token,
                'data_accredito': date or '2026-10-23', 'importo': amount,
                'request_key': key.group(1) if key else secrets.token_hex(16)}

    def test_proforma_completion_and_document_snapshot(self):
        job_id, rows, token, pf = self.complete_proforma()
        self.assertEqual(self.sql(f'SELECT stato FROM commesse WHERE id={job_id}'), 'Completato')
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM fatture WHERE commessa_id={job_id}'), '0')
        self.assertEqual(len(json.loads(pf['righe'])), 2)
        self.assertEqual(float(pf['importo']), 250)
        company = json.loads(pf['azienda'])
        page = self.request('proforme.php?view=' + str(pf['id']))
        self.assertIn(company['ragione_sociale'], page)
        self.assertIn(company['partita_iva'], page)
        self.assertIn('Verifica personalizzata', page)
        self.assertIn('type="date"', page)
        self.assertIn('href="proforme.php"', page)
        self.request('commesse.php?edit=' + job_id, {'azione': 'complete_commessa', 'id': job_id, 'csrf_token': token})
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM proforme WHERE commessa_id={job_id}'), '1')
        self.assertIn('Filtro ricerca commesse', self.request('commesse.php'))
        # Completing individual moments emits the same one-document job flow.
        other_id, _, other_rows, other_token, _ = self.create_consuntivo_job()
        for i, moment in enumerate(other_rows):
            self.request('commesse.php?edit=' + other_id, {'azione': 'complete_momento', 'id': other_id,
                         'momento_id': moment['id'], 'csrf_token': other_token})
            self.assertEqual(self.sql(f'SELECT COUNT(*) FROM proforme WHERE commessa_id={other_id}'), str(i))
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM fatture WHERE commessa_id={other_id}'), '0')

    def test_proforma_credits_and_colors(self):
        _, _, _, pf = self.complete_proforma()
        pf_id = pf['id']
        first = self.credit_payload(pf_id, '100.10', pf['data'])
        self.assertIn('Accredito registrato correttamente.', self.request('proforme.php?view=' + str(pf_id), first))
        page = self.request('proforme.php?view=' + str(pf_id))
        self.assertRegex(page, f'data-proforma-id="{pf_id}" class="table-warning"')
        self.assertIn('Acconto', page)
        self.request('proforme.php?view=' + str(pf_id), first)
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM proforma_accrediti WHERE proforma_id={pf_id}'), '1')
        second = self.credit_payload(pf_id, '149.90', pf['data'])
        self.request('proforme.php?view=' + str(pf_id), second)
        page = self.request('proforme.php?view=' + str(pf_id))
        self.assertRegex(page, f'data-proforma-id="{pf_id}" class="table-success"')
        self.assertIn('Saldo', page)
        self.assertIn('Residuo: € 0,00', page)
        self.assertEqual(float(self.sql(f'SELECT SUM(importo) FROM proforma_accrediti WHERE proforma_id={pf_id}')), 250)
        # A fresh crafted submission cannot overpay a settled document.
        extra = dict(second, importo='0.01', request_key=secrets.token_hex(16))
        self.assertIn('supera il residuo', self.request('proforme.php?view=' + str(pf_id), extra))
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM proforma_accrediti WHERE proforma_id={pf_id}'), '2')

    def test_proforma_overdue_conversion_and_immutable_invoice(self):
        import datetime
        _, _, _, pf = self.complete_proforma()
        pf_id = pf['id']
        page = self.request('proforme.php?view=' + str(pf_id))
        token = re.search(r'name="csrf_token" value="([^"]+)"', page).group(1)
        payload = {'azione': 'genera_fattura', 'proforma_id': pf_id, 'csrf_token': token}
        self.assertIn('solo dopo 30 giorni senza accrediti', self.request('proforme.php', payload))
        base_date = datetime.date.fromisoformat(pf['data'])
        for age, allowed in [(29, False), (30, True)]:
            issued = (base_date - datetime.timedelta(days=age)).isoformat()
            self.sql(f"UPDATE proforme SET data_emissione='{issued}' WHERE id={pf_id}")
            page = self.request('proforme.php?view=' + str(pf_id))
            pf_row = re.search(f'<tr data-proforma-id="{pf_id}".*?</tr>', page, re.S).group()
            self.assertEqual('table-danger' in pf_row, allowed)
            self.assertEqual('aria-label="Genera Fattura"' in pf_row, allowed)
        # Source changes must not alter the already issued customer/line snapshots.
        company = json.loads(pf['azienda'])
        self.sql(f"UPDATE aziende SET ragione_sociale=CONCAT(ragione_sociale,'_alt') WHERE id={company['id']}")
        response = self.request('proforme.php', payload)
        self.assertIn('Fattura generata', response)
        invoice_id = self.sql(f'SELECT fattura_id FROM proforme WHERE id={pf_id}')
        self.assertTrue(invoice_id and invoice_id != 'NULL')
        invoice = json.loads(self.sql("SELECT JSON_OBJECT('azienda',azienda_json,'righe',righe_json,'importo',importo) "
                                      f'FROM fatture WHERE id={invoice_id}'))
        self.assertEqual(invoice['azienda'], pf['azienda'])
        self.assertEqual(invoice['righe'], pf['righe'])
        self.assertEqual(float(invoice['importo']), float(pf['importo']))
        self.assertIn(company['ragione_sociale'], self.request('fatture.php?view=' + invoice_id))
        self.request('proforme.php', payload)
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM fatture WHERE proforma_id={pf_id}'), '1')
        credit = self.credit_payload(pf_id, '250', pf['data'])
        self.request('proforme.php', credit)
        self.assertEqual(self.sql(f'SELECT pagata FROM fatture WHERE id={invoice_id}'), '1')

    def test_proforma_validation_and_partial_payment_after_deadline(self):
        import datetime
        import urllib.error
        _, _, _, pf = self.complete_proforma()
        pf_id = pf['id']
        data = self.credit_payload(pf_id, '1', pf['data'])
        for changes in [{'importo': '-1'}, {'importo': '0'}, {'importo': '1.001'},
                        {'importo': '251'}, {'data_accredito': '2026-02-30'}]:
            self.assertIn('alert alert-danger', self.request('proforme.php', dict(data, **changes)))
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM proforma_accrediti WHERE proforma_id={pf_id}'), '0')
        with self.assertRaises(urllib.error.HTTPError) as error:
            self.request('proforme.php', dict(data, csrf_token='invalid'))
        self.assertEqual(error.exception.code, 403)
        old_date = (datetime.date.fromisoformat(pf['data']) - datetime.timedelta(days=31)).isoformat()
        self.sql(f"UPDATE proforme SET data_emissione='{old_date}' WHERE id={pf_id}")
        self.request('proforme.php', data)
        page = self.request('proforme.php?view=' + str(pf_id))
        row = re.search(f'<tr data-proforma-id="{pf_id}".*?</tr>', page, re.S).group()
        self.assertIn('table-warning', row)
        self.assertNotIn('Genera Fattura', row)
        payload = {'azione': 'genera_fattura', 'proforma_id': pf_id, 'csrf_token': data['csrf_token']}
        self.assertIn('solo dopo 30 giorni senza accrediti', self.request('proforme.php', payload))
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM fatture WHERE proforma_id={pf_id}'), '0')

    def test_company_vat_suggestions(self):
        import urllib.error
        data, company = self.quick_company()
        prefix = data['partita_iva'][:4]
        matches = json.loads(self.request('aziende_suggerimenti.php?q=' + prefix))
        self.assertTrue(any(int(row['id']) == company['id'] for row in matches))
        self.assertTrue(all(row['partita_iva'].startswith(prefix) for row in matches))
        self.assertLessEqual(len(matches), 10)
        for invalid in ['1', 'abc', "x' OR 1=1"]:
            self.assertEqual(json.loads(self.request('aziende_suggerimenti.php?q=' + urllib.parse.quote(invalid))), [])
        for page in ('aziende.php?action=new', 'offerte.php?action=new'):
            self.assertIn('assets/js/company-autocomplete.js', self.request(page))
        anonymous = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        with self.assertRaises(urllib.error.HTTPError) as error:
            anonymous.open('http://127.0.0.1:8000/aziende_suggerimenti.php?q=' + prefix)
        self.assertEqual(error.exception.code, 401)

    def test_completion_preserves_legacy_invoices_and_requires_csrf(self):
        import urllib.error
        job_id, _, rows, token, _ = self.create_consuntivo_job()
        payload = {'azione': 'complete_commessa', 'id': job_id, 'csrf_token': token}
        with self.assertRaises(urllib.error.HTTPError) as error:
            self.request('commesse.php?edit=' + job_id, dict(payload, csrf_token='bad'))
        self.assertEqual(error.exception.code, 403)
        self.sql(f"INSERT INTO fatture (commessa_id,momento_id,numero,anno_riferimento,importo) VALUES ({job_id},{rows[0]['id']},'legacy-{job_id}',2026,200)")
        response = self.request('commesse.php?edit=' + job_id, payload)
        self.assertIn('ha già fatture emesse', response)
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM proforme WHERE commessa_id={job_id}'), '0')
        self.assertEqual(self.sql(f'SELECT COUNT(*) FROM fatture WHERE commessa_id={job_id}'), '1')
        self.assertEqual(self.sql(f'SELECT stato FROM commesse WHERE id={job_id}'), 'In corso')

    def test_company_autocomplete_interaction(self):
        from pathlib import Path
        script = (Path(__file__).resolve().parent.parent / 'assets/js/company-autocomplete.js').read_text()
        node_test = r"""
const assert=require('node:assert/strict');
const create=()=>({children:[],events:{},setAttribute(){},replaceChildren(){this.children=[];},appendChild(child){this.children.push(child);},addEventListener(name,fn){this.events[name]=fn;},focus(){this.focused=true;},querySelector(){return this.children[0];}});
const modal={};
const input=create();input.value='123';input.insertAdjacentElement=(where,box)=>input.box=box;input.closest=()=>modal;
const plain=create();plain.value='123';plain.insertAdjacentElement=(where,box)=>plain.box=box;plain.closest=()=>null;
const select={options:[{value:'42'}],value:'',dispatchEvent(){this.changed=true;}};
const document={querySelectorAll(){return [input,plain];},createElement(){return create();},getElementById(){return select;}};
globalThis.fetch=async()=>({ok:true,json:async()=>[{id:42,partita_iva:'12345678901',ragione_sociale:'Azienda <test>'}]});
globalThis.setTimeout=fn=>{fn();return 1;};globalThis.clearTimeout=()=>{};
globalThis.window={location:{href:''}};
let hidden=false;globalThis.bootstrap={Modal:{getInstance(){return {hide(){hidden=true;}};}}};
new Function('document', SCRIPT)(document);
(async()=>{
 input.events.input();plain.events.input();
 await new Promise(resolve=>setImmediate(resolve));
 assert.equal(input.box.children.length,1);
 assert.equal(input.box.children[0].textContent,'12345678901 — Azienda <test> (già censita)');
 input.box.children[0].events.click();
 assert.equal(select.value,'42');assert.equal(select.changed,true);assert.equal(hidden,true);
 plain.box.children[0].events.click();assert.equal(window.location.href,'aziende.php?view=42');
 input.value='1';input.events.input();assert.equal(input.box.children.length,0);
})().catch(error=>{console.error(error);process.exitCode=1;});
""".replace('SCRIPT', json.dumps(script))
        subprocess.run(['node', '-e', node_test], check=True)

    def test_company_edit_hides_filters_and_list(self):
        _, company = self.quick_company()
        page = self.request('aziende.php?edit=' + str(company['id']))
        self.assertIn('Modifica Azienda', page)
        self.assertNotIn('Filtri Aziende', page)
        self.assertNotIn('Elenco Aziende', page)
        listing = self.request('aziende.php')
        self.assertIn('Filtri Aziende', listing)
        self.assertIn('Elenco Aziende', listing)

    def test_commesse_open_closed_filter_and_protocol_title(self):
        opened, _, _, _, _ = self.create_consuntivo_job()
        closed, _, _, _ = self.complete_proforma()
        open_page = self.request('commesse.php?f_stato=Aperta')
        closed_page = self.request('commesse.php?f_stato=Chiusa')
        self.assertIn('commesse.php?edit=' + opened + '"', open_page)
        self.assertNotIn('commesse.php?edit=' + closed + '"', open_page)
        self.assertIn('commesse.php?edit=' + closed + '"', closed_page)
        self.assertNotIn('commesse.php?edit=' + opened + '"', closed_page)
        self.assertTrue(Form(open_page).selects['f_stato']['Aperta'])
        self.assertTrue(Form(closed_page).selects['f_stato']['Chiusa'])
        all_page = self.request('commesse.php')
        for job in (opened, closed):
            self.assertIn('commesse.php?edit=' + job + '"', all_page)
        company_id = self.sql(f'SELECT azienda_id FROM offerte WHERE id=(SELECT offerta_id FROM commesse WHERE id={opened})')
        combined = self.request('commesse.php?f_stato=Aperta&f_azienda_id=' + company_id)
        self.assertIn('commesse.php?edit=' + opened + '"', combined)
        self.assertNotIn('commesse.php?edit=' + closed + '"', combined)
        protocol = self.sql(f'SELECT protocollo FROM commesse WHERE id={opened}')
        detail = self.request('commesse.php?edit=' + opened)
        self.assertIn('<h2 class="mb-0">Commessa n° prot ' + protocol + '</h2>', detail)
        self.assertIn('<title>Simplex - Commessa n° prot ' + protocol + '</title>', detail)

    def test_active_menu_and_expanded_groups(self):
        job_id, offer_id, _, _, _ = self.create_consuntivo_job()
        company_id = self.sql(f'SELECT azienda_id FROM offerte WHERE id={offer_id}')
        cases = [(page, page, group) for page, group in [
            ('bacheca.php', None), ('offerte.php', None), ('commesse.php', None),
            ('utenti.php', 'Anagrafiche'), ('aziende.php', 'Anagrafiche'),
            ('enti_certificazione.php', 'Anagrafiche'), ('amministrazione_produzione.php', 'Amministrazione'),
            ('proforme.php', 'Amministrazione'), ('fatture.php', 'Amministrazione'), ('pagamenti.php', 'Amministrazione'),
        ]]
        cases.extend([
            ('aziende.php?edit=' + company_id, 'aziende.php', 'Anagrafiche'),
            ('commesse.php?edit=' + job_id, 'commesse.php', None),
            ('offerte.php?action=new', 'offerte.php', None),
            ('lavorazioni.php?offerta_id=' + offer_id, 'offerte.php', None),
        ])
        for page, target, group in cases:
            with self.subTest(page=page):
                body = self.request(page)
                nav = re.search(r'<nav\b.*?</nav>', body, re.S).group()
                self.assertEqual(nav.count('aria-current="page"'), 1)
                self.assertIn('class="nav-link active" aria-current="page" href="' + target + '"', nav)
                if group:
                    self.assertIn('class="nav flex-column ms-3 collapse show" id="menu' + group + '"', nav)
                    self.assertRegex(nav, 'href="#menu' + group + '"[^>]*aria-expanded="true"')

    def test_offer_italian_datepicker_roundtrip(self):
        from pathlib import Path
        body = self.request('offerte.php?action=new')
        self.assertIn("dateFormat: 'd/m/Y'", body)
        self.assertIn("locale: 'it'", body)
        self.assertIn('disableMobile: true', body)
        self.assertIn('initOfferDatepickers(tr);', body)
        for resource in ('flatpickr.min.js', 'flatpickr.min.css', 'it.js'):
            self.assertTrue(self.request('assets/vendor/flatpickr/' + resource))
        library = str(Path(__file__).resolve().parent.parent / 'assets/vendor/flatpickr/flatpickr.min.js')
        code = "const assert=require('node:assert/strict');const fp=require(" + json.dumps(library) + ");" +                "const date=fp.parseDate('23/10/2026','d/m/Y');assert.equal(fp.formatDate(date,'d/m/Y'),'23/10/2026');"
        subprocess.run(['node', '-e', code], check=True)
        _, company = self.quick_company()
        data = self.offer_data(company['id'])
        data.update(data_offerta='23/10/2026', data_scadenza='22/11/2026')
        data['momento_data_momento[]'] = ['01/11/2026']
        self.assertIn('Offerta creata correttamente', self.request('offerte.php?action=new', data))
        offer_id = self.sql(f"SELECT id FROM offerte WHERE azienda_id={company['id']}")
        self.assertEqual(self.sql(f'SELECT data_offerta FROM offerte WHERE id={offer_id}'), '2026-10-23')
        self.assertEqual(self.sql(f'SELECT data_scadenza FROM offerte WHERE id={offer_id}'), '2026-11-22')
        self.assertEqual(self.sql(f'SELECT data_momento FROM offerta_momenti_lavorazione WHERE offerta_id={offer_id}'), '2026-11-01')
        edit = self.request('offerte.php?edit=' + offer_id)
        for date in ('23/10/2026', '22/11/2026', '01/11/2026'):
            self.assertIn('value="' + date + '"', edit)


if __name__ == '__main__':
    unittest.main(verbosity=2)
