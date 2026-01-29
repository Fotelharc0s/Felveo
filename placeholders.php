<?php
session_start();
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php');
    exit;
}

require 'config.php';

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->prepare("SELECT oktatasi_azonosito, nev, szuletesi_ido, email FROM szemelyek WHERE is_placeholder = 1 ORDER BY nev");
        $stmt->execute();
        $placeholders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['placeholders' => $placeholders]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title>Placeholder személyek</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require 'navbar.php'; ?>

    <div class="container">
        <div class="card">
            <h1>Placeholder személyek</h1>
            <p>Azok a személyek, amelyeket az eredmények importja hozott létre automatikusan.</p>

            <div id="placeholdersTable"></div>
        </div>
    </div>

    <?php require 'footer.php'; ?>

    <script>
        fetch('placeholders.php?ajax=1')
            .then(r => r.json())
            .then(data => {
                const target = document.getElementById('placeholdersTable');
                if (!data.placeholders || data.placeholders.length === 0) {
                    target.innerHTML = '<div>Nem található placeholder.</div>';
                    return;
                }
                let html = '<table>';
                html += '<tr><th>Okt. azonosító</th><th>Név</th><th>Születési idő</th><th>Email</th><th></th></tr>';
                data.placeholders.forEach(p => {
                    html += `<tr data-okt="${p.oktatasi_azonosito}"><td>${p.oktatasi_azonosito}</td><td class="ph-name">${p.nev}</td><td class="ph-dob">${p.szuletesi_ido}</td><td class="ph-email">${p.email}</td><td><button class="editBtn">Szerkeszt</button></td></tr>`;
                });
                html += '</table>';
                target.innerHTML = html;
                // attach handlers
                document.querySelectorAll('.editBtn').forEach(btn => {
                    btn.addEventListener('click', e => {
                        const tr = e.target.closest('tr');
                        enterEditMode(tr);
                    });
                });
            })
            .catch(err => {
                document.getElementById('placeholdersTable').innerHTML = '<div class="error-display">Hiba a betöltésben: ' + err.message + '</div>';
            });

        function enterEditMode(tr) {
            const okt = tr.getAttribute('data-okt');
            const name = tr.querySelector('.ph-name').textContent || '';
            const dob = tr.querySelector('.ph-dob').textContent || '';
            const email = tr.querySelector('.ph-email').textContent || '';

            tr.innerHTML = `
                <td>${okt}</td>
                <td><input class="edit-name" value="${escapeHtml(name)}"></td>
                <td><input class="edit-dob" value="${escapeHtml(dob)}" placeholder="YYYY-MM-DD"></td>
                <td><input class="edit-email" value="${escapeHtml(email)}"></td>
                <td>
                    <button class="saveBtn">Mentés</button>
                    <button class="cancelBtn">Mégse</button>
                </td>
            `;

            tr.querySelector('.cancelBtn').addEventListener('click', () => { location.reload(); });
            tr.querySelector('.saveBtn').addEventListener('click', () => savePlaceholder(tr, okt));
        }

        function savePlaceholder(tr, okt) {
            const nev = tr.querySelector('.edit-name').value.trim();
            const szulet = tr.querySelector('.edit-dob').value.trim();
            const email = tr.querySelector('.edit-email').value.trim();

            fetch('placeholders_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ oktatasi_azonosito: okt, nev: nev, szuletesi_ido: szulet, email: email })
            })
            .then(r => r.json())
            .then(j => {
                if (j.error) {
                    alert('Hiba: ' + j.error);
                    return;
                }
                // success — reload to refresh list
                location.reload();
            })
            .catch(err => { alert('Hálózati hiba: ' + err.message); });
        }

        function escapeHtml(s) { return String(s).replace(/[&<>\"']/g, c => ({'&':'&amp;','<':'<','>':'>','"':'"',"'":"&#39;"})[c]); }
    </script>
    <script src="script.js"></script>
</body>
</html>
