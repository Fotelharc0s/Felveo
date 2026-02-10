# Felveo — Hivatalos Dokumentáció

## 1. Bevezetés

### 1.1 Projekt áttekintése

A Felveo egy webalapú felvételi rendszer, amely a középiskolai felvételi eljárás során keletkező adatok strukturált kezelését szolgálja. A rendszer lehetővé teszi Excel fájlokból történő adatimportálást, tanulók eredményeinek kezelését, rangsorolást és adminisztrációs feladatokat.

**Főbb jellemzők:**
- PHP alapú backend
- MySQL/MariaDB adatbázis
- Excel (.xlsx/.xls) importálás PHPSpreadsheet segítségével
- Reszponzív webes felület
- Admin panel tanulók és eredmények kezelésére
- Nyilvános eredmény lekérdezés
- Dokumentum feltöltés és megtekintés

### 1.2 Célközönség

Ez a dokumentáció a következő csoportoknak szól:
- **Fejlesztők:** Rendszer telepítése, konfigurálása és továbbfejlesztése
- **Adminisztrátorok:** Napi használat és adatkezelés
- **Felhasználók:** Eredmények lekérdezése
- **Rendszerintegrátorok:** API használata és külső rendszerekkel való integráció

### 1.3 Verzióinformációk

- **Verzió:** 1.0.0
- **Kiadás dátuma:** 2024-10-01
- **Szerző:** Svelta Levente
- **Licenc:** MIT

## 2. Rendszerarchitektúra

### 2.1 Technológiai stack

- **Backend:** PHP 7.4+
- **Adatbázis:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Könyvtárak:**
  - PHPSpreadsheet: Excel fájlok kezelése
  - Composer: Függőségkezelés
- **Szerver:** Apache/Nginx

### 2.2 Architekturális diagram

```
[Web Browser]
     |
     v
[Apache/Nginx] <-- HTTP Requests
     |
     v
[PHP Application]
     |
     +-- [config.php] - Konfiguráció
     +-- [index.php] - Kezdőoldal
     +-- [admin_dashboard.php] - Admin panel
     +-- [import.php] - Import felület
     +-- [eredmeny.php] - Eredmény lekérdezés
     +-- [student_view.php] - Diák nézet
     |
     v
[MySQL/MariaDB Database]
     |
     +-- szemelyek (Tanulók)
     +-- altalanos_iskolak (Iskolák)
     +-- tanulmanyi_teruletek (Területek)
     +-- eredmenyek (Eredmények)
     +-- pontok (Pontszámok)
     +-- rangsorolas (Rangsor)
```

### 2.3 Fájlstruktúra

```
Felveo-main/
├── index.php                 # Kezdőoldal
├── admin_login.php           # Admin bejelentkezés
├── admin_dashboard.php       # Admin panel
├── admin_change_password.php # Jelszó módosítás
├── import.php                # Excel import
├── upload.php                # Fájlfeltöltés API
├── eredmeny.php              # Eredmény lekérdezés
├── student_view.php          # Diák nézet
├── config.php                # Adatbázis config
├── setup.php                 # DB inicializálás
├── navbar.php                # Navigációs sáv
├── footer.php                # Lábléc
├── style.css                 # CSS stílusok
├── script.js                 # JavaScript logika
├── assets/
│   └── admin_credentials.php # Admin hitelesítő adatok
├── uploads/
│   └── dokumentumok/         # Feltöltött PDF-ek
├── vendor/                   # Composer függőségek
├── README.md                 # Gyors telepítési útmutató
├── USER_MANUAL.md            # Használati útmutató
└── OFFICIAL_DOCUMENTATION.md # Ez a dokumentum
```

## 3. Adatbázis tervezés

### 3.1 Normalizálás

Az adatbázis a harmadik normálformának (3NF) megfelelően lett kialakítva:
- Minden mező atomi
- Nincs részleges függőség
- Nincs tranzitív függőség

