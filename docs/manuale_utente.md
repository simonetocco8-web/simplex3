# Manuale Utente - Sistema Simplex

Versione: 1.0  
Data: 09/04/2026

## 1. Introduzione
Simplex è un sistema web amministrativo per la gestione operativa e commerciale di:
- Utenti
- Aziende
- Enti di Certificazione
- Offerte
- Commesse
- Momenti di Lavorazione
- Fatture
- Pagamenti
- Produzione (report amministrativo)

Il sistema è pensato per tracciare l'intero ciclo: dall'offerta, alla commessa, alla fatturazione e incasso.

## 2. Accesso al sistema
### 2.1 Login
1. Apri la pagina di accesso.
2. Inserisci username e password.
3. Premi **Accedi**.

### 2.2 Recupero credenziali
Se non ricordi la password usa la funzione **Recupera credenziali**.

### 2.3 Logout
Usa il pulsante **Logout** nel menù laterale.

## 3. Navigazione e Menù
Il menù laterale è organizzato in:
- **Bacheca**
- **Offerte**
- **Commesse**
- **Anagrafiche** (a tendina):
  - Utenti
  - Aziende
  - Enti di Certificazione
- **Amministrazione** (a tendina):
  - Produzione
  - Pro-Forma
  - Fatture
  - Pagamenti

## 4. Bacheca
La **Bacheca** mostra una vista sintetica delle informazioni principali.
In base al ruolo, l'utente può visualizzare commesse assegnate o riepiloghi utili.

## 5. Anagrafiche
### 5.1 Utenti
Funzioni principali:
- Inserimento nuovo utente
- Modifica dati utente
- Attivazione/disattivazione
- Gestione ruoli

### 5.2 Aziende
Funzioni principali:
- Inserimento nuova azienda
- Modifica anagrafica azienda
- Eliminazione azienda
- Ricerca/filtro

### 5.3 Enti di Certificazione
Funzioni principali:
- Inserimento di un ente con denominazione
- Visualizzazione elenco enti
- Eliminazione ente

## 6. Offerte
Nella sezione **Offerte** puoi:
- Creare una nuova offerta
- Compilare servizio, dettaglio, stato e dati economici
- Aggiornare offerte esistenti
- Cercare tramite filtri

### 6.1 Generazione commessa
Quando un'offerta viene aggiudicata/gestita secondo il flusso previsto, la commessa collegata viene resa disponibile in sezione **Commesse**.

## 7. Commesse
La sezione **Commesse** consente di:
- Modificare i dati della commessa
- Gestire budget e dati amministrativi
- Caricare file allegati
- Gestire i **Momenti di Lavorazione**

### 7.1 Enti di Certificazione in commessa
Se la commessa deriva da un'offerta con servizio **SISTEMI DI GESTIONE AZIENDALE**, sono disponibili i campi:
- **Ente di Certificazione** (menù a tendina)
- **Importo dell'Ente di Certificazione** (Euro)

### 7.2 Momenti di Lavorazione
Per ogni commessa puoi inserire momenti con:
- Data
- Tipologia (Apertura/Chiusura)
- Valore giornaliero uomo
- Ore/Giorni
- Numero incontri
- Ore studio
- Data prevista

### 7.3 Completamento e Pro-Forma
Nel dettaglio della commessa, **Imposta Completato e genera Pro-Forma** completa tutti i momenti e genera una sola Pro-Forma riepilogativa. Anche completando i singoli momenti, il documento viene emesso quando tutti risultano completati. Il completamento non genera più una fattura.

La Pro-Forma riporta i dati dell'azienda e i momenti della commessa, con gli importi già usati per la fatturazione. Questi dati vengono conservati all'emissione; modifiche successive all'anagrafica non cambiano il documento. Le fatture precedentemente emesse restano conservate e impediscono una seconda emissione automatica per la stessa commessa.

### 7.4 Pro-Forma e accrediti
Da **Amministrazione → Pro-Forma** puoi consultare i documenti e registrare accrediti con data e importo. Un accredito inferiore al residuo è un acconto; quando il totale degli accrediti raggiunge esattamente l'importo della Pro-Forma, il documento è saldato. Non è possibile superare il residuo.

- Verde: Pro-Forma interamente pagata.
- Giallo: pagamento parziale, anche oltre i 30 giorni.
- Rosso: almeno 30 giorni dall'emissione e nessun accredito.

Per le sole Pro-Forma rosse compare **Genera Fattura**: crea una singola fattura con gli stessi dati azienda, momenti e importo, consultabile in **Fatture**. Gli eventuali accrediti successivi si registrano dalla Pro-Forma collegata e aggiornano anche lo stato di pagamento della fattura.

## 8. Fatture
La sezione **Fatture** contiene:
- Elenco di tutte le fatture generate
- Filtri di ricerca
- Dettaglio della singola fattura

Per le fatture precedenti è disponibile l'icona **banconota** per registrare il pagamento. Per quelle generate da Pro-Forma, il collegamento **Accrediti** porta al documento di origine.

### 8.1 Registrazione pagamento da fattura
Cliccando l'icona banconota si apre un popup con:
- Data ricezione pagamento (datepicker)
- Modalità pagamento:
  - Contanti
  - Bonifico
  - Carta di Credito

Confermando, il sistema salva il pagamento e aggiorna lo stato della fattura.

## 9. Pagamenti
La sezione **Pagamenti** mostra tutti i pagamenti registrati con:
- Numero fattura
- Commessa
- Importo
- Data pagamento
- Modalità pagamento

Sono disponibili filtri per ricercare pagamenti specifici.

## 10. Produzione (Amministrazione)
La sezione **Produzione** riepiloga per utente:
- **Carico di Lavoro**: numero di commesse assegnate ancora aperte.
- **Commesse gestite**: numero di commesse assegnate, aperte e chiuse.
- **Importo**: somma dei totali delle offerte collegate, al netto dello sconto, delle commesse gestite.

I filtri **Oggi**, **Ieri**, **Ultimi 7 Giorni**, **Mese Corrente**, **Anno Corrente** e **Dall'inizio** si riferiscono alla data di creazione della commessa nel fuso Europe/Rome. Gli ultimi sette giorni comprendono oggi e i sei giorni precedenti. Sono disponibili anche **Servizio** e **Servizio Specifico**, combinabili con il periodo.

Sono visibili anche gli utenti senza carico nel periodo. Le commesse non assegnate o con un vecchio nominativo non identificabile univocamente sono segnalate separatamente ed escluse dai totali per utente.

## 11. Allegati commessa
Nella commessa puoi caricare allegati.
Per ogni file sono disponibili:
- Visualizzazione
- Download

## 12. Buone pratiche operative
- Compilare sempre i campi obbligatori.
- Verificare i dati economici prima del salvataggio.
- Registrare pagamenti appena ricevuti.
- Usare i filtri per controlli periodici (Fatture/Pagamenti/Produzione).

## 13. Risoluzione problemi comuni
- **Credenziali non valide**: verificare username/password o usare recupero credenziali.
- **Campi non salvati**: controllare i messaggi di errore in pagina.
- **Pro-Forma non generata**: completare la commessa o tutti i suoi momenti; verificare che non siano già presenti fatture.
- **Genera Fattura non disponibile**: sono richiesti almeno 30 giorni dall’emissione e nessun accredito sulla Pro-Forma.
- **Pagamento non visibile**: verificare conferma popup e filtri attivi.

## 14. Supporto
Per problemi applicativi o richieste evolutive contattare l'amministratore di sistema.
