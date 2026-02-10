# Felveo — Használati útmutató

## Bevezetés

A Felveo egy webalapú felvételi rendszer, amely lehetővé teszi Excel fájlokból történő adatimportálást, tanulók eredményeinek kezelését és adminisztrációs feladatokat. Ez az útmutató segít a rendszer gyors megismerésében és használatában.

## Rendszerkövetelmények

- **Webszerver:** Apache vagy Nginx
- **Adatbázis:** MySQL/MariaDB
- **PHP:** 7.4 vagy újabb
- **Böngésző:** Modern böngésző (Chrome, Firefox, Edge)

## Telepítés és beállítás

1. **Projekt letöltése:** Másolja a projekt fájljait a webszerver dokumentumgyökerébe (pl. `htdocs` vagy `www`).

2. **Adatbázis beállítása:**
   - Nyissa meg a `config.php` fájlt.
   - Állítsa be az adatbázis kapcsolatot:
     ```php
     $host = "localhost";
     $user = "root";
     $password = "";
     $dbname = "felveo";
     ```

3. **Adatbázis inicializálása:**
   - Látogasson el a `http://localhost/Felveo-main/setup.php` oldalra.
   - Kövesse a képernyőn megjelenő utasításokat a táblák létrehozásához.

4. **Függőségek telepítése:**
   - Telepítse a Composer-t, ha nincs telepítve.
   - Futtassa: `composer install`

5. **Admin bejelentkezés:**
   - Felhasználónév és jelszó az `assets/admin_credentials.php` fájlban található.

## Használat

### Általános felhasználók

1. **Kezdőoldal:** `index.php` — Itt választhat az eredmények lekérdezése vagy admin bejelentkezés között.

2. **Eredmények lekérdezése:** `eredmeny.php`
   - Adja meg a tanuló oktatási azonosítóját.
   - Válassza ki a tantárgyat (Magyar, Matematika).
   - Kattintson a "Lekérdezés" gombra az eredmények megtekintéséhez.

3. **Dolgozatok megtekintése:** `student_view.php`
   - Adja meg az oktatási azonosítót.
   - Megtekintheti és letöltheti a feltöltött dolgozatokat.

### Adminisztrátorok

1. **Bejelentkezés:** `admin_login.php`
   - Használja az admin hitelesítő adatokat.

2. **Admin panel:** `admin_dashboard.php`
   - **Diákok listázása és szűrése:** Nézze meg a tanulók listáját, szűrje név, település vagy iskola szerint.
   - **Diák módosítása:** Kattintson a "Módosítás" gombra egy tanuló mellett.
     - Szerkessze a személyes adatokat.
     - Módosítsa a pontszámokat.
     - Töltse fel PDF dolgozatokat.
   - **Jelszó módosítása:** `admin_change_password.php` — Változtassa meg az admin jelszót.

3. **Adatimportálás:** `import.php`
   - Válassza ki az Excel fájlokat (.xlsx vagy .xls).
   - Drag & drop vagy fájlválasztó segítségével töltse fel.
   - Kattintson az "Importálás" gombra.
   - Ellenőrizze az eredményeket az `eredmeny.php` oldalon.

## Téma váltás

A navigációs sávban található téma váltó gomb segítségével válthat világos és sötét mód között. A választás automatikusan mentésre kerül.

## Hibaelhárítás

- **404 hiba:** Ellenőrizze, hogy a projekt a helyes mappában van-e.
- **Adatbázis hiba:** Nézze meg a `config.php` beállításait és futtassa a `setup.php`-t.
- **Import hiba:** Győződjön meg róla, hogy az Excel fájl helyes formátumú és tartalmazza a szükséges oszlopokat.

## Támogatás

Ha problémába ütközik, ellenőrizze a naplófájlokat (`import_debug.log`) vagy forduljon a fejlesztőhöz.

---

**Utolsó frissítés:** 2024-10-01
