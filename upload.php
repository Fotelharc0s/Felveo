<?php
require 'vendor/autoload.php';
require 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
// Only allow admin users to run imports
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo "Forbidden: admin only";
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;


/** Log to a file for debugging */
$logfile = __DIR__ . '/import_debug.log';
function log_msg($msg) {
    global $logfile;
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents($logfile, $line, FILE_APPEND);
}

if (!isset($_FILES['excel'])) {
    exit("Nincs feltöltött fájl.");
}

$type = isset($_POST['import_type']) ? (string)$_POST['import_type'] : '';
$files = $_FILES['excel']['tmp_name'];
if (!is_array($files)) $files = [$files];

$strict = isset($_POST['strict']) && ($_POST['strict'] == '1' || $_POST['strict'] === 1);

if (!$type) exit("Nincs kiválasztva import típus.");

$summary = ['files' => [], 'imported' => 0, 'skipped' => 0];

foreach ($files as $file) {
    if (!is_uploaded_file($file) && !file_exists($file)) {
        log_msg("Skipping non-uploaded file: $file");
        continue;
    }

    try {
        $spreadsheet = IOFactory::load($file);
    } catch (Exception $e) {
        log_msg("Failed to load spreadsheet ($file): " . $e->getMessage());
        continue;
    }

    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
    if (!is_array($rows) || count($rows) < 2) {
        log_msg("Empty or invalid Excel file, skipping: $file");
        continue;
    }

    // Build header map: normalized header name -> column letter
    $header = [];
    foreach ($rows[1] as $col => $val) {
        if ($val === null) continue;
        $norm = strtolower(trim($val));
        $norm = preg_replace('/[^a-z0-9_áéíóöőúüűçàè]/iu', '_', $norm);
        $header[$norm] = $col;
    }

    log_msg("Parsed header for $file: " . json_encode($header, JSON_UNESCAPED_UNICODE));

    try {
        $pdo->beginTransaction();
        if ($type === 'szemelyek') {
            $count = importSzemelyek($sheet, $rows, $header, $pdo);
        } elseif ($type === 'eredmenyek') {
            $count = importEredmenyek($sheet, $rows, $header, $pdo, $strict);
        } else {
            throw new Exception("Ismeretlen import típus: $type");
        }
        $pdo->commit();
        $summary['files'][] = ['file' => $file, 'imported' => $count];
        $summary['imported'] += $count;

        // If we just imported eredmenyek, remove duplicate eredmenyek rows (keep lowest id).
        if ($type === 'eredmenyek') {
            try {
                $delSql = "DELETE e1 FROM eredmenyek e1
                    INNER JOIN eredmenyek e2
                    ON e1.oktatasi_azonosito = e2.oktatasi_azonosito
                    AND e1.targy_id = e2.targy_id
                    AND e1.id > e2.id";
                $deleted = $pdo->exec($delSql);
                $deleted = $deleted === false ? 0 : (int)$deleted;
                $summary['deduped'] = ($summary['deduped'] ?? 0) + $deleted;
                log_msg("Auto-dedupe after import of $file: deleted $deleted duplicate eredmenyek rows");
            } catch (Exception $e) {
                log_msg("Auto-dedupe failed after import of $file: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        log_msg("Error importing $file: " . $e->getMessage());
    }
}

echo "Importálás befejezve. Összesen importált sorok: " . intval($summary['imported']);


/* ===== FÜGGVÉNYEK ===== */

function importSzemelyek($sheet, $rows, $header, $pdo) {
    $count = 0;

    // helper: find header column by substring matching
    $find = function(array $header, string $needle) {
        $needle = strtolower($needle);
        foreach ($header as $k => $col) {
            if (strpos($k, $needle) !== false) return $col;
        }
        return null;
    };

    $colOkt = $find($header, 'oktat');
    if (!$colOkt) {
        log_msg("importSzemelyek: no oktatasi_azonosito column found");
        return 0;
    }

    $colNev = $find($header, 'nev');
    $colSzul = $find($header, 'szulet');
    $colAnyja = $find($header, 'anyja');
    $colEmail = $find($header, 'email');
    $colLakcim = $find($header, 'lakcim');

    $stmt = $pdo->prepare(
          "INSERT INTO szemelyek
                (oktatasi_azonosito, nev, szuletesi_ido, anyja_neve, lakcim, email, jelszo_hash, is_placeholder)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nev = VALUES(nev),
                szuletesi_ido = VALUES(szuletesi_ido),
                anyja_neve = VALUES(anyja_neve),
                lakcim = VALUES(lakcim),
                email = VALUES(email),
                jelszo_hash = VALUES(jelszo_hash),
                is_placeholder = VALUES(is_placeholder)"
    );

    foreach ($rows as $i => $row) {
        if ($i === 1) continue;

        $cell = $sheet->getCell($colOkt . $i);
        $oktatasi_azonosito = trim($cell->getFormattedValue());
        $oktatasi_azonosito = preg_replace('/\D/', '', $oktatasi_azonosito);

        if (strlen($oktatasi_azonosito) !== 11) {
            log_msg("Skipping row $i – invalid oktatasi_azonosito: $oktatasi_azonosito");
            continue;
        }

        $nev = $colNev ? trim($row[$colNev] ?? '') : '';
        $rawSzul = $colSzul ? ($row[$colSzul] ?? '') : '';
        $anyja_neve = $colAnyja ? trim($row[$colAnyja] ?? '') : '';
        $email = $colEmail ? trim($row[$colEmail] ?? '') : '';
        $lakcim = $colLakcim ? trim($row[$colLakcim] ?? '') : '';

        // ensure unique, non-empty email so UNIQUE index doesn't collide
        if ($email === '' || $email === null) {
            $email = $oktatasi_azonosito . '@import.local';
        } else {
            $email = trim($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                log_msg("importSzemelyek: invalid email for okt={$oktatasi_azonosito}: {$email}, using generated");
                $email = $oktatasi_azonosito . '@import.local';
            } else {
                // ensure not used by someone else
                $eChk = $pdo->prepare("SELECT oktatasi_azonosito FROM szemelyek WHERE email = ? LIMIT 1");
                $eChk->execute([$email]);
                $owner = $eChk->fetchColumn();
                if ($owner && $owner !== $oktatasi_azonosito) {
                    log_msg("importSzemelyek: email {$email} already used by okt={$owner}, generating fallback");
                    $email = $oktatasi_azonosito . '@import.local';
                }
            }
        }

        $jelszo_hash = '';

        // parse birthdate: Excel numeric or string
        $szuletesi_ido = null;
        if ($rawSzul !== null && $rawSzul !== '') {
            if (is_numeric($rawSzul)) {
                try {
                    $dt = Date::excelToDateTimeObject((float)$rawSzul);
                    $szuletesi_ido = $dt->format('Y-m-d');
                } catch (Exception $e) {
                    $szuletesi_ido = parseDate($rawSzul);
                }
            } else {
                $szuletesi_ido = parseDate($rawSzul);
            }
        }

        try {
            $stmt->execute([
                $oktatasi_azonosito,
                $nev,
                $szuletesi_ido,
                $anyja_neve,
                $lakcim,
                    $email,
                    $jelszo_hash,
                    0
            ]);
            $count++;
            log_msg("Szemelyek: executed insert/update for okt={$oktatasi_azonosito}, row={$i}");
        } catch (Exception $e) {
            log_msg("Error inserting szemely row $i: " . $e->getMessage());
        }
    }

    return $count;
}







function importEredmenyek($sheet, $rows, $header, $pdo, $strict = false) {
    $count = 0;

    $find = function(array $header, string $needle) {
        $needle = strtolower($needle);
        foreach ($header as $k => $col) {
            if (strpos($k, $needle) !== false) return $col;
        }
        return null;
    };

    $colOkt = $find($header, 'oktat');
    if (!$colOkt) {
        log_msg("importEredmenyek: no oktatasi_azonosito column found");
        return 0;
    }

    $colMagyar = $find($header, 'magyar');
    $colMate = $find($header, 'matemat');

    // cache targyak
    $targyStmt = $pdo->prepare("SELECT id FROM targyak WHERE nev = ?");
    $ponttipusStmt = $pdo->prepare("SELECT id FROM ponttipusok WHERE nev = ?");
    $ponttipusStmt->execute(['elert_pont']);
    $elertPontId = $ponttipusStmt->fetchColumn();

    foreach ($rows as $i => $row) {
        if ($i == 1) continue;

        $cell = $sheet->getCell($colOkt . $i);
        $raw = $cell->getFormattedValue();
        $oktatasi_azonosito = preg_replace('/\D/', '', (string)$raw);
        log_msg("Eredmenyek row $i raw okt: '" . ($raw ?? '') . "' => parsed: $oktatasi_azonosito");
        if (!$oktatasi_azonosito) {
            log_msg("Eredmenyek: skipping row $i — empty oktatasi_azonosito");
            continue;
        }

        if ($colMagyar) {
            $val = $row[$colMagyar] ?? null;
            if ($val !== null && $val !== '') {
                $inserted = insertEredmeny($pdo, $oktatasi_azonosito, 'magyar', $val, $elertPontId, $targyStmt, $strict);
                $count += $inserted;
            }
        }

        if ($colMate) {
            $val = $row[$colMate] ?? null;
            if ($val !== null && $val !== '') {
                $inserted = insertEredmeny($pdo, $oktatasi_azonosito, 'matematika', $val, $elertPontId, $targyStmt, $strict);
                $count += $inserted;
            }
        }
    }

    return $count;
}

/**
 * Helper function to parse dates from various formats
 */
function parseDate($dateStr) {
    if (empty($dateStr)) return null;
    
    $dateStr = trim($dateStr);
    
    // Try Hungarian date format YYYY.MM.DD
    if (preg_match('/^(\d{4})\.(\d{2})\.(\d{2})$/', $dateStr, $m)) {
        return "{$m[1]}-{$m[2]}-{$m[3]}";
    }
    
    // Try other common formats
    $ts = strtotime($dateStr);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }
    
    return null;
}

function insertEredmeny($pdo, $oktatasi_azonosito, $targyNev, $elertPont, $elertPontId = null, $targyStmt = null, $strict = false) {
    if ($elertPont === null || $elertPont === '') return 0;

    // normalize numeric score
    if (is_string($elertPont)) $elertPont = trim($elertPont);
    if (!is_numeric($elertPont)) return 0;

    if ($targyStmt === null) $targyStmt = $pdo->prepare("SELECT id FROM targyak WHERE nev = ?");
    $targyStmt->execute([$targyNev]);
    $targy_id = $targyStmt->fetchColumn();
    if (!$targy_id) {
        log_msg("insertEredmeny: unknown targy: $targyNev");
        return 0;
    }
    // Ensure the person exists. If missing, create a minimal placeholder
    // record so foreign key constraints won't fail. This avoids requiring
    // a separate 'szemelyek' import when only eredmenyek are available.
    try {
        $chk = $pdo->prepare("SELECT 1 FROM szemelyek WHERE oktatasi_azonosito = ? LIMIT 1");
        $chk->execute([$oktatasi_azonosito]);
        $exists = (bool)$chk->fetchColumn();
        if (!$exists) {
            if ($strict) {
                log_msg("insertEredmeny: strict mode - missing szemely for okt={$oktatasi_azonosito}");
                throw new Exception("Strict import: szemely hiányzik az oktatasi_azonosito={$oktatasi_azonosito}");
            }
            $insSzem = $pdo->prepare(
                "INSERT INTO szemelyek (oktatasi_azonosito, nev, szuletesi_ido, anyja_neve, lakcim, email, jelszo_hash, is_placeholder)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            // Minimal placeholders: empty strings and a safe default birthdate.
            $safeDate = '1900-01-01';
            $useEmail = $oktatasi_azonosito . '@import.local';
            $insSzem->execute([
                $oktatasi_azonosito,
                '',
                $safeDate,
                '',
                '',
                $useEmail,
                '',
                1
            ]);
            log_msg("insertEredmeny: created placeholder szemely for okt={$oktatasi_azonosito} email={$useEmail}");
        }
    } catch (Exception $e) {
        log_msg("insertEredmeny: failed ensuring szemely for okt={$oktatasi_azonosito}: " . $e->getMessage());
        return 0;
    }

    // Use an atomic INSERT ... ON DUPLICATE KEY UPDATE to avoid race-conditions
    // and duplicate rows when concurrent imports run. This requires a UNIQUE
    // key on (oktatasi_azonosito, targy_id) in the `eredmenyek` table.
    try {
        $ins = $pdo->prepare("INSERT INTO eredmenyek (oktatasi_azonosito, targy_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        $ins->execute([$oktatasi_azonosito, $targy_id]);
        $eredmeny_id = $pdo->lastInsertId();
        if ($eredmeny_id) {
            log_msg("insertEredmeny: ensured eredmeny id {$eredmeny_id} for okt={$oktatasi_azonosito}, targy={$targyNev}");
        } else {
            log_msg("insertEredmeny: failed to obtain eredmeny id for okt={$oktatasi_azonosito}, targy={$targyNev}");
            return 0;
        }
    } catch (Exception $e) {
        log_msg("insertEredmeny: error creating/selecting eredmeny for okt={$oktatasi_azonosito}, targy={$targyNev}: " . $e->getMessage());
        return 0;
    }

    if ($elertPontId === null) {
        $pt = $pdo->prepare("SELECT id FROM ponttipusok WHERE nev = ?");
        $pt->execute(['elert_pont']);
        $elertPontId = $pt->fetchColumn();
    }
    if (!$elertPontId) {
        log_msg("insertEredmeny: missing ponttipus elert_pont");
        return 0;
    }

    // upsert pontok (ponttipus per eredmeny should be unique)
    $insPont = $pdo->prepare("INSERT INTO pontok (eredmeny_id, ponttipus_id, ertek) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ertek = VALUES(ertek)");
    $insPont->execute([$eredmeny_id, $elertPontId, (int)$elertPont]);
    log_msg("insertEredmeny: set pont for eredmeny_id={$eredmeny_id}, ponttipus_id={$elertPontId}, ertek=" . (int)$elertPont);
    return 1;
}
