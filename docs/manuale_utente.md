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

### 7.3 Generazione automatica fattura
Quando crei un momento con tipologia **Chiusura**, il sistema genera automaticamente una fattura con:
- Numero formato `ID_UNIVOCO/ANNO_CORRENTE`
- Importo calcolato dal totale del momento di lavorazione

Nella tabella dei momenti è presente l'icona per aprire la fattura collegata.

## 8. Fatture
La sezione **Fatture** contiene:
- Elenco di tutte le fatture generate
- Filtri di ricerca
- Dettaglio della singola fattura

Per ogni fattura è disponibile l'icona **banconota** per registrare il pagamento.

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
La sezione **Produzione** permette una vista amministrativa delle commesse:
- Elenco commesse
- Filtri per campi caratteristici
- Totale budget in euro delle righe visualizzate

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
- **Fattura non generata**: verificare che il momento sia di tipo **Chiusura**.
- **Pagamento non visibile**: verificare conferma popup e filtri attivi.

## 14. Supporto
Per problemi applicativi o richieste evolutive contattare l'amministratore di sistema.
