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
$uploaded = $_FILES['excel'];
$countFiles = is_array($uploaded['tmp_name']) ? count($uploaded['tmp_name']) : 0;

if (!$type) exit("Nincs kiválasztva import típus.");

$summary = ['files' => [], 'imported' => 0, 'skipped' => 0];

for ($fi = 0; $fi < $countFiles; $fi++) {
    $tmp = $uploaded['tmp_name'][$fi];
    $origName = $uploaded['name'][$fi] ?? '';
    if (!is_uploaded_file($tmp) && !file_exists($tmp)) {
        log_msg("Skipping non-uploaded file: $tmp (orig: $origName)");
        continue;
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    try {
        if ($ext === 'csv') {
            // Read a sample for encoding/delimiter detection
            $sample = file_get_contents($tmp, false, null, 0, 20000);
            if ($sample === false) $sample = '';

                // Respect manual override from form if provided
                $enc = null;
                $postedEnc = $_POST['csv_encoding'] ?? 'auto';
                if ($postedEnc !== 'auto') {
                    $enc = $postedEnc;
                    log_msg("CSV manual encoding override for {$origName}: {$enc}");
                } else {
                    if (function_exists('mb_detect_encoding')) {
                        $enc = false;
                        $candidateEnc = ['UTF-8','CP1250','Windows-1250','ISO-8859-2','ISO-8859-1','ISO-8859-15'];
                        foreach ($candidateEnc as $cand) {
                            try {
                                $res = mb_detect_encoding($sample, $cand, true);
                            } catch (\Throwable $t) {
                                // some PHP builds may throw for unknown encoding names; skip
                                $res = false;
                            }
                            if ($res !== false && $res !== null) {
                                $enc = $res;
                                break;
                            }
                        }
                    }
                    if (!$enc) $enc = 'UTF-8';
                }

            // prepare file to load: convert to UTF-8 if necessary
            $loadFile = $tmp;
                if (strtoupper($enc) !== 'UTF-8') {
                $full = file_get_contents($tmp);
                if ($full !== false) {
                    $converted = null;
                    if (function_exists('mb_convert_encoding')) {
                        $converted = mb_convert_encoding($full, 'UTF-8', $enc);
                    }
                    if ($converted !== null) {
                        $tmpConv = tempnam(sys_get_temp_dir(), 'csv_');
                        file_put_contents($tmpConv, $converted);
                        $loadFile = $tmpConv;
                            log_msg("CSV converted to UTF-8 for {$origName}: temp file {$tmpConv}, detected encoding={$enc}");
                    }
                }
            }

            // Detect delimiter by sampling first few lines
            $sampleFull = file_get_contents($tmp, false, null, 0, 20000) ?: '';
            $lines = preg_split('/\r\n|\n|\r/', $sampleFull);
            $candidates = [',', ';', "\t", '|'];
            $postedDelim = $_POST['csv_delimiter'] ?? 'auto';
            if ($postedDelim !== 'auto') {
                $best = $postedDelim;
                log_msg("CSV manual delimiter override for {$origName}: {$best}");
            } else {
                $candidates = [',', ';', "\t", '|'];
                $best = ',';
                $bestScore = -1;
                foreach ($candidates as $cand) {
                    $score = 0; $cnt = 0;
                    foreach ($lines as $ln) {
                        $ln = trim($ln);
                        if ($ln === '') continue;
                        $score += substr_count($ln, $cand);
                        $cnt++;
                        if ($cnt >= 5) break;
                    }
                    $avg = $cnt > 0 ? ($score / $cnt) : 0;
                    if ($avg > $bestScore) { $bestScore = $avg; $best = $cand; }
                }
            }

            log_msg("CSV auto-detected for {$origName}: encoding={$enc}, delimiter=" . str_replace("\t","\\t", $best));
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter($best);
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
            $spreadsheet = $reader->load($loadFile);
            log_msg("CSV loaded for {$origName} from {$loadFile}");

            // cleanup converted temporary file
            if (isset($tmpConv) && file_exists($tmpConv)) {
                @unlink($tmpConv);
            }
        } else {
            $spreadsheet = IOFactory::load($tmp);
        }
    } catch (Exception $e) {
        log_msg("Failed to load file ({$origName}): " . $e->getMessage());
        continue;
    }

    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
    if (!is_array($rows) || count($rows) < 2) {
        log_msg("Empty or invalid file, skipping: {$origName}");
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

    log_msg("Parsed header for {$origName}: " . json_encode($header, JSON_UNESCAPED_UNICODE));

    try {
        $pdo->beginTransaction();
        if ($type === 'szemelyek') {
            $count = importSzemelyek($sheet, $rows, $header, $pdo);
        } elseif ($type === 'altalanos_iskolak') {
            $count = importAltalanosIskolak($sheet, $rows, $header, $pdo);
        } elseif ($type === 'eredmenyek') {
            $count = importEredmenyek($sheet, $rows, $header, $pdo);
        } elseif ($type === 'osszes') {
            $countSzemelyek = importSzemelyek($sheet, $rows, $header, $pdo);
            $countEredmenyek = importEredmenyek($sheet, $rows, $header, $pdo);
            $count = $countSzemelyek + $countEredmenyek;
        } else {
            throw new Exception("Ismeretlen import típus: $type");
        }
        $pdo->commit();
        $summary['files'][] = ['file' => $origName, 'imported' => $count];
        $summary['imported'] += $count;

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
                log_msg("Auto-dedupe after import of {$origName}: deleted $deleted duplicate eredmenyek rows");
            } catch (Exception $e) {
                log_msg("Auto-dedupe failed after import of {$origName}: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        log_msg("Error importing {$origName}: " . $e->getMessage());
    }
}

echo "Importálás befejezve. Összes importált sor: " . intval($summary['imported']);


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
    $colTelepules = $find($header, 'telepules');
    $colIranyitoszam = $find($header, 'iranyit') ?? $find($header, 'zip');
    $colIskolaOm = $find($header, 'iskola') ?? $find($header, 'om');

    $stmt = $pdo->prepare(
          "INSERT INTO szemelyek
                (oktatasi_azonosito, nev, szuletesi_ido, anyja_neve, lakcim, email, telepules, alt_iskola_om, jelszo_hash, is_placeholder)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nev = VALUES(nev),
                szuletesi_ido = VALUES(szuletesi_ido),
                anyja_neve = VALUES(anyja_neve),
                lakcim = VALUES(lakcim),
                email = VALUES(email),
                telepules = VALUES(telepules),
                alt_iskola_om = VALUES(alt_iskola_om),
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
        $telepules = $colTelepules ? trim($row[$colTelepules] ?? '') : '';
        $iranyitoszam = $colIranyitoszam ? trim($row[$colIranyitoszam] ?? '') : '';
        $iskola_om = $colIskolaOm ? trim($row[$colIskolaOm] ?? '') : '';

        if ($telepules !== '') {
            ensureTelepulesExists($pdo, $telepules, $iranyitoszam);
        } elseif ($iskola_om !== '') {
            // Ha nincs telepules, de van iskola, akkor az iskola telepuleset hasznaljuk
            $iskolaStmt = $pdo->prepare("SELECT telepules FROM altalanos_iskolak WHERE om_azonosito = ?");
            $iskolaStmt->execute([$iskola_om]);
            $iskolaTelepules = $iskolaStmt->fetchColumn();
            if ($iskolaTelepules) {
                $telepules = $iskolaTelepules;
                ensureTelepulesExists($pdo, $telepules, null);
            }
        }

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
                $telepules ?: null,
                $iskola_om ?: null,
                $jelszo_hash,
                0
            ]);
            $count++;
            log_msg("Szemelyek: executed insert/update for okt={$oktatasi_azonosito}, row={$i}, telepules={$telepules}, iskola_om={$iskola_om}");
        } catch (Exception $e) {
            log_msg("Error inserting szemely row $i: " . $e->getMessage());
        }
    }

    return $count;
}