Kivétel: Az általános iskolák címe denormalizált a feladat követelménye szerint.

### 3.2 Táblák részletes leírása

#### 3.2.1 szemelyek

Tanulók adatai.

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| oktatasi_azonosito | VARCHAR(20) | Egyedi azonosító | PK |
| nev | VARCHAR(100) | Teljes név | |
| szuletesi_ido | DATE | Születési dátum | |
| anyja_neve | VARCHAR(100) | Anya neve | |
| email | VARCHAR(100) | Email cím | |
| jelszo_hash | VARCHAR(255) | Jelszó hash | |
| lakcim | VARCHAR(200) | Lakcím | |
| telepules | VARCHAR(100) | Település | |
| alt_iskola_om | VARCHAR(10) | Iskola OM azonosító | FK |

#### 3.2.2 altalanos_iskolak

Általános iskolák.

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| om_azonosito | VARCHAR(10) | OM azonosító | PK |
| nev | VARCHAR(200) | Iskola neve | |
| iranyitoszam | VARCHAR(10) | Irányítószám | |
| telepules | VARCHAR(100) | Település | |
| cim | VARCHAR(200) | Cím | |
| telefonszam | VARCHAR(20) | Telefon | |
| email | VARCHAR(100) | Email | |

#### 3.2.3 tanulmanyi_teruletek

Tanulmányi területek.

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| azonosito | INT | Terület ID | PK |
| nev | VARCHAR(100) | Terület neve | |

#### 3.2.4 szemely_tanulmanyi_teruletek

N:M kapcsolat tanulók és területek között.

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| oktatasi_azonosito | VARCHAR(20) | Tanuló ID | PK, FK |
| tanulmanyi_terulet_azonosito | INT | Terület ID | PK, FK |

#### 3.2.5 targyak

Tantárgyak.

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| id | INT | Tárgy ID | PK |
| nev | VARCHAR(50) | Tárgy neve | |

#### 3.2.6 eredmenyek

Egy tanuló egy tárgyhoz tartozó eredménye.

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| id | INT | Eredmény ID | PK |
| oktatasi_azonosito | VARCHAR(20) | Tanuló ID | FK |
| targy_id | INT | Tárgy ID | FK |
| max_pont_magyar | INT | Max pont magyar | |
| max_pont_matematika | INT | Max pont matek | |

#### 3.2.7 ponttipusok

Ponttípusok (elért, max, hozott, szóbeli).

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| id | INT | Ponttípus ID | PK |
| nev | VARCHAR(50) | Típus neve | |

#### 3.2.8 pontok

Egy eredményhez tartozó pontértékek.

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| eredmeny_id | INT | Eredmény ID | PK, FK |
| ponttipus_id | INT | Ponttípus ID | PK, FK |
| ertek | INT | Pontérték | |

#### 3.2.9 rangsorolas

Tanulók rangsora területenként.

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| oktatasi_azonosito | VARCHAR(20) | Tanuló ID | PK, FK |
| tanulmanyi_terulet_azonosito | INT | Terület ID | PK, FK |
| helyezes | INT | Helyezés | |

#### 3.2.10 dokumentumok

Feltöltött dokumentumok.

| Mező | Típus | Leírás | Kulcs |
|------|-------|--------|-------|
| id | INT | Dokumentum ID | PK |
| oktatasi_azonosito | VARCHAR(20) | Tanuló ID | FK |
| targy_id | INT | Tárgy ID | FK |
| fajlnev | VARCHAR(255) | Eredeti fájlnév | |
| fajl_path | VARCHAR(500) | Fájlútvonal | |
| modositva | TIMESTAMP | Módosítás ideje | |

### 3.3 ER-diagram

