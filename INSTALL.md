# 📋 Felveo — Telepítési útmutató

Ez a dokumentum részletesen ismerteti a Felveo rendszer telepítési módszereit.

## 🚀 Leggyorsabb telepítés (ajánlott)

### Követelmények
- PHP 7.4+ (a server-et futtatnia kell)
- MySQL 5.7+ vagy MariaDB 10.3+
- Composer (opcionális, csak ha friss `/vendor` mappára van szükség)

### 1. lépés: Projekt másolása
Másold a projekt mappát a webszerver könyvtárába:
- **XAMPP:** `C:\xampp\htdocs\Felveo-main`
- **Laragon:** `C:\laragon\www\Felveo-main`
- **Máshol:** A webszerver `DocumentRoot` alkönyvtárába

### 2. lépés: Setup varázsló megnyitása
Nyisd meg böngésződet és navigálj ide:
```
http://localhost/Felveo-main/setup.php
```

### 3. lépés: Telepítés kész!
A varázsló automatikusan mindent beállít. Csak add meg az adatbázis adatokat és kattints a telepítésre.

### 4. lépés: Admin bejelentkezés
```
Felhasználónév: admin
Jelszó: secret
```
Bejelentkezés: `http://localhost/Felveo-main/admin_login.php`

⚠️ **FONTOS:** Első bejelentkezés után azonnal módosítsd a jelszót!

---

## 🔧 Alternatív telepítési módok

### Manuális adatbázis létrehozás

Ha valamilyen okból nem működik az automatikus telepítés:

1. Nyisd meg a MySQL-t vagy phpMyAdmin-t
2. Futtasd ezt az SQL parancsot:
   ```sql
   CREATE DATABASE IF NOT EXISTS `felveteli` 
   CHARACTER SET utf8mb4 
   COLLATE utf8mb4_hungarian_ci;
   ```
3. Ezután nyisd meg a `setup.php`-t, amely az SQL sémát importálja

### Composer függőségek frissen telepítése

Ha a `vendor/` mappa sérült vagy hiányzik:

```powershell
cd C:\xampp\htdocs\Felveo-main
composer install
```

---

## ⚙️ Haladó beállítások

### config.php szerkesztése (kézi módszer)

Ha nem szeretnél a varázslót használni, szerkesztheted közvetlenül a `config.php` fájlt:

```php
<?php
$pdo = new PDO(
    "mysql:host=localhost;dbname=felveteli;charset=utf8mb4",
    "root",           // Felhasználónév
    ""                // Jelszó (üres = nincs jelszó)
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Admin hitelesítés
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'secret';
```

Utána nyisd meg: `http://localhost/Felveo-main/setup.php`

### Admin jelszó módosítása később

1. Bejelentkezés az admin panelre
2. Kattints: Admin → Jelszó módosítása
3. Add meg az új jelszót

---

## 🐛 Gyakori problémák és megoldások

### "Adatbázis hiba: Connection refused"
- **Ok:** MySQL szerver nem fut
- **Megoldás:** Indítsd el az XAMPP/Laragon szolgáltatásokat

### "Nem sikerült beolvasni az SQL fájlt"
- **Ok:** Az `assets/felveteli.sql` fájl hiányzik
- **Megoldás:** Ellenőrizd, hogy az `assets` mappa létezik és tartalmazza az SQL fájlt

### "SQLSTATE[HY000]: General error"
- **Ok:** Jelszó vagy felhasználónév hiba
- **Megoldás:** Ellenőrizd az adatbázis bejelentkezési adatokat

### "Permission denied" feltöltési mappához
- **Ok:** A `uploads/dokumentumok` mappa nincs létrehozva vagy nincs írási joga
- **Megoldás:** Futtasd a `setup.php`-t, amely automatikusan létrehozza a szükséges mappákat

### "uploads/dokumentumok" nem jön létre
- **Ok:** Windows-on az engedélyek korlátozottak
- **Megoldás:** Manuálisan hozd létre a mappákat:
  ```
  C:\xampp\htdocs\Felveo-main\uploads\dokumentumok
  ```

---

## 📝 Ellenőrzőlista

A telepítés után ellenőrizd az alábbi pontokat:

- [ ] A `config.php` fájl létezik
- [ ] Az admin bejelentkezés működik
- [ ] A főoldal (`index.php`) betöltődik
- [ ] Az `uploads/dokumentumok` mappa létezik
- [ ] Az adatbázis tábláinak száma 10+ (futtatás után: `setup.php`)

---

## 📞 Segítség

Ha problémád van:
1. Ellenőrizd a böngésző konzolt (F12 → Console)
2. Nézd meg a PHP error log-ot
3. Kérdezz az admin interfészen megjelenő hibaüzeneteket

---

**Verzió:** 1.0  
**Utolsó frissítés:** 2026-04-21
