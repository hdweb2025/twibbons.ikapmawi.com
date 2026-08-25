/* ==========================================================================
   Admin Dashboard Interactive JavaScript
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Tab Navigation
    const navLinks = document.querySelectorAll('.nav-link-btn[data-tab]');
    const tabPanels = document.querySelectorAll('.tab-panel');

    function switchTab(tabId) {
        navLinks.forEach(link => {
            if (link.getAttribute('data-tab') === tabId) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        tabPanels.forEach(panel => {
            if (panel.id === tabId) {
                panel.classList.add('active');
            } else {
                panel.classList.remove('active');
            }
        });

        // Update URL hash without scroll
        if (history.pushState) {
            history.pushState(null, null, '#' + tabId);
        } else {
            location.hash = '#' + tabId;
        }

        // Close mobile sidebar if open
        const sidebar = document.querySelector('.admin-sidebar');
        if (sidebar && sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    }

    navLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab');
            switchTab(tabId);
        });
    });

    // Check initial hash in URL
    const currentHash = window.location.hash.replace('#', '');
    if (currentHash && document.getElementById(currentHash)) {
        switchTab(currentHash);
    }

    // 2. Mobile Sidebar Toggle
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
        });
    }

    // 3. Auto-Slug Generator
    const eventNameInput = document.getElementById('eventNameInput');
    const eventSlugInput = document.getElementById('eventSlugInput');
    let isSlugManual = false;

    if (eventSlugInput) {
        eventSlugInput.addEventListener('input', function () {
            isSlugManual = this.value.trim().length > 0;
        });
    }

    if (eventNameInput && eventSlugInput) {
        eventNameInput.addEventListener('input', function () {
            if (!isSlugManual) {
                const slug = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .trim()
                    .replace(/\s+/g, '-');
                eventSlugInput.value = slug;
            }
        });
    }

    // 4. Live Image Preview & Hole Calibration Canvas for Add Event
    const templateFileInput = document.getElementById('templateFileInput');
    const templatePreviewContainer = document.getElementById('templatePreviewContainer');
    const holeVisualizerCanvas = document.getElementById('holeVisualizerCanvas');
    const holeXInput = document.getElementById('holeXInput');
    const holeYInput = document.getElementById('holeYInput');
    const holeWInput = document.getElementById('holeWInput');
    const holeHInput = document.getElementById('holeHInput');

    let loadedTemplateImg = null;

    function redrawHoleCalibration(canvas, img, hx, hy, hw, hh) {
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw background checkered for transparency
        const size = 16;
        for (let x = 0; x < canvas.width; x += size) {
            for (let y = 0; y < canvas.height; y += size) {
                ctx.fillStyle = ((x / size + y / size) % 2 === 0) ? '#f1f5f9' : '#e2e8f0';
                ctx.fillRect(x, y, size, size);
            }
        }

        // Draw the hole photo area box
        ctx.fillStyle = 'rgba(37, 99, 235, 0.35)';
        ctx.fillRect(hx, hy, hw, hh);
        ctx.strokeStyle = '#2563eb';
        ctx.lineWidth = 4;
        ctx.strokeRect(hx, hy, hw, hh);

        // Text indicator in the hole
        ctx.fillStyle = '#1e3a8a';
        ctx.font = 'bold 28px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Area Foto Alumni (' + hw + 'x' + hh + ')', hx + hw / 2, hy + hh / 2);

        // Draw image frame on top
        if (img) {
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        }
    }

    function updateHoleFromInputs() {
        if (!holeVisualizerCanvas) return;
        const hx = parseInt(holeXInput?.value || 0, 10);
        const hy = parseInt(holeYInput?.value || 0, 10);
        const hw = parseInt(holeWInput?.value || 1080, 10);
        const hh = parseInt(holeHInput?.value || 1080, 10);
        redrawHoleCalibration(holeVisualizerCanvas, loadedTemplateImg, hx, hy, hw, hh);
    }

    if (templateFileInput) {
        templateFileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    const img = new Image();
                    img.onload = function () {
                        loadedTemplateImg = img;
                        if (templatePreviewContainer) templatePreviewContainer.style.display = 'block';
                        updateHoleFromInputs();
                    };
                    img.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    [holeXInput, holeYInput, holeWInput, holeHInput].forEach(inp => {
        if (inp) {
            inp.addEventListener('input', updateHoleFromInputs);
        }
    });

    // 5. Alumni Table Search Filter
    const searchAlumniInput = document.getElementById('searchAlumniInput');
    const alumniTableBody = document.getElementById('alumniTableBody');

    if (searchAlumniInput && alumniTableBody) {
        searchAlumniInput.addEventListener('input', function () {
            const filter = this.value.toLowerCase().trim();
            const rows = alumniTableBody.querySelectorAll('tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 6. Export Alumni Data to CSV
    const exportCsvBtn = document.getElementById('exportCsvBtn');
    if (exportCsvBtn && alumniTableBody) {
        exportCsvBtn.addEventListener('click', function () {
            const rows = alumniTableBody.querySelectorAll('tr');
            if (!rows.length) {
                alert('Tidak ada data untuk diekspor.');
                return;
            }

            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "No,Nama Lengkap,Tahun Alumni,Nomor HP,Tanggal Daftar\n";

            rows.forEach((row, index) => {
                const cols = row.querySelectorAll('td');
                if (cols.length >= 5) {
                    const no = index + 1;
                    const nama = `"${cols[1].textContent.trim().replace(/"/g, '""')}"`;
                    const tahun = `"${cols[2].textContent.trim().replace(/"/g, '""')}"`;
                    const hp = `"${cols[3].textContent.trim().replace(/"/g, '""')}"`;
                    const tanggal = `"${cols[4].textContent.trim().replace(/"/g, '""')}"`;
                    csvContent += `${no},${nama},${tahun},${hp},${tanggal}\n`;
                }
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `data_alumni_ikapmawi_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    // 7. Copy Direct Link Utility
    window.copyToClipboard = function (text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Tautan disalin ke clipboard: ' + text);
        }).catch(err => {
            prompt('Salin tautan berikut:', text);
        });
    };

    // 8. Edit Event Modal Management
    const editEventModal = document.getElementById('editEventModal');
    window.openEditEventModal = function (eventData) {
        if (!editEventModal) return;
        document.getElementById('editEventId').value = eventData.id;
        document.getElementById('editEventName').value = eventData.name;
        document.getElementById('editEventSlug').value = eventData.slug;
        document.getElementById('editHoleX').value = eventData.hole_x;
        document.getElementById('editHoleY').value = eventData.hole_y;
        document.getElementById('editHoleW').value = eventData.hole_w;
        document.getElementById('editHoleH').value = eventData.hole_h;
        document.getElementById('editCurrentTemplateImg').src = '/' + eventData.template;
        editEventModal.classList.add('active');
    };

    window.closeEditEventModal = function () {
        if (editEventModal) editEventModal.classList.remove('active');
    };

    // 9. Quick preset hole sizes
    window.applyHolePreset = function(type) {
        if (type === 'full') {
            if (holeXInput) holeXInput.value = 0;
            if (holeYInput) holeYInput.value = 0;
            if (holeWInput) holeWInput.value = 1080;
            if (holeHInput) holeHInput.value = 1080;
        } else if (type === 'kartini') {
            if (holeXInput) holeXInput.value = 40;
            if (holeYInput) holeYInput.value = 220;
            if (holeWInput) holeWInput.value = 570;
            if (holeHInput) holeHInput.value = 570;
        } else if (type === 'center_square') {
            if (holeXInput) holeXInput.value = 140;
            if (holeYInput) holeYInput.value = 140;
            if (holeWInput) holeWInput.value = 800;
            if (holeHInput) holeHInput.value = 800;
        }
        updateHoleFromInputs();
    };
});