function ensureTelepulesExists($pdo, $telepules, $iranyitoszam = null) {
    $telepules = trim((string)$telepules);
    if ($telepules === '') {
        return null;
    }
    $iranyitoszam = trim((string)$iranyitoszam);
    if ($iranyitoszam === '') {
        $iranyitoszam = null;
    }

    try {
        $select = $pdo->prepare("SELECT id, iranyitoszam FROM telepulesek WHERE nev = ? LIMIT 1");
        $select->execute([$telepules]);
        $existing = $select->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if (empty($existing['iranyitoszam']) && ($iranyitoszam || !empty($GLOBALS['ZIP_LOOKUP_ENABLED']))) {
                $finalZip = $iranyitoszam ?: lookupIranyitoszam($telepules);
                if ($finalZip) {
                    $update = $pdo->prepare("UPDATE telepulesek SET iranyitoszam = ? WHERE id = ?");
                    $update->execute([$finalZip, $existing['id']]);
                    log_msg("Updated telepulesek iranyitoszam for {$telepules}: {$finalZip}");
                }
            }
            return (int)$existing['id'];
        }

        if (!$iranyitoszam && !empty($GLOBALS['ZIP_LOOKUP_ENABLED'])) {
            $iranyitoszam = lookupIranyitoszam($telepules);
        }

        $insert = $pdo->prepare("INSERT INTO telepulesek (nev, iranyitoszam) VALUES (?, ?)");
        $insert->execute([$telepules, $iranyitoszam]);
        $newId = (int)$pdo->lastInsertId();
        log_msg("Inserted telepulesek row id={$newId} nev={$telepules} iranyitoszam={$iranyitoszam}");
        return $newId;
    } catch (Exception $e) {
        log_msg("ensureTelepulesExists failed for {$telepules}: " . $e->getMessage());
        return null;
    }
}

