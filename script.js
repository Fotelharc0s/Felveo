// ===== Fő inicializáció =====
document.addEventListener('DOMContentLoaded', function () {
    // ===== Theme toggle =====
    const toggleBtn = document.getElementById("themeToggle");
    const body = document.body;

    if (toggleBtn) {
        // Betöltéskor
        if (localStorage.getItem("theme") === "dark") {
            body.classList.add("dark");
            toggleBtn.textContent = "☀️";
        }

        toggleBtn.addEventListener("click", () => {
            body.classList.toggle("dark");
            const dark = body.classList.contains("dark");
            toggleBtn.textContent = dark ? "☀️" : "🌙";
            localStorage.setItem("theme", dark ? "dark" : "light");
        });
    }


    // ===== Admin link handling =====
    (async function initAdminNav() {
        const linksContainer = document.querySelector('.nav .links');
        if (!linksContainer) return;
        try {
            const res = await fetch('is_admin.php');
            const j = await res.json();
            const isAdmin = !!j.is_admin;

            // Helper: find link by href
            const findLink = href => linksContainer.querySelector(`a[href="${href}"]`);

            // Ensure admin login/logout link
            let adminLink = findLink('admin_login.php') || findLink('admin_logout.php');
            if (isAdmin) {
                // show import and placeholders
                const imp = findLink('import.php'); if (imp) imp.style.display = '';
                const ph = findLink('placeholders.html'); if (ph) ph.style.display = '';
                // replace login with logout
                if (adminLink && adminLink.getAttribute('href') === 'admin_login.php') {
                    adminLink.textContent = 'Kijelentkezés';
                    adminLink.setAttribute('href', 'admin_logout.php');
                } else if (!adminLink) {
                    const a = document.createElement('a'); a.href = 'admin_logout.php'; a.textContent = 'Kijelentkezés'; linksContainer.appendChild(a);
                }
            } else {
                // hide import and placeholders for non-admin
                const imp = findLink('import.php'); if (imp) imp.style.display = 'none';
                const ph = findLink('placeholders.html'); if (ph) ph.style.display = 'none';
                // ensure login link
                if (adminLink && adminLink.getAttribute('href') === 'admin_logout.php') {
                    adminLink.textContent = 'Admin bejelentkezés';
                    adminLink.setAttribute('href', 'admin_login.php');
                } else if (!adminLink) {
                    const a = document.createElement('a'); a.href = 'admin_login.php'; a.textContent = 'Admin bejelentkezés'; linksContainer.appendChild(a);
                }
            }
        } catch (e) {
            // ignore failures — keep existing links
            console.warn('is_admin check failed', e);
        }
    })();

    // ===== Upload form kezelés =====
    const uploadForm = document.getElementById("uploadForm");
    const status = document.getElementById("status");
    const progress = document.querySelector(".progress");
    const bar = document.getElementById("progressBar");

    if (uploadForm) {
        uploadForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            if (status) status.innerText = "Feldolgozás...";

            if (progress) {
                progress.style.display = "block";
                if (bar) bar.style.width = "0%";
            }

            const xhr = new XMLHttpRequest();
            xhr.open("POST", "upload.php", true);

            xhr.upload.onprogress = function (e) {
                if (e.lengthComputable && bar) {
                    const percent = (e.loaded / e.total) * 100;
                    bar.style.width = percent + "%";
                }
            };

            xhr.onload = function () {
                if (bar) bar.style.width = "100%";
                if (status) {
                    status.innerText = xhr.responseText;
                    status.className = "status success";
                }
            };

            xhr.onerror = function () {
                if (status) {
                    status.innerText = "Hiba történt.";
                    status.className = "status error";
                }
            };

            xhr.send(formData);
        });
    }


    // Drag and drop
    const fileInput = document.querySelector('input[type="file"]');
    const dropArea = document.querySelector('.file-input');

    if (dropArea && fileInput) {
        dropArea.addEventListener("dragover", e => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.style.opacity = "0.7";
        });

        dropArea.addEventListener("dragleave", (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.style.opacity = "1";
        });

        dropArea.addEventListener("drop", e => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.style.opacity = "1";
            
            // Use DataTransfer to set files
            const dataTransfer = new DataTransfer();
            Array.from(e.dataTransfer.files).forEach(file => {
                dataTransfer.items.add(file);
            });
            fileInput.files = dataTransfer.files;
            
            // Trigger change event and render files
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
            renderFiles();
        });
    }

    // Fájl név megjelenítése
    const fileNameDisplay = document.getElementById("fileName");

    if (fileInput && fileNameDisplay) {
        fileInput.addEventListener("change", () => {
            if (fileInput.files.length > 0) {
                fileNameDisplay.textContent = fileInput.files[0].name;
                fileNameDisplay.classList.add("selected");
            } else {
                fileNameDisplay.textContent = "Nincs fájl kiválasztva";
                fileNameDisplay.classList.remove("selected");
            }
        });
    }

    const fileList = document.getElementById("fileList");
    const clearBtn = document.getElementById("clearFiles");

    function renderFiles() {
        if (!fileList || !fileInput) return;
        fileList.innerHTML = "";

        const hasFiles = Array.from(fileInput.files).filter(f => f.name.match(/\.(xlsx|xls)$/i)).length > 0;
        
        if (hasFiles) {
            fileList.classList.add("visible");
        } else {
            fileList.classList.remove("visible");
            return;
        }

        Array.from(fileInput.files).forEach((file, index) => {
            if (!file.name.match(/\.(xlsx|xls)$/i)) return;

            const div = document.createElement("div");
            div.className = "file-item";

            const name = document.createElement("span");
            name.textContent = file.name;

            const remove = document.createElement("button");
            remove.type = "button";
            remove.textContent = "✕";
            remove.style.border = "none";
            remove.style.background = "transparent";
            remove.style.cursor = "pointer";
            remove.style.marginLeft = "10px";

            remove.onclick = (e) => {
                e.preventDefault();
                removeFile(index);
            };

            div.appendChild(name);
            div.appendChild(remove);
            fileList.appendChild(div);
        });
    }

    function removeFile(removeIndex) {
        if (!fileInput) return;
        const dt = new DataTransfer();
        Array.from(fileInput.files).forEach((file, index) => {
            if (index !== removeIndex) dt.items.add(file);
        });
        fileInput.files = dt.files;
        renderFiles();
    }

    if (fileInput) fileInput.addEventListener("change", renderFiles);

    if (clearBtn) {
        clearBtn.addEventListener("click", () => {
            if (fileInput) fileInput.value = "";
            if (fileList) fileList.innerHTML = "";
        });
    }

    // ===== Eredmények lekérdezése =====
    const btn = document.getElementById("lekerdezBtn");
    const input = document.getElementById("oktatasi");
    const output = document.getElementById("output");

    if (btn && input && output) {
        btn.addEventListener("click", () => {
            const okt = input.value.trim();
            output.innerHTML = "";

            if (!okt) {
                output.innerHTML = `<div class="error">Add meg az oktatási azonosítót!</div>`;
                return;
            }

            fetch("eredmeny.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "oktatasi_azonosito=" + encodeURIComponent(okt)
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    output.innerHTML = `<div class="error">${data.error}</div>`;
                    return;
                }

                // Build a nicer, card-based UI for results
                let html = `<div class="result-header">${data.nev}</div>`;
                if (!data.eredmenyek || data.eredmenyek.length === 0) {
                    html += `<div class="error">Nincsenek eredmények.</div>`;
                    output.innerHTML = html;
                    return;
                }

                html += `<div class="result-cards">`;
                data.eredmenyek.forEach(e => {
                    let cls = 'mid';
                    if (e.pont >= 85) cls = 'high';
                    else if (e.pont < 70) cls = 'low';

                    html += `
                        <div class="result-card">
                            <div class="subject">${e.targy}</div>
                            <div class="score ${cls}">${e.pont}</div>
                        </div>`;
                });
                html += `</div>`;
                output.innerHTML = html;
            })
            .catch(() => {
                output.innerHTML = `<div class="error">Hiba történt a lekérdezés során.</div>`;
            });
        });
    }
});
