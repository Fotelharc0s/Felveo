# Felveo — Felvételi import és lekérdező rendszer

Rövid, karbantartott leírás a jelenlegi állapot alapján. A README célja, hogy gyorsan beállítható és használható legyen a rendszer helyi fejlesztésre.

## Főbb pontok (frissítve)
- PHP alapú backend, MySQL/MariaDB támogatható (az adatbázis beállításait a `config.php` határozza meg)
- Frontend: HTML/CSS/JS (a központi stílus a `style.css`, a viselkedés a `script.js` fájlban)
- Admin felület: `admin_dashboard.php`, jelszó módosítás: `admin_change_password.php`
- Importálás: `import.php` (Excel `.xlsx` / `.xls` támogatás)

## Gyors telepítés (lokálisan, XAMPP/Laragon)
1. Másold a projekt mappát a webszerver `htdocs`/`www` könyvtárába.
2. Állítsd be az adatbázis elérését a `config.php` fájlban (DB host, user, password, dbname).
3. Nyisd meg a böngészőt: `http://localhost/Felveo-main/setup.php` — a `setup.php` létrehozza a szükséges mezőket és opcionálisan módosítja a sémát.
4. Ha a `vendor/` mappa nincs telepítve, futtass Composer-t:

```powershell
cd C:\xampp\htdocs\Felveo-main
composer install
```

5. Admin belépés: az admin hitelesítő adatok a `assets/admin_credentials.php` fájlban vannak. A jelszó módosításához használd a `admin_change_password.php` oldalt.

## Importálás
- Menj az `import.php` oldalra, válaszd ki az Excel fájlokat és indítsd el az importot.
- A felület támogatja a drag & drop-ot és megjeleníti a kiválasztott fájlok listáját.

## Fájlok és mappák (fontos)
- `navbar.php`, `footer.php` — közös komponensek
- `style.css`, `script.js` — frontend stílus és viselkedés
- `import.php`, `upload.php` — import és feltöltés logika
- `assets/` — statikus erőforrások, `admin_credentials.php` tartalmazhat admin belépési információkat (ha használatban van)
- `uploads/dokumentumok/` — feltöltött dokumentumok (ne töröld)

## Takarítás és segéd fájlok
Létrehoztam `cleanup.ps1` segéd scriptet a gyökérben, ami segít eltávolítani felesleges, top-level fájlokat (például `.sql`, `.log`, screenshotok). Példák:

```powershell
.\cleanup.ps1        # interaktív törlés (kérdez)
.\cleanup.ps1 -Force # törlés kérdezés nélkül
.\cleanup.ps1 -RemoveSamples # eltávolítja a samples/ könyvtárat, ha van
```

Hozzáadtam egy alap `.gitignore` fájlt, amely kizárja a naplókat (`*.log`) és SQL dumpokat (`*.sql`) valamint a `samples/` mappát.

## További megjegyzések
- A stílus rendszer CSS-változókat használ (`:root`) — a sötét/világos téma a `body.dark` osztállyal működik, és a beállítás a `localStorage`-ban tárolódik.
- Ha a `vendor/` mappa nincs verziókezelve, futtasd `composer install` a dependencyk telepítéséhez.

## Készítette
Svelta Levente

Frissítve: 2026-01-29