function lookupIranyitoszam($telepules) {
    global $ZIP_LOOKUP_ENABLED, $ZIP_LOOKUP_PROVIDER, $ZIP_LOOKUP_NOMINATIM_BASE, $ZIP_LOOKUP_USER_AGENT, $ZIP_LOOKUP_TIMEOUT_SECONDS;

    $telepules = trim((string)$telepules);
    if ($telepules === '' || empty($ZIP_LOOKUP_ENABLED)) {
        return null;
    }

    $provider = $ZIP_LOOKUP_PROVIDER ?? 'nominatim';
    if ($provider !== 'nominatim') {
        log_msg("lookupIranyitoszam: unsupported provider {$provider}");
        return null;
    }

    $base = $ZIP_LOOKUP_NOMINATIM_BASE ?? 'https://nominatim.openstreetmap.org/search';
    $query = http_build_query([
        'q' => $telepules . ', Hungary',
        'countrycodes' => 'hu',
        'format' => 'json',
        'addressdetails' => 1,
        'limit' => 5,
        'accept-language' => 'hu'
    ]);
    $url = $base . '?' . $query;
    $headers = [
        'User-Agent: ' . ($ZIP_LOOKUP_USER_AGENT ?? 'Felveo/1.0 (localhost)'),
        'Accept: application/json'
    ];

    $body = fetchUrl($url, $headers, $ZIP_LOOKUP_TIMEOUT_SECONDS ?? 10);
    if (!$body) {
        log_msg("lookupIranyitoszam: empty response for {$telepules}");
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        log_msg("lookupIranyitoszam: invalid json for {$telepules}");
        return null;
    }

    foreach ($data as $item) {
        if (!empty($item['address']['postcode'])) {
            $zip = trim($item['address']['postcode']);
            if (preg_match('/^\d{4}$/', $zip)) {
                log_msg("lookupIranyitoszam: found {$zip} for {$telepules}");
                return $zip;
            }
        }
    }

    return null;
}

function fetchUrl($url, $headers = [], $timeout = 10) {
    $options = [
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => implode("\r\n", $headers)
        ]
    ];
    $context = stream_context_create($options);
    return @file_get_contents($url, false, $context);
}