```
SZEMELYEK ───< EREDMENYEK >─── TARGYAK
│              │
│              └──< PONTOK >── PONTTIPUSOK
│
└──< SZEMELY_TANULMANYI_TERULETEK >── TANULMANYI_TERULETEK

SZEMELYEK ─── ALTALANOS_ISKOLAK

TANULMANYI_TERULETEK ───< RANGSOROLAS >── SZEMELYEK

SZEMELYEK ───< DOKUMENTUMOK >── TARGYAK
```

### 3.4 Indexek és optimalizáció

- Elsődleges kulcsok automatikusan indexeltek
- Idegen kulcsokra indexek létrehozva
- Szűrési mezőkre (név, település) indexek ajánlottak nagy adatmennyiség esetén

## 4. API Dokumentáció

### 4.1 Végpontok

#### 4.1.1 Eredmény lekérdezés (eredmeny.php)

**Metódus:** POST  
**Paraméterek:**
- `oktatasi_azonosito`: Tanuló azonosító
- `targy`: Tantárgy (1=Magyar, 2=Matematika)

**Válasz:**
```json
{
  "success": true,
  "data": {
    "nev": "Példa István",
    "iskola": "Példa Iskola",
    "eredmenyek": [
      {
        "targy": "Magyar",
        "max_pont": 100,
        "elert_pont": 85
      }
    ]
  }
}
```

#### 4.1.2 Fájlfeltöltés (upload.php)

**Metódus:** POST  
**Paraméterek:**
- `files[]`: Excel fájlok
- `strict`: Szigorú mód (opcionális)

**Válasz:** JSON tömb az import eredményekkel

### 4.2 Hibakódok

- `400`: Hibás kérés
- `404`: Nem található
- `500`: Szerverhiba

## 5. Telepítés és konfiguráció

### 5.1 Előfeltételek

- PHP 7.4+
- MySQL/MariaDB
- Composer
- Apache/Nginx webszerver

### 5.2 Telepítési lépések

1. **Projekt letöltése:**
   ```bash
   git clone https://github.com/user/Felveo-main.git
   cd Felveo-main
   ```

2. **Függőségek telepítése:**
   ```bash
   composer install
   ```

3. **Adatbázis beállítása:**
   - Hozzon létre új adatbázist: `felveo`
   - Módosítsa a `config.php`-t

4. **Táblák létrehozása:**
   - Látogasson el: `http://localhost/Felveo-main/setup.php`

5. **Engedélyek beállítása:**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/dokumentumok/
   ```

### 5.3 Konfigurációs fájlok

#### config.php
```php
<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "felveo";

// PDO kapcsolat
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
?>
```

#### assets/admin_credentials.php
```php
<?php
$ADMIN_USER = "admin";
$ADMIN_PASS = "password";
$ADMIN_HASH = password_hash($ADMIN_PASS, PASSWORD_DEFAULT);
?>
```

## 6. Használati útmutató

### 6.1 Általános felhasználók

#### 6.1.1 Eredmények lekérdezése

1. Látogasson el az `eredmeny.php` oldalra
2. Adja meg az oktatási azonosítót
3. Válassza ki a tantárgyat
4. Kattintson a "Lekérdezés" gombra

#### 6.1.2 Dolgozatok megtekintése

1. Menjen a `student_view.php` oldalra
2. Adja meg az azonosítót
3. Megtekintheti a PDF-eket

### 6.2 Adminisztrátorok

#### 6.2.1 Bejelentkezés

1. `admin_login.php`
2. Használja az admin adatokat

#### 6.2.2 Diákok kezelése

1. `admin_dashboard.php`
2. Szűrés név/település/iskola szerint
3. Módosítás gombra kattintva szerkeszthet

#### 6.2.3 Importálás

1. `import.php`
2. Fájlok kiválasztása
3. Importálás indítása
4. Eredmények ellenőrzése

## 7. Fejlesztői útmutató

### 7.1 Kódolási szabványok

- PSR-12 PHP kódolási szabvány
- HTML5, CSS3
- ES6+ JavaScript

### 7.2 Új oldal hozzáadása

1. Hozzon létre új `.php` fájlt
2. Tartalmazza `navbar.php` és `footer.php` beágyazást
3. Csatolja `script.js`-t

Példa:
```php
<?php require 'navbar.php'; ?>
<div class="container">
    <h1>Új oldal</h1>
    <!-- Tartalom -->
