<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$student = null;
$documents = [];
$message = '';

function is_mobile(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (bool) preg_match('/Mobile|Android|Silk|Kindle|BlackBerry|Opera Mini|Opera Mobi|iPhone|iPad|IEMobile|WPDesktop/i', $ua);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oktatasi_azonosito'])) {
    $okt = trim($_POST['oktatasi_azonosito']);
    
    // Csak számokat tartalmazhat
    if (!preg_match('/^\d{11}$/', $okt)) {
        $message = '✗ Az oktatási azonosító 11 számjegyből állhat!';
    } else {
        $student_stmt = $pdo->prepare("SELECT * FROM szemelyek WHERE oktatasi_azonosito = ?");
        $student_stmt->execute([$okt]);
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            $message = '✗ Nem található tanulórekord ezzel az oktatási azonosítóval!';
        } else {
            // Dokumentumok lekérése
            $docs_stmt = $pdo->prepare("
                SELECT 
                    d.id,
                    d.targy_id,
                    t.nev as targy_nev,
                    d.fajlnev,
                    d.fajl_path,
                    d.feltoltve,
                    e.max_pont_magyar,
                    e.max_pont_matematika,
                    p.ertek as elert_pont
                FROM dokumentumok d
                JOIN targyak t ON d.targy_id = t.id
                LEFT JOIN eredmenyek e ON d.oktatasi_azonosito = e.oktatasi_azonosito AND d.targy_id = e.targy_id
                LEFT JOIN pontok p ON e.id = p.eredmeny_id 
                    AND p.ponttipus_id = (SELECT id FROM ponttipusok WHERE nev = 'elert_pont')
                WHERE d.oktatasi_azonosito = ?
                ORDER BY d.feltoltve DESC
            ");
            $docs_stmt->execute([$okt]);
            $documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diák Dolgozatai - Felveo</title>
    <link rel="stylesheet" href="style.css">
    <style>
    /* Simple modal for desktop PDF preview */
    .pdf-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }
    .pdf-modal.open { display: flex; }
    .pdf-modal .modal-content {
        width: 90%;
        height: 90%;
        background: #fff;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        border-radius: 6px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .pdf-modal .modal-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        background: #f5f5f5;
        border-bottom: 1px solid #e0e0e0;
    }
    .pdf-modal .modal-toolbar .title { font-weight: 600; }
    .pdf-modal .modal-close { background: transparent; border: none; font-size: 18px; cursor: pointer; }
    .pdf-modal iframe { border: 0; width: 100%; height: 100%; flex: 1 1 auto; }
    .document-actions { display:flex; gap:8px; align-items:center; margin-top:8px; }
    .view-btn, .download-btn { padding:6px 10px; border-radius:4px; border:1px solid #ccc; background:#fff; cursor:pointer; text-decoration:none; color:inherit; }
    .view-btn:hover, .download-btn:hover { box-shadow:0 2px 6px rgba(0,0,0,0.08); }
    </style>
</head>
<body class="student-view-body">
    <?php require 'navbar.php'; ?>
    <div class="student-view-wrapper">
    <div class="student-view-container">
        <h1>📄 Dolgozataimon</h1>
        <p class="subtitle">Add meg az oktatási azonosítódat!</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✗') !== false ? 'error' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$student): ?>
            <!-- Bejelentkezési forma -->
            <form method="POST">
                <div class="form-group">
                    <label for="okt">Oktatási Azonosító (11 számjegy)</label>
                    <input 
                        type="text" 
                        id="okt"
                        name="oktatasi_azonosito" 
                        placeholder="pl. 72770184806"
                        maxlength="11"
                        pattern="\d{11}"
                        required
                    >
                </div>
                <button type="submit">🔍 Megkeresés</button>
            </form>
        <?php else: ?>
            <!-- Diák adatai -->
            <div class="student-info">
                <h2>👤 <?php echo htmlspecialchars($student['nev']); ?></h2>
                <div class="info-row">
                    <span class="info-label">Oktatási Azonosító:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['oktatasi_azonosito']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Születési dátum:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['szuletesi_ido'] ?? '-'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Anyja neve:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['anyja_neve'] ?? '-'); ?></span>
                </div>
            </div>
            
            <!-- Dokumentumok -->
            <div class="documents">
                <h3>📋 Dolgozatok (<?php echo count($documents); ?> db)</h3>
                
                <?php if (empty($documents)): ?>
                    <div class="no-documents">
                        <p>Még nincsenek feltöltött dolgozataid</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="document-card">
                            <div class="document-header">
                                <span class="document-subject">
                                    <?php 
                                    $icon = $doc['targy_id'] == 1 ? '📚' : '🔢';
                                    echo $icon . ' ' . htmlspecialchars($doc['targy_nev']);
                                    ?>
                                </span>
                                <span class="document-date">
                                    <?php echo date('Y.m.d H:i', strtotime($doc['feltoltve'])); ?>
                                </span>
                            </div>
                            
                            <div class="document-scores">
                                <div class="score-item">
                                    <div class="score-label">Max pont</div>
                                    <div class="score-value">
                                        <?php 
                                        $max = $doc['targy_id'] == 1 ? $doc['max_pont_magyar'] : $doc['max_pont_matematika'];
                                        echo $max ?? 50;
                                        ?>
                                    </div>
                                </div>
                                <div class="score-item">
                                    <div class="score-label">Elért pont</div>
                                    <div class="score-value"><?php echo $doc['elert_pont'] ?? '-'; ?></div>
                                </div>
                                <div class="score-item">
                                    <div class="score-label">Százalék</div>
                                    <div class="score-value">
                                        <?php 
                                        $max = $doc['targy_id'] == 1 ? $doc['max_pont_magyar'] : $doc['max_pont_matematika'];
                                        $max = $max ?? 50;
                                        $percent = $doc['elert_pont'] ? round(($doc['elert_pont'] / $max) * 100) : '-';
                                        echo $percent . ($percent !== '-' ? '%' : '');
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php $mobile = is_mobile(); ?>
                            <div class="document-actions">
                                <?php if (!$mobile): ?>
                                    <button type="button" class="view-btn" data-file="<?php echo htmlspecialchars($doc['fajl_path']); ?>" data-name="<?php echo htmlspecialchars($doc['fajlnev']); ?>">👁 Megtekintés</button>
                                <?php endif; ?>

                                <a href="<?php echo htmlspecialchars($doc['fajl_path']); ?>" 
                                   class="download-btn" 
                                   target="_blank" 
                                   download>
                                    💾 PDF Letöltés
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <form class="back-form">
                <button type="button" class="btn-back" onclick="location.href='student_view.php'; return false;">← Vissza</button>
            </form>
        <?php endif; ?>
    </div>
    </div>
    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
    <div id="pdfModal" class="pdf-modal" aria-hidden="true">
        <div class="modal-content" role="dialog" aria-modal="true">
            <div class="modal-toolbar">
                <div class="title">PDF Megtekintő</div>
                <div>
                    <button id="modalDownload" class="download-btn">Letöltés</button>
                    <button id="modalClose" class="modal-close" aria-label="Bezár">✕</button>
                </div>
            </div>
            <iframe id="pdfFrame" src="" frameborder="0" allowfullscreen></iframe>
        </div>
    </div>

    <script>
    (function(){
        const modal = document.getElementById('pdfModal');
        const frame = document.getElementById('pdfFrame');
        const closeBtn = document.getElementById('modalClose');
        const downloadBtn = document.getElementById('modalDownload');

        let currentPdfUrl = '';
        let currentPdfName = '';

        function generateObfuscatedName(original){
            // Format: felveo_YYYYMMDD_<hash>_<rand>.ext
            // short deterministic hash from original (djb2 -> base36 slice)
            function djb2(str){
                let h = 5381;
                for(let i=0;i<str.length;i++){
                    h = ((h << 5) + h) + str.charCodeAt(i); /* h * 33 + c */
                    h = h & 0xffffffff;
                }
                return h >>> 0;
            }

            let ext = 'pdf';
            let nameForHash = '';
            if(original){
                const m = original.match(/\.([0-9a-zA-Z]+)(?:\?.*)?$/);
                if(m) ext = m[1];
                nameForHash = original;
            }

            const d = new Date();
            const y = d.getFullYear();
            const mm = String(d.getMonth()+1).padStart(2,'0');
            const dd = String(d.getDate()).padStart(2,'0');
            const datePart = `${y}${mm}${dd}`;
            const hash = nameForHash ? Number(djb2(nameForHash)).toString(36).slice(0,6) : Math.random().toString(36).slice(2,6);
            const rand = Math.random().toString(36).slice(2,6);
            return `felveo_${datePart}_${hash}_${rand}.${ext}`;
        }

        function triggerDownload(url, name){
            const a = document.createElement('a');
            a.href = url;
            a.download = generateObfuscatedName(name || url);
            document.body.appendChild(a);
            a.click();
            a.remove();
        }

        function openPdf(url, name){
            currentPdfUrl = url;
            currentPdfName = name || '';
            frame.src = url;
            downloadBtn.onclick = function(){ triggerDownload(currentPdfUrl, currentPdfName); };
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closePdf(){
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            // clear src to stop loading
            frame.src = '';
        }

        document.addEventListener('click', function(e){
            const btn = e.target.closest && e.target.closest('.view-btn');
            if(btn){
                const file = btn.getAttribute('data-file');
                openPdf(file, btn.getAttribute('data-name') || 'PDF');
            }

            // Intercept clicks on plain download links to provide obfuscated filename
            const dl = e.target.closest && e.target.closest('a.download-btn');
            if(dl){
                // let modal download button handle itself (it's a button, not an <a>)
                e.preventDefault();
                const url = dl.href;
                const orig = dl.getAttribute('data-name') || url.split('/').pop();
                triggerDownload(url, orig);
            }
        });

        closeBtn.addEventListener('click', closePdf);
        modal.addEventListener('click', function(e){ if(e.target === modal) closePdf(); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closePdf(); });
    })();
    </script>
</body>
</html>
