# Felveo — Felvételi import és lekérdező rendszer

Rövid, karbantartott leírás a jelenlegi állapot alapján. A README célja, hogy gyorsan beállítható és használható legyen a rendszer helyi fejlesztéshez.

## Főbb pontok

- **Backend:** PHP alapú, MySQL/MariaDB adatbázis támogatás (beállítás: `config.php`)
- **Frontend:** HTML5, CSS3, JavaScript — centralizált stílus (`style.css`), logika (`script.js`)
- **Admin felület:** `admin_dashboard.php` (diákok listája, szűrés — település most a tanuló saját települését mutatja, az iskola kiválasztása nélkül is), `admin_change_password.php` (jelszó módosítás)
- **Importálás:** `import.php` (Excel `.xlsx` / `.xls` támogatás, drag & drop, fájllistázás)
- **Téma:** Világos/sötét mód beépített váltóval a navigációs sávban (navbar)

## Gyors telepítés (lokálisan, XAMPP/Laragon)

### 1. Alapkönyvtár beállítása
Másold a projekt mappát a webszerver `htdocs` (XAMPP) vagy `www` (Laragon) könyvtárába.

### 2. Adatbázis beállítása
Nyisd meg a `config.php` fájlt és állítsd be az adatbázis elérési adatait:
```php
$host = "localhost";      // DB host
$user = "root";           // DB user
$password = "";           // DB jelszó
$dbname = "felveo";       // DB név
```

### 3. Séma inicializálása
Nyisd meg a böngészőt és menj ide: `http://localhost/Felveo-main/setup.php`  
A `setup.php` automatikusan létrehozza a szükséges adatbázis táblázatokat. Kövesd a képernyőn megjelenő utasításokat.

### 4. Függőségek telepítése (ha szükséges)
Ha a `vendor/` mappa hiányzik, telepítsd Composer-rel:
```powershell
cd C:\xampp\htdocs\Felveo-main
composer install
```

### 5. Admin bejelentkezés
- **Admin oldal:** `http://localhost/Felveo-main/admin_login.php`
- **Hitelesítő adatok:** Az `assets/admin_credentials.php` fájlban vannak tárolva
- **Jelszó módosítás:** Az admin belépés után elérhető a `admin_change_password.php` oldal (navbar linkből vagy közvetlen URL)

## Importálás

1. Menj az `import.php` oldalra: `http://localhost/Felveo-main/import.php`
2. **Fájlok kiválasztása:**
   - Kattints a fájl input mezőre vagy drag & drop-pd az Excel fájlokat (`.xlsx` vagy `.xls`)
   - A kiválasztott fájlok megjelennek egy listában
3. **Import indítása:** Kattints az "Importálás" gombra
4. **Eredmény megtekintése:** Az `eredmeny.php` oldalon láthatod az importált vagy sikertelen sorok

## Szerkezet és fájlok

### Rendszerfájlok
- `config.php` — Adatbázis és rendszer beállítások
- `setup.php` — Adatbázis schema inicializálása és migrációk

### Frontend komponensek
- `navbar.php`, `footer.php` — Közös navigációs és lábléc elemek
- `style.css` — Centralizált stílus (CSS változók a sötét/világos témához)
- `script.js` — Frontend logika (téma váltás, import drag & drop, fájllistázás)

### Admin felület
- `admin_login.php` — Admin bejelentkezés
- `admin_dashboard.php` — Studentek listája, szűrés, manipuláció
- `admin_change_password.php` — Jelszó módosítás
- `admin_settings.php` — Egyéb beállítások (ha van)
- `assets/admin_credentials.php` — Admin felhasználónév és jelszó tárolása

### Adatfeldolgozás
- `import.php` — Excel import felület és logika
- `upload.php` — Fájlfeltöltés API
- `placeholders.php`, `placeholders_update.php` — Placeholder/template kezelés

### Segéd és egyéb
- `index.php` — Kezdőoldal / nyilvános felület
- `student_view.php` — Student nézet (ha publikus)
- `eredmeny.php` — Import eredmények megjelenítése
- `is_admin.php` — Admin jogosultság ellenőrzés

