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


if __name__ == '__main__':
    unittest.main(verbosity=2)
