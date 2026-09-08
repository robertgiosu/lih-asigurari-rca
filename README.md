# Calculator RCA

Aplicație web care cere simultan oferte de asigurare RCA de la **11 asigurători**, le compară după preț, transformă oferta aleasă în poliță și descarcă documentele PDF — folosind [API-ul RCA Life is Hard](https://api.lifeishard.ro/docs/rca-api/rca_api_v1_4_1).

Construită cu **Laravel 13**, **SQLite**, **Blade + Tailwind 4** și puțin **Alpine.js**.

```
Completezi formularul  →  11 apeluri în paralel  →  compari prețurile  →  emiți polița  →  descarci PDF-ul
        ~25 secunde                                    sortate crescător
```

Fiecare pas lasă o urmă completă în baza de date, vizibilă la `/istoric`.

---

## Cuprins

- [Ce face, concret](#ce-face-concret)
- [Instalare](#instalare)
- [Cum funcționează](#cum-funcționează)
- [Structura codului](#structura-codului)
- [Trasabilitate](#trasabilitate)
- [Ce am aflat despre API](#ce-am-aflat-despre-api-și-nu-scrie-în-documentație)
- [Particularități per asigurător](#particularități-per-asigurător)
- [Rute](#rute)
- [Testare](#testare)
- [Securitate](#securitate)
- [Depanare](#depanare)

---

## Ce face, concret

| Funcționalitate | Detalii |
|---|---|
| **Cotație comparativă** | Un singur formular → `POST /offer` către toți asigurătorii simultan, prin `Http::pool()`. Rezultatele apar sortate crescător după primă. |
| **Emitere poliță** | `POST /policy` transformă o ofertă în poliță reală, cu sau fără decontare directă. |
| **Documente PDF** | Descarcă PDF-ul ofertei și al poliței, le păstrează local și nu le mai cere a doua oară. |
| **Conturi** | Register / login / logout scrise manual, fără starter kit. |
| **Profil** | Datele personale se salvează o dată (CNP-ul criptat) și pre-completează automat formularul de ofertă. |
| **Trasabilitate** | Tot ce se introduce în Web se salvează *înainte* de orice apel extern, împreună cu fiecare cerere HTTP și fiecare acțiune a utilizatorului. |
| **Nomenclator** | 42 de județe și ~13.900 de localități sincronizate local, pentru codul SIRUTA cerut de API. |

**Asigurători interogați:** Allianz-Țiriac, Asirom, Axeria, Eazy Insure, Generali, Grawe, Groupama, Hellas Direct (Autonom și NextIns), Omniasig, DallBogg.

---

## Instalare

### Cerințe

- PHP **8.5** cu extensiile `pdo_sqlite`, `curl`, `mbstring`, `openssl`
- Composer 2
- Node.js 20+
- **Acces la API-ul RCA** — mediile QA și producție sunt limitate la adrese IP autorizate

Dacă nu ai PHP:

```bash
# macOS
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

apoi repornește terminalul sau rulează `source ~/.zshrc`.

### Pași

```bash
git clone https://github.com/robertgiosu/lih-asigurari-rca.git
cd lih-asigurari-rca

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate
```

Completează în `.env` credențialele pentru API:

```dotenv
RCA_BASE_URL=https://rca-qa.api.lifeishard.ro
RCA_ACCOUNT=test
RCA_PASSWORD=test
RCA_TIMEOUT=30
RCA_CONNECT_TIMEOUT=10
```

Verifică imediat că API-ul te acceptă — dacă IP-ul tău nu e autorizat, nimic altceva nu va funcționa:

```bash
curl -X POST "$RCA_BASE_URL/auth?account=test&password=test"
# {"error":false,"status":200,"data":{"token":"eyJ0eXAi...","expires_at":"...","refresh_token":"..."}}
```

Sincronizează nomenclatorul (43 de apeluri, ~30 de secunde, o singură dată):

```bash
php artisan rca:sync-nomenclature
# Gata: 42 judete, 13872 localitati.
```

Pornește:

```bash
composer dev     # server + vite + coadă, toate odată
```

Deschide **http://localhost:8000**. În mediul local, butonul **„Completează cu date de test"** umple formularul cu datele din documentația API-ului, ca să nu tastezi 35 de câmpuri la fiecare încercare.

---

## Cum funcționează

### Fluxul unei cotații

```
  Browser                    Laravel                          API Life is Hard
     │                          │                                    │
     │  POST /oferta            │                                    │
     ├─────────────────────────>│                                    │
     │                          │ 1. validează (CNP, enumerări,      │
     │                          │    localitate ↔ județ)             │
     │                          │                                    │
     │                          │ 2. SALVEAZĂ cererea în DB          │
     │                          │    ← înainte de orice apel         │
     │                          │                                    │
     │                          │ 3. token din cache (sau POST /auth)│
     │                          ├───────────────────────────────────>│
     │                          │                                    │
     │                          │ 4. Http::pool() — 11 x POST /offer │
     │                          ├═══════════════════════════════════>│
     │                          │<═══════════════════════════════════┤
     │                          │    ~25s (nu 97s, cât ar dura       │
     │                          │     secvențial)                    │
     │                          │                                    │
     │                          │ 5. salvează provider_quotes,       │
     │                          │    offers și 11 rânduri în api_logs│
     │  302 → /oferta/{uuid}    │                                    │
     │<─────────────────────────┤                                    │
```

**Pasul 2 e cel care contează pentru trasabilitate.** Formularul se scrie în baza de date *înainte* de a contacta pe cineva. Dacă toți cei 11 asigurători cad, sau dacă procesul moare la jumătate, datele introduse rămân salvate.

### Tokenul de autentificare

`RcaTokenManager` are trei stări, nu două:

```
token valid în cache?  ──da──>  îl folosește
        │ nu
        ▼
are refresh_token?     ──da──>  PATCH /auth  ──eșuat──┐
        │ nu                                          │
        ▼                                             │
   POST /auth  <──────────────────────────────────────┘
```

Tokenul se cere **o singură dată, înainte de pool**. Altfel cele 11 apeluri paralele ar rata simultan cache-ul și ar produce 11 autentificări.

### De ce nu se afișează ofertele una câte una

Interfața arată un spinner cu cronometru, nu rezultate progresive. Motivul e practic: `php artisan serve` procesează **un singur request odată**, deci 11 cereri separate din browser s-ar executa în șir — ~97 de secunde în loc de ~25. Un singur POST cu `Http::pool()` e de aproape patru ori mai rapid.

Formularul se trimite ca POST HTML normal; Alpine doar afișează ceva peste el. **Fără JavaScript aplicația funcționează identic**, doar fără spinner.

---

## Structura codului

```
app/
├── Console/Commands/
│   └── SyncNomenclature.php        rca:sync-nomenclature
│
├── Http/
│   ├── Controllers/
│   │   ├── Auth/{Login,Register}Controller.php
│   │   ├── QuoteController.php     formular, cotație, PDF ofertă
│   │   ├── PolicyController.php    emitere, detaliu, PDF poliță
│   │   ├── ProfileController.php   „Profilul meu"
│   │   └── HistoryController.php   /istoric — urma completă
│   └── Requests/
│       ├── StoreQuoteRequest.php   ~45 reguli + verificări încrucișate
│       └── UpdateProfileRequest.php
│
├── Models/
│   ├── QuoteRequest, ProviderQuote, Offer, Policy    business
│   ├── ApiLog, AuditEvent                            trasabilitate
│   ├── County, Locality                              nomenclator
│   └── Profile, User
│
├── Rules/Cnp.php                   cifra de control + deducere sex/dată naștere
│
├── Services/Rca/
│   ├── RcaTokenManager.php         JWT: cache, reînnoire, expirare
│   ├── RcaClient.php               poarta unică: token, logare, retry la 401, pool()
│   ├── ApiCallLogger.php           scrie în api_logs, maschează, trunchiază
│   ├── OfferPayloadBuilder.php     formular → JSON-ul API-ului
│   ├── OfferService.php            orchestrează pool-ul și persistă
│   ├── PolicyService.php           emitere, cu payment auto-generat
│   ├── PdfService.php              decodează base64, salvează pe disc
│   ├── NomenclatureService.php     județe / localități
│   ├── RcaException.php            eroare cu context (provider, status, body)
│   └── RcaPoolResult.php           rezultatul unui apel din pool
│
└── Support/Correlation.php         UUID-ul acțiunii curente
```

### Regula de aur a arhitecturii

**Controllerele, comenzile și job-urile sunt uși de intrare, nu locuri unde stă logica.** Tot ce contează trăiește în `app/Services/Rca/`, unde se testează cu `Http::fake()` în milisecunde, fără să atingă rețeaua.

### Configurarea ca sursă unică de adevăr

`config/rca.php` ține trei lucruri:

1. **Conexiunea** — URL, credențiale (citite din `.env`), timeouts
2. **Asigurătorii** — slug, etichetă afișată, particularități
3. **Enumerările API-ului** — `vehicleType`, `fuelType`, `usageType`, etc., ca perechi `cheie => etichetă`

Al treilea punct evită duplicarea în trei locuri:

```php
// validare
Rule::in(array_keys(config('rca.enums.fuel_type')))

// formular
@foreach(config('rca.enums.fuel_type') as $valoare => $eticheta)
    <option value="{{ $valoare }}">{{ $eticheta }}</option>
@endforeach
```

Cheia (`diesel`) pleacă spre API, eticheta (`Motorină`) se vede în interfață. Imposibil să divergă.

> `env()` funcționează **doar** în `config/`. După `php artisan config:cache`, orice `env()` din restul aplicației întoarce `null`. Peste tot altundeva: `config('rca.…')`.

---

## Trasabilitate

Cerință de prim rang, nu logging accidental. Patru tabele, cu roluri distincte:

| Tabel | Răspunde la întrebarea | Un click produce |
|---|---|---|
| `quote_requests` | *Ce a completat omul?* | 1 rând, cu tot formularul în `input` (JSON) |
| `provider_quotes` | *Ce a răspuns fiecare asigurător?* | 11 rânduri: status, cod HTTP, durată, eroare |
| `api_logs` | *Ce a plecat efectiv pe rețea?* | 11 rânduri: URL, antete, corp cerere/răspuns, durată |
| `audit_events` | *Ce a făcut utilizatorul?* | 1 rând: `quote.requested`, cu IP, sesiune, user agent |

Le leagă **`correlation_id`** — un UUID generat o dată per acțiune web.

### Chei străine: da la business, nu la audit

```php
// provider_quotes — cheie străină reală
$table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();

// api_logs — deliberat FĂRĂ ->constrained()
$table->foreignId('quote_request_id')->nullable()->index();
```

Trei motive pentru care log-urile nu au constrângeri:

1. **Un log trebuie să supraviețuiască obiectului pe care îl descrie.** Dacă ștergi un utilizator, urma acțiunilor lui nu trebuie să dispară.
2. **Un log nu are voie să arunce excepții** — o scriere care poate eșua ar rupe exact funcționalitatea pe care o înregistrează.
3. Ordinea migrărilor: `api_logs` se creează înaintea tabelelor pe care le referă.

### Ce nu ajunge niciodată în jurnale

`ApiCallLogger` curăță fiecare apel înainte de scriere:

| Ce | Cum |
|---|---|
| `password`, `token`, `refresh_token` | înlocuite cu `***`, recursiv, la orice adâncime |
| parola din query string (`/auth?password=…`) | mascată separat, în coloana `url` |
| PDF-uri base64 din `data.files[].content` | orice text peste 2000 de caractere → `<964959 octeti omisi>` |
| corpuri uriașe | peste 64 KB → `{"_truncated": true, "bytes": …}` |

Regula pentru `audit_events`: se salvează **ce câmpuri** s-au schimbat, niciodată valorile — altfel jurnalul ar deveni o colecție de CNP-uri.

### Pagina `/istoric/{uuid}`

Toate cele de mai sus, într-un singur ecran:

1. **Contextul** — utilizator, IP, sesiune, `correlation_id`, browser
2. **Ce s-a introdus** — fiecare câmp completat (`Arr::dot()` peste `input`), plus JSON-ul brut
3. **Asigurătorii** — toți 11, cu status, HTTP, durată individuală, mesaj de eroare
4. **Apelurile HTTP** — fiecare extensibil, cu antete și payload-uri
5. **Acțiunile** — evenimentele de audit, cu oră și IP

Pagina adună **toate** firele care au atins cererea: cotația, emiterea poliței și descărcările PDF s-au întâmplat în vizite web diferite, cu `correlation_id` diferite, dar toate au `quote_request_id`. O urmă tipică are 13 apeluri, nu 11.

---

## Ce am aflat despre API (și nu scrie în documentație)

Descoperiri făcute lovind API-ul real. Fiecare a devenit o decizie de cod și un test.

### 1. PDF-urile nu sunt binare

`GET /offer/{id}` și `GET /policy/{id}` întorc **JSON**, cu fișierul codat base64 în `data.files[].content` — nu un PDF pe fir. `PdfService` decodează cu `base64_decode($…, strict: true)` și scrie pe discul privat.

### 2. `expires_at` e în ora României, `exp` din JWT e UTC

```
claim exp din JWT ........ 1788058836  →  2026-08-30 03:00:36 UTC
câmp expires_at .......... "2026-08-30 06:00:36"      (UTC+3)
```

Aplicația rulează pe UTC. Un `Carbon::parse($data['expires_at'])` naiv ar fi crezut că tokenul mai trăiește **trei ore** după ce murise, producând erori `401` inexplicabile.

Soluția: expirarea se citește **din tokenul însuși**, unde `exp` e epoch UTC prin standard. Textul rămâne doar ca rezervă, parsat explicit cu `Europe/Bucharest`. *Când un API îți dă aceeași informație în două formate, alege-l pe cel fără ambiguitate.*

### 3. Nomenclatorul are alte nume de câmpuri decât specificația

| Câmp | Specificația OpenAPI | Realitate |
|---|---|---|
| județ | `county_code` | **`code`** |
| localitate | `nume` | **`name`** |
| localitate | `cod_judet` | **`countyCode`** |
| localitate | `cod_siruta` | **`siruta`** |

Bonus nedocumentat: fiecare localitate are un **`rang`** (2 = municipiu, 5 = sat), folosit ca dropdown-ul din Cluj să înceapă cu CLUJ-NAPOCA, nu cu un sat pe „A".

De asemenea, `/nomenclature/locality/XX` cu un cod inexistent întoarce `200` cu listă goală, **nu** o eroare.

### 4. Grawe cere `floor`, deși specificația spune că e opțional

```json
{"error": true, "status": 400, "message": "VALIDATION_ERROR",
 "data": {"product.policyholder.address.floor":
          ["Câmpul etaj adresă asigurat este obligatoriu."]}}
```

Verificat empiric: trimiterea lui `floor` către toți ceilalți nu strică nimic. E acum câmp obligatoriu în formular. *Payload-ul dovedit bate documentația.*

### 5. DallBogg nu e activat în QA

```json
{"provider.organization.businessName": ["Câmpul nume asigurator selectat este invalid."]}
```

Marcat cu `expect_failure` în config, deci interfața îl afișează calm ca „indisponibil în mediul de test", nu ca eroare.

### 6. Excepțiile dintr-un `Http::pool()` nu se aruncă

Vin ca obiecte `ConnectionException` **în array-ul de rezultate**. Cod care presupune `Response` dă fatal error. De aceea există `RcaPoolResult`: în restul aplicației, căderea unui asigurător e o *valoare*, nu o excepție.

Durata individuală nu se poate afla din durata pool-ului — e nevoie de opțiunea Guzzle `on_stats` pe fiecare cerere.

---

## Particularități per asigurător

Declarate în `config/rca.php`, nu în `if`-uri prin cod:

```php
'omniasig' => [
    'label' => 'Omniasig',
    'extra' => ['pti', 'bonus_malus_prev', 'bonus_malus_current'],
],
```

| Etichetă | Efect |
|---|---|
| `pti` | adaugă `additionalData.product.vehicle.expirationDatePti` |
| `bonus_malus_prev` | adaugă `additionalData.product.bonusMalusPrevClass` |
| `bonus_malus_current` | adaugă `additionalData.product.bonusMalusCurrentClass` |
| `house_number` | `address.houseNumber` devine obligatoriu (Axeria) |

| Asigurător | Cerințe |
|---|---|
| Allianz, Asirom, Eazy Insure | nimic special |
| Axeria | `houseNumber` obligatoriu |
| Generali, Groupama, Hellas ×2 | `expirationDatePti` |
| Omniasig, Grawe | `expirationDatePti` + ambele clase bonus-malus |
| Grawe | în plus, `address.floor` obligatoriu |
| DallBogg | `expect_failure` — indisponibil în QA |

**Un asigurător nou = patru linii de configurare, zero linii de logică.**

---

## Rute

| Metodă | URI | Nume | Acces |
|---|---|---|---|
| `GET` | `/oferta` | `oferta.create` | public |
| `POST` | `/oferta` | `oferta.store` | public |
| `GET` | `/oferta/{uuid}` | `oferta.show` | public (UUID) |
| `GET` | `/oferta/{uuid}/pdf/{offer}` | `oferta.pdf` | public (UUID) |
| `POST` | `/oferta/{uuid}/emite/{offer}` | `polita.store` | public (UUID) |
| `GET` | `/polita/{uuid}` | `polita.show` | public (UUID) |
| `GET` | `/polita/{uuid}/pdf` | `polita.pdf` | public (UUID) |
| `GET` | `/localitati/{county}` | `localitati` | public (JSON) |
| `GET\|POST` | `/inregistrare` | `register` | doar vizitatori |
| `GET\|POST` | `/autentificare` | `login` | doar vizitatori, `throttle:5,1` |
| `POST` | `/deconectare` | `logout` | autentificat |
| `GET\|PUT` | `/profil` | `profil.edit`, `profil.update` | autentificat |
| `GET` | `/istoric` | `istoric.index` | autentificat |
| `GET` | `/istoric/{uuid}` | `istoric.show` | autentificat + proprietar |

Rutele publice folosesc **UUID**, nu id-uri secvențiale: cu `/oferta/57` oricine ar putea încerca `/oferta/58`.

Endpoint-ul JSON pentru localități stă în `web.php`, nu în `api.php` — pentru o rută internă consumată de propria pagină, sesiunea și CSRF-ul vin gratis.

---

## Testare

```bash
php artisan test
# Tests: 64 passed (205 assertions)
```

Niciun test nu atinge rețeaua. `phpunit.xml` setează `RCA_BASE_URL` la un domeniu inexistent, iar fiecare test apelează `Http::preventStrayRequests()` — centura și bretelele.

| Fișier | Ce apără |
|---|---|
| `Unit/CnpTest` | cifra de control, deducerea sexului și a datei nașterii |
| `Rca/RcaTokenManagerTest` | cache, reînnoire, **bug-ul de fus orar** |
| `Rca/RcaClientTest` | antet `Token`, retry la `401` exact o dată, trunchierea PDF-urilor |
| `Rca/NomenclatureSyncTest` | maparea reală a câmpurilor, idempotența, ordonarea după `rang` |
| `Rca/OfferPayloadBuilderTest` | reproduce **exact** payload-ul Allianz din documentație |
| `Rca/OfferServiceTest` | 11 apeluri, căderea unuia nu afectează restul, urma completă |
| `Rca/PolicyServiceTest` | `payment` auto-generat, refuzul emiterii duble |
| `Rca/PdfServiceTest` | decodarea base64, cache pe disc, base64 absent din log-uri |
| `AuthTest` | parola hashuită, nereafișată, tentative eșuate fără parolă |
| `ProfileTest` | **criptarea la nivel de coloană**, autocompletarea formularului |
| `HistoryTest` | izolarea între utilizatori, secretele mascate și în interfață |

Testele bune verifică **de ce** a fost scris codul așa, nu doar ce face. Trei exemple:

- `test_expirarea_se_ia_din_jwt_nu_din_textul_expires_at` — cade dacă cineva „simplifică" la `Carbon::parse($data['expires_at'])`
- `test_reincearca_exact_o_data_dupa_401` — cade dacă dispare garda `$isRetry` care previne recursia infinită
- `test_cnp_ul_si_seria_actului_sunt_criptate_in_baza_de_date` — citește cu `DB::table()`, ocolind modelul, deci dovedește criptarea, nu doar prezența castului

---

## Securitate

| Măsură | Unde |
|---|---|
| Credențialele API doar în `.env` → `config/rca.php` | nu ajung niciodată în Blade sau JS |
| CNP și serie act criptate la nivel de coloană | `Profile`, cast `encrypted` |
| Parole hashuite (cast `hashed`), niciodată reafișate | `User`, `components/field.blade.php` |
| Sesiune regenerată la login, invalidată la logout | împotriva session fixation |
| `throttle:5,1` pe autentificare | 5 încercări/minut |
| Deconectarea e POST, nu link | un `<img src="…/deconectare">` nu o poate declanșa |
| PDF-uri pe discul privat, servite prin controller | `storage/app/private/` nu e accesibil din web |
| `abort_unless` pe legătura ofertă ↔ cerere | previne emiterea pe baza unui id ghicit |
| `/istoric` doar pentru proprietar | pagina conține CNP-uri și payload-uri complete |
| Secretele mascate în `api_logs` | verificat prin teste, la scriere **și** la afișare |
| UUID în URL-urile publice | fără enumerare de id-uri |

**Ce nu se comite niciodată în git:** `.env`, `database/*.sqlite*` (conține CNP-uri și polițe reale), `storage/app/private/` (PDF-urile polițelor). Toate sunt în `.gitignore`.

---

## Depanare

**`Maximum execution time of 30 seconds exceeded`**
`php.ini` nu setează `max_execution_time`, deci serverul web aplică implicitul de 30s, iar pool-ul îl poate depăși. Rezolvat cu `set_time_limit(config('rca.timeout') + 60)` în controllerele care fac apeluri lungi.

**`Class "App\Http\Requests\Rule" does not exist`**
Lipsește un `use`. PHP rezolvă numele necalificate relativ la namespace-ul fișierului, de unde prefixul ciudat din mesaj. Când vezi în eroare un namespace pe care nu l-ai scris niciodată, caută `use`-ul lipsă.

**Dropdown-ul de localități e gol**
N-ai rulat `php artisan rca:sync-nomenclature`, sau tabelul e gol:
```bash
php artisan tinker --execute="echo App\Models\Locality::count();"
```

**Toți asigurătorii dau eroare**
Cel mai probabil IP-ul tău nu e autorizat. Verifică cu `curl`-ul din secțiunea de instalare.

**„Există deja o poliță validă pentru acest vehicul"**
Nu e un defect: în QA, VIN-ul de test are deja polițe emise. Schimbă `startDate` sau folosește alt VIN.

**Istoricul e gol deși ai cerut oferte**
Cererile făcute cât timp erai deconectat au `user_id` null. Cere o ofertă nouă după autentificare.

---

## Comenzi utile

```bash
composer dev                      # server + vite + coadă
php artisan test                  # 64 de teste
php artisan rca:sync-nomenclature # reîmprospătează județele și localitățile
php artisan migrate:fresh         # ⚠️ șterge tot, inclusiv polițele emise

# urma unei cereri, direct din baza de date
sqlite3 database/database.sqlite \
  "SELECT provider, status, duration_ms FROM provider_quotes ORDER BY id DESC LIMIT 11;"
```

---

## Documentație API

- Ghid: <https://api.lifeishard.ro/docs/rca-api/rca_api_v1_4_1>
- Specificație OpenAPI: <https://api.lifeishard.ro/redocusaurus/rca-api.yaml>
- QA: `https://rca-qa.api.lifeishard.ro` · Producție: `https://rca.api.lifeishard.ro`

> Credențialele din mediul de test nu funcționează în producție, iar acolo fiecare asigurător cere propriile date în `provider.authentication`.