function importEredmenyek($sheet, $rows, $header, $pdo) {
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
    $colElertPont = $find($header, 'elert');
    $colMaxMagyar = $find($header, 'max_pont_magyar');
    $colMaxMate = $find($header, 'max_pont_matematika');

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

        $elertPont = null;
        if ($colElertPont) {
            $val = $row[$colElertPont] ?? null;
            if ($val !== null && $val !== '') {
                $elertPont = $val;
            }
        }

        $maxMagyar = null;
        if ($colMaxMagyar) {
            $val = $row[$colMaxMagyar] ?? null;
            if ($val !== null && $val !== '') {
                $maxMagyar = $val;
            }
        }

        $maxMate = null;
        if ($colMaxMate) {
            $val = $row[$colMaxMate] ?? null;
            if ($val !== null && $val !== '') {
                $maxMate = $val;
            }
        }

        if ($colMagyar) {
            $val = $row[$colMagyar] ?? null;
            if ($val !== null && $val !== '') {
                $elertPontMagyar = $colElertPont ? ($row[$colElertPont] ?? null) : null;
                $inserted = insertEredmeny($pdo, $oktatasi_azonosito, 'magyar', $elertPontMagyar, $elertPontId, $targyStmt, $maxMagyar, $maxMate);
                $count += $inserted;
            }
        }

        if ($colMate) {
            $val = $row[$colMate] ?? null;
            if ($val !== null && $val !== '') {
                $elertPontMate = $colElertPont ? ($row[$colElertPont] ?? null) : null;
                $inserted = insertEredmeny($pdo, $oktatasi_azonosito, 'matematika', $elertPontMate, $elertPontId, $targyStmt, $maxMagyar, $maxMate);
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

function insertEredmeny($pdo, $oktatasi_azonosito, $targyNev, $elertPont, $elertPontId = null, $targyStmt = null, $maxMagyar = null, $maxMate = null) {
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

    // Update max pontok if provided
    if ($targyNev === 'magyar' && $maxMagyar !== null) {
        $updateMax = $pdo->prepare("UPDATE eredmenyek SET max_pont_magyar = ? WHERE id = ?");
        $updateMax->execute([(int)$maxMagyar, $eredmeny_id]);
        log_msg("insertEredmeny: set max_pont_magyar={$maxMagyar} for eredmeny_id={$eredmeny_id}");
    } elseif ($targyNev === 'matematika' && $maxMate !== null) {
        $updateMax = $pdo->prepare("UPDATE eredmenyek SET max_pont_matematika = ? WHERE id = ?");
        $updateMax->execute([(int)$maxMate, $eredmeny_id]);
        log_msg("insertEredmeny: set max_pont_matematika={$maxMate} for eredmeny_id={$eredmeny_id}");
    }

    return 1;
}

function importAltalanosIskolak($sheet, $rows, $header, $pdo) {
    $count = 0;

    $find = function(array $header, string $needle) {
        $needle = strtolower($needle);
        foreach ($header as $k => $col) {
            if (strpos($k, $needle) !== false) return $col;
        }
        return null;
    };

    $colOm = $find($header, 'om');
    if (!$colOm) {
        log_msg("importAltalanosIskolak: no om_azonosito column found");
        return 0;
    }

    $colNev = $find($header, 'nev');
    $colCim = $find($header, 'cim');
    $colTelefon = $find($header, 'telefon');
    $colEmail = $find($header, 'email');
    $colIranyitoszam = $find($header, 'iranyit') ?? $find($header, 'zip');
    $colTelepules = $find($header, 'telepules');

    $stmt = $pdo->prepare(
        "INSERT INTO altalanos_iskolak
            (om_azonosito, nev, cim, telefonszam, email, iranyitoszam, telepules)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            nev = VALUES(nev),
            cim = VALUES(cim),
            telefonszam = VALUES(telefonszam),
            email = VALUES(email),
            iranyitoszam = VALUES(iranyitoszam),
            telepules = VALUES(telepules)"
    );

    foreach ($rows as $i => $row) {
        if ($i === 1) continue;

        $cell = $sheet->getCell($colOm . $i);
        $om_azonosito = trim($cell->getFormattedValue());
        $om_azonosito = preg_replace('/\D/', '', $om_azonosito);

        if (strlen($om_azonosito) !== 6) {
            log_msg("Skipping row $i – invalid om_azonosito: $om_azonosito");
            continue;
        }

        $nev = $colNev ? trim($row[$colNev] ?? '') : '';
        $cim = $colCim ? trim($row[$colCim] ?? '') : '';
        $telefonszam = $colTelefon ? trim($row[$colTelefon] ?? '') : '';
        $email = $colEmail ? trim($row[$colEmail] ?? '') : '';
        $iranyitoszam = $colIranyitoszam ? trim($row[$colIranyitoszam] ?? '') : '';
        $telepules = $colTelepules ? trim($row[$colTelepules] ?? '') : '';

        if ($telepules !== '') {
            ensureTelepulesExists($pdo, $telepules, $iranyitoszam);
        }

        try {
            $stmt->execute([
                $om_azonosito,
                $nev,
                $cim,
                $telefonszam ?: null,
                $email ?: null,
                $iranyitoszam ?: null,
                $telepules ?: null
            ]);
            $count++;
            log_msg("AltalanosIskolak: executed insert/update for om={$om_azonosito}, row={$i}");
        } catch (Exception $e) {
            log_msg("Error inserting altalanos_iskola row $i: " . $e->getMessage());
        }
    }

    return $count;
}