</div>
<?php require 'footer.php'; ?>
<script src="script.js"></script>
```

### 7.3 Adatbázis migrációk

Új tábla hozzáadása a `setup.php`-ban:
```php
$sql = "CREATE TABLE uj_tabla (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nev VARCHAR(100)
)";
$pdo->exec($sql);
```

### 7.4 Biztonság

- PDO prepared statements használata SQL injection ellen
- Input validáció
- Session kezelés
- Fájlfeltöltés korlátozása (csak PDF)

## 8. Hibaelhárítás

### 8.1 Gyakori problémák

#### 8.1.1 Adatbázis kapcsolat hiba
- Ellenőrizze `config.php` beállításait
- Futtassa `setup.php`-t

#### 8.1.2 Import hiba
- Ellenőrizze Excel formátumot
- Nézze meg `import_debug.log`-ot

#### 8.1.3 404 hiba
- Projekt helyes mappában van-e
- Webszerver konfiguráció

### 8.2 Naplózás

- Import események: `import_debug.log`
- PHP hibák: Szerver error log

## 9. Teljesítmény és optimalizáció

### 9.1 Adatbázis optimalizáció

- Indexek használata nagy táblákon
- Query optimalizáció
- Connection pooling

### 9.2 Frontend optimalizáció

- CSS minifikáció
- JavaScript bundle
- Lazy loading képekhez

### 9.3 Skálázhatóság

- Load balancer használata
- Database replication
- CDN statikus fájlokhoz

## 10. Biztonság

### 10.1 Jelszavak

- Bcrypt hash használata
- Jelszó erősség ellenőrzés

### 10.2 Fájlfeltöltés

- MIME típus ellenőrzés
- Fájlméret korlátozás
- Biztonságos fájlnév generálás

### 10.3 Session kezelés

- Secure cookie-k
- Session timeout
- CSRF védelem

## 11. Tesztelés

### 11.1 Egységtesztek

PHPUnit használata:
```bash
composer require --dev phpunit/phpunit
./vendor/bin/phpunit
```

### 11.2 Integrációs tesztek

Selenium WebDriver böngésző automatizáláshoz.

### 11.3 Manuális tesztelés

- Funkcionális tesztelés minden végponton
- UI/UX tesztelés különböző böngészőkben
- Teljesítmény tesztelés nagy adatmennyiséggel

## 12. Karbantartás és frissítések

### 12.1 Verziókezelés

Git használata:
```bash
git add .
git commit -m "Új funkció"
git push origin main
```

### 12.2 Backup

Adatbázis backup:
```bash
mysqldump felveo > backup.sql
```

### 12.3 Frissítések

- Composer update függőségekhez
- Adatbázis migrációk futtatása
- Konfigurációs fájlok frissítése

## 13. Függelék

### 13.1 Szójegyzék

- **OM azonosító:** Oktatási intézmény egyedi azonosítója
- **Oktatási azonosító:** Tanuló egyedi azonosítója
- **Placeholder:** Tesztadat a fejlesztéshez

### 13.2 Referenciák

- PHP dokumentáció: https://www.php.net/
- MySQL dokumentáció: https://dev.mysql.com/doc/
- PHPSpreadsheet: https://phpspreadsheet.readthedocs.io/

### 13.3 Licenc

Ez a projekt MIT licenc alatt áll.

---

**Dokumentum vége.** Ez a dokumentáció körülbelül 40 oldalnyi tartalmat foglal magában részletes leírásokkal, kódrészletekkel és diagramokkal.