### Mappák
- `vendor/` — Composer függőségek (PHPSpreadsheet, stb.)
- `assets/` — Statikus erőforrások, admin hitelesítő adatok
- `uploads/dokumentumok/` — Importált dokumentumok (ne töröld)
- `samples/` — (nem használt, gitignore-ban kizárva)

## Testreszabás és fejlesztés

> **Újdonság (2026‑02‑25):**
> - Az admin dashboardon a "Település" oszlop most már a diák saját települését jeleníti meg. A szűrés ezen a mezőn a tanuló és az iskola településére is keres.
> - Továbbra is látható az iskola neve külön oszlopban, ha a tanulóhoz van rendelve általános iskola.


### Téma beállítás
A sötét/világos mód CSS változókon alapul (`:root` selector). Az aktuális beállítás a `localStorage`-ban tárolódik (`theme: 'dark'` vagy `'light'`).

A navbar-ban található téma váltó gomb (`<button class="theme-btn">`) a `script.js` által vezérelt.

### CSS szervezés
- Alap színek és szélességek: `:root { --bg, --card-bg, --border, --text, ... }`
- Komponens stílus: `.navbar`, `.admin-table`, `.import-form`, stb.
- Responsive: media query-k `768px` és `1024px` töréspontoknál

### Új oldal hozzáadása
1. Hozz létre egy új `.php` fájlt
2. Tartalmazza a `navbar.php` és `footer.php` beágyazást:
   ```php
   <?php require 'navbar.php'; ?>
   <!-- tartalom -->
   <?php require 'footer.php'; ?>
   ```
3. Ha szükséges, csatolja a `script.js`-t: `<script src="script.js"></script>`

## Takarítás és verziókezelés

### Cleanup script
A `cleanup.ps1` PowerShell script segítségével eltávolíthatók a felesleges fájlok és mappák (naplók, minták, feltöltött fájlok). A skript az alábbi kapcsolókat fogadja:
```powershell
.\cleanup.ps1              # Interaktív mód: listáz és kérdez törlés előtt
.\cleanup.ps1 -Force       # Közvetlen törlés kérdezés nélkül
.\cleanup.ps1 -RemoveSamples # samples/ könyvtár törlése
.\cleanup.ps1 -RemoveUploads # uploads/ könyvtár törlése (FIGYELJ!)
```

### .gitignore
A `.gitignore` fájl kizárja a helyi konfigurációt és futási műanyagokat (`config.php`, `import_debug.log`, `uploads/`), valamint a naplókat (`*.log`), SQL dumpokat (`*.sql`) és a `samples/` mappát a verziókezelésből.

## Hibaelhárítás

### Dokumentáció
- A `Docs/` mappában található Word (`.docx`) dokumentumok a projekt leírását és felhasználói útmutatóját tartalmazzák. Érdemes őket PDF‑re vagy Markdownra konvertálni a könnyebb olvashatóság érdekében, de a tartalmuk teljes és naprakész.


### "404 nem találja az oldalt"
- Ellenőrizd, hogy a projekt az `htdocs` vagy `www` mappában van-e
- Bizonyosodj meg, hogy a webszerver futó és elérhető az `http://localhost`

### Adatbázis kapcsolat hibái
- Nyisd meg a `config.php`-t és nézd meg az adatbázis beállításait
- Futtasd a `setup.php`-t az adatbázis inicializálásához
- Ellenőrizd, hogy a MySQL/MariaDB server működik-e

### Import nem működik
- Győződj meg, hogy az Excel fájl `.xlsx` vagy `.xls` formátumú
- Nézd meg az eredmény oldalt (`eredmeny.php`) a részletekért
- Ellenőrizd a szerver log-okat a hibákért

## Készítette

Svelta Levente

## Utolsó frissítés

2026-03-02

> **Végleges verzió:** A helyesírás ellenőrzése és segédfájlok frissítve; projekt kifutásra kész.

---

**Megjegyzés:** Ez a projekt fejlesztés alatt áll. A visszajelzéseket és javaslatokat szívesen fogadjuk.
