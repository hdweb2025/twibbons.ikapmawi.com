(function() {
    const canvas = document.getElementById('mainCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const upload = document.getElementById('upload');
    const downloadBtn = document.getElementById('download');
    const zoomSlider = document.getElementById('zoomSlider');
    const zoomValBadge = document.getElementById('zoomValBadge');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const rotateBtn = document.getElementById('rotateBtn');
    const resetBtn = document.getElementById('resetBtn');

    let userImg = new Image();
    let templateImg = new Image();

    // Transform State
    let imgX = 540;
    let imgY = 540;
    let imgScale = 1;
    let baseScale = 1;
    let imgRotation = 0; // 0, 90, 180, 270 degrees
    let isDragging = false;
    let activePointerId = null;
    let startX = 0, startY = 0;
    let lastPinchDist = 0;
    let drawFrame = null;

    // Load template
    const templateSrc = canvas.getAttribute('data-template');
    if (templateSrc) {
        templateImg.crossOrigin = "anonymous";
        templateImg.src = templateSrc;
        templateImg.onload = draw;
    }

    // Render Canvas
    function draw() {
        if (drawFrame) cancelAnimationFrame(drawFrame);
        drawFrame = requestAnimationFrame(() => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Draw User Photo under the template
            if (userImg.complete && userImg.src) {
                ctx.save();
                ctx.translate(imgX, imgY);
                ctx.rotate((imgRotation * Math.PI) / 180);
                ctx.scale(imgScale, imgScale);
                ctx.drawImage(userImg, -userImg.width / 2, -userImg.height / 2);
                ctx.restore();
            }

            // Draw Template Frame on top
            if (templateImg.complete && templateImg.src) {
                ctx.drawImage(templateImg, 0, 0, canvas.width, canvas.height);
            }
        });
    }

    // Update Slider & Badge
    function updateSlider() {
        if (zoomSlider) {
            const currentVal = parseFloat(zoomSlider.value);
            if (imgScale < parseFloat(zoomSlider.min)) zoomSlider.min = (imgScale * 0.5).toFixed(3);
            if (imgScale > parseFloat(zoomSlider.max)) zoomSlider.max = (imgScale * 2).toFixed(3);
            zoomSlider.value = imgScale;
        }
        if (zoomValBadge) {
            const percent = baseScale > 0 ? Math.round((imgScale / baseScale) * 100) : 100;
            zoomValBadge.textContent = percent + '%';
        }
    }

    // Reset Image Position, Scale, and Orientation
    function resetImageState() {
        if (!userImg.complete || !userImg.width) return;

        const targetX = parseInt(canvas.getAttribute('data-hole-x')) || 0;
        const targetY = parseInt(canvas.getAttribute('data-hole-y')) || 0;
        const targetW = parseInt(canvas.getAttribute('data-hole-w')) || 1080;
        const targetH = parseInt(canvas.getAttribute('data-hole-h')) || 1080;

        // Tentukan dimensi efektif berdasarkan rotasi saat ini
        const isRotated = (imgRotation === 90 || imgRotation === 270);
        const effW = isRotated ? userImg.height : userImg.width;
        const effH = isRotated ? userImg.width : userImg.height;

        const scaleW = targetW / effW;
        const scaleH = targetH / effH;

        // Default cover/fit area target
        imgScale = Math.min(scaleW, scaleH);
        baseScale = imgScale;

        imgX = targetX + (targetW / 2);
        imgY = targetY + (targetH / 2);

        if (zoomSlider) {
            zoomSlider.min = (baseScale * 0.2).toFixed(3);
            zoomSlider.max = (Math.max(scaleW, scaleH) * 4).toFixed(3);
        }

        updateSlider();
        draw();
    }

    // Set Zoom with bounds check
    function setZoom(newScale, focalPoint) {
        if (!userImg.src) return;
        
        const minScale = zoomSlider ? parseFloat(zoomSlider.min) : 0.05;
        const maxScale = zoomSlider ? parseFloat(zoomSlider.max) : 10;
        newScale = Math.max(minScale, Math.min(maxScale, newScale));

        if (focalPoint) {
            const factor = newScale / imgScale;
            imgX = focalPoint.x - (focalPoint.x - imgX) * factor;
            imgY = focalPoint.y - (focalPoint.y - imgY) * factor;
        }

        imgScale = newScale;
        updateSlider();
        draw();
    }

    // --- Event Listeners: Zoom Slider ---
    if (zoomSlider) {
        const handleSliderInput = (e) => {
            imgScale = parseFloat(e.target.value);
            if (zoomValBadge) {
                const percent = baseScale > 0 ? Math.round((imgScale / baseScale) * 100) : 100;
                zoomValBadge.textContent = percent + '%';
            }
            draw();
        };
        zoomSlider.addEventListener('input', handleSliderInput);
        zoomSlider.addEventListener('change', handleSliderInput);
    }

    // Zoom In Button (+)
    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', () => {
            const center = { x: canvas.width / 2, y: canvas.height / 2 };
            setZoom(imgScale * 1.15, center);
        });
    }

    // Zoom Out Button (-)
    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', () => {
            const center = { x: canvas.width / 2, y: canvas.height / 2 };
            setZoom(imgScale * 0.85, center);
        });
    }

    // Rotate Button (↻ 90°)
    if (rotateBtn) {
        rotateBtn.addEventListener('click', () => {
            if (!userImg.src) return;
            imgRotation = (imgRotation + 90) % 360;
            draw();
        });
    }

    // Reset Button (↺)
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            imgRotation = 0;
            resetImageState();
        });
    }

    // File Upload Handler
    if (upload) {
        upload.addEventListener('change', (e) => {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (ev) => {
                userImg = new Image();
                userImg.onload = () => {
                    imgRotation = 0;
                    resetImageState();
                    if (downloadBtn) downloadBtn.disabled = false;
                };
                userImg.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    // --- ACCURATE POINTER COORDINATES ---
    function getPointerPos(clientX, clientY) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / (rect.width || 1);
        const scaleY = canvas.height / (rect.height || 1);
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    // --- POINTER EVENTS (Mouse, Touch, Stylus / Hybrid Laptop) ---
    function handlePanStart(x, y, pointerId) {
        if (!userImg.src) return;
        isDragging = true;
        activePointerId = pointerId;
        startX = x;
        startY = y;
    }

    function handlePanMove(x, y) {
        if (isDragging) {
            imgX += (x - startX);
            imgY += (y - startY);
            startX = x;
            startY = y;
            draw();
        }
    }

    function handlePanEnd() {
        isDragging = false;
        activePointerId = null;
    }

    // Support Pointer Events with Pointer Capture
    if (window.PointerEvent) {
        canvas.addEventListener('pointerdown', (e) => {
            if (e.isPrimary) {
                const pos = getPointerPos(e.clientX, e.clientY);
                handlePanStart(pos.x, pos.y, e.pointerId);
                try {
                    canvas.setPointerCapture(e.pointerId);
                } catch (err) {}
            }
        });

        canvas.addEventListener('pointermove', (e) => {
            if (isDragging && e.pointerId === activePointerId) {
                const pos = getPointerPos(e.clientX, e.clientY);
                handlePanMove(pos.x, pos.y);
            }
        });

        canvas.addEventListener('pointerup', (e) => {
            if (e.pointerId === activePointerId) {
                try {
                    canvas.releasePointerCapture(e.pointerId);
                } catch (err) {}
                handlePanEnd();
            }
        });

        canvas.addEventListener('pointercancel', (e) => {
            handlePanEnd();
        });
    } else {
        // Fallback Mouse
        canvas.addEventListener('mousedown', (e) => {
            const pos = getPointerPos(e.clientX, e.clientY);
            handlePanStart(pos.x, pos.y, 'mouse');
        });
        window.addEventListener('mousemove', (e) => {
            if (isDragging) {
                const pos = getPointerPos(e.clientX, e.clientY);
                handlePanMove(pos.x, pos.y);
            }
        });
        window.addEventListener('mouseup', handlePanEnd);
    }

    // --- MULTI-TOUCH GESTURES (Pinch-to-zoom on Tablet / Mobile) ---
    canvas.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            isDragging = false;
            lastPinchDist = getDist(e.touches[0], e.touches[1]);
        }
    }, { passive: true });

    canvas.addEventListener('touchmove', (e) => {
        if (e.touches.length === 2 && lastPinchDist > 0) {
            e.preventDefault();
            const currentDist = getDist(e.touches[0], e.touches[1]);
            const scaleFactor = currentDist / lastPinchDist;
            const midpoint = getMidpoint(e.touches[0], e.touches[1]);
            const pos = getPointerPos(midpoint.x, midpoint.y);

            setZoom(imgScale * scaleFactor, pos);
            lastPinchDist = currentDist;
        }
    }, { passive: false });

    canvas.addEventListener('touchend', (e) => {
        if (e.touches.length < 2) {
            lastPinchDist = 0;
        }
    }, { passive: true });

    canvas.addEventListener('touchcancel', () => {
        lastPinchDist = 0;
        handlePanEnd();
    }, { passive: true });

    function getDist(t1, t2) {
        return Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
    }
    function getMidpoint(t1, t2) {
        return { x: (t1.clientX + t2.clientX) / 2, y: (t1.clientY + t2.clientY) / 2 };
    }

    // Mouse Wheel Zoom
    canvas.addEventListener('wheel', (e) => {
        if (!userImg.src) return;
        e.preventDefault();
        const zoomAmount = e.deltaY > 0 ? 0.92 : 1.08;
        const pos = getPointerPos(e.clientX, e.clientY);
        setZoom(imgScale * zoomAmount, pos);
    }, { passive: false });

    // Handle Orientation & Window Resize
    window.addEventListener('resize', () => {
        draw();
    });
    window.addEventListener('orientationchange', () => {
        setTimeout(draw, 150);
    });

    // --- TOAST NOTIFICATION ---
    function showToast(message) {
        let toast = document.getElementById('twibbonToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'twibbonToast';
            toast.className = 'twibbon-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
    }

    // --- SMART PERSISTENT PROFILE (6 BULAN) ---
    function getStoredProfile() {
        try {
            const raw = localStorage.getItem('ikapmawi_participant');
            if (raw) return JSON.parse(raw);
        } catch(e) {}
        const legacyName = localStorage.getItem('ikapmawi_participant_name');
        if (legacyName) {
            return {
                nama: legacyName,
                peran: localStorage.getItem('ikapmawi_participant_role') || 'Alumni'
            };
        }
        return null;
    }

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function syncProfileUI(profile) {
        if (!profile || !profile.nama) return;

        // Set logged-in state on canvas
        canvas.setAttribute('data-logged-in', '1');

        // Update header greeting if present
        const headerBadge = document.getElementById('userBadgeHeader');
        const headerText = document.getElementById('userBadgeText');
        if (headerBadge && headerText) {
            const ket = profile.keterangan || (profile.peran === 'Alumni' && profile.tahun ? 'Alumni ' + profile.tahun : profile.peran);
            headerText.innerHTML = `Assalamualaikum, <b>${escapeHtml(profile.nama)}</b> (${escapeHtml(ket || 'Keluarga MWI')})`;
            headerBadge.style.display = 'inline-flex';
        }

        // Update download card identity box
        const rememberedBox = document.getElementById('participantRememberedBox');
        const rememberedName = document.getElementById('rememberedName');
        if (rememberedBox && rememberedName) {
            rememberedName.textContent = profile.nama;
            rememberedBox.style.display = 'flex';
        }

        // Pre-fill modal form
        const inputNama = document.getElementById('modalNama');
        if (inputNama && !inputNama.value) inputNama.value = profile.nama;

        if (profile.peran && typeof window.selectRole === 'function') {
            window.selectRole(profile.peran);
        }
        if (profile.tahun && profile.peran === 'Alumni') {
            const selTahun = document.getElementById('selectTahunAlumni');
            if (selTahun) selTahun.value = profile.tahun;
        }
    }

    // Jalankan sinkronisasi profil saat awal render
    const initialProfile = getStoredProfile();
    if (initialProfile) {
        syncProfileUI(initialProfile);
    }

    // --- DIRECT DOWNLOAD TRIGGER ---
    function triggerDirectDownload() {
        if (!userImg.src) return;

        const eventSlug = canvas.getAttribute('data-event-slug') || canvas.getAttribute('data-event-id') || 'frame';
        const filename = 'twibbon-' + eventSlug + '.png';

        const link = document.createElement('a');
        link.download = filename;
        link.href = canvas.toDataURL('image/png', 1.0);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        const profile = getStoredProfile();
        const displayName = profile ? profile.nama : '';
        const greetText = displayName ? ` Sahabat ${displayName}` : '';
        showToast(`Twibbon berhasil diunduh! Alhamdulillah, terima kasih atas partisipasinya,${greetText}.`);

        // Silent tracking ke database
        const eventId = canvas.getAttribute('data-event-id');
        if (eventId) {
            const params = new URLSearchParams();
            params.append('event_id', eventId);
            if (profile && profile.user_id) {
                params.append('user_id', profile.user_id);
            }
            fetch('/track_usage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            }).catch(err => console.error(err));
        }
    }

    // --- SWITCH PARTICIPANT IDENTITY (GANTI AKUN/HP PINJAMAN) ---
    window.switchParticipantIdentity = function() {
        // Hapus penyimpanan lokal
        localStorage.removeItem('ikapmawi_participant');
        localStorage.removeItem('ikapmawi_participant_name');
        localStorage.removeItem('ikapmawi_participant_role');

        // Reset atribut login di canvas
        canvas.setAttribute('data-logged-in', '0');

        // Sembunyikan badge & kotak profil tersimpan
        const headerBadge = document.getElementById('userBadgeHeader');
        if (headerBadge) headerBadge.style.display = 'none';

        const rememberedBox = document.getElementById('participantRememberedBox');
        if (rememberedBox) rememberedBox.style.display = 'none';

        // Reset input form & Google state
        const inputNama = document.getElementById('modalNama');
        if (inputNama) {
            inputNama.value = '';
            const groupNama = inputNama.closest('.form-group-modal');
            if (groupNama) groupNama.style.display = 'block';
        }

        const tokenInput = document.getElementById('googleCredentialToken');
        if (tokenInput) tokenInput.value = '';

        const googleSection = document.getElementById('googleAuthSection');
        if (googleSection) googleSection.style.display = 'flex';

        const googleBanner = document.getElementById('googleProfileBanner');
        if (googleBanner) googleBanner.style.display = 'none';

        // Panggil endpoint reset session & cookie secara asinkron
        fetch('/logout.php?action=ajax_reset', { method: 'GET' }).catch(() => {});

        // Buka modal agar bisa diisi identitas baru
        window.openParticipantModal();
    };

    // --- GOOGLE SIGN-IN CREDENTIAL HANDLER ---
    window.handleGoogleCredentialResponse = function(response) {
        if (!response || !response.credential) return;

        const errEl = document.getElementById('modalErrorMsg');
        if (errEl) errEl.style.display = 'none';

        const btn = document.getElementById('btnSubmitModal');
        const btnText = btn ? btn.querySelector('.btn-text') : null;
        const btnSpinner = btn ? btn.querySelector('.btn-spinner') : null;

        if (btn) btn.disabled = true;
        if (btnText) btnText.style.display = 'none';
        if (btnSpinner) {
            btnSpinner.textContent = 'Memverifikasi akun Google...';
            btnSpinner.style.display = 'inline-block';
        }

        const eventId = canvas.getAttribute('data-event-id') || '0';
        const formData = new URLSearchParams();
        formData.append('credential', response.credential);
        formData.append('event_id', eventId);

        // Simpan token Google di hidden field
        const tokenInput = document.getElementById('googleCredentialToken');
        if (tokenInput) tokenInput.value = response.credential;

        fetch('/auth/google_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (btnText) btnText.style.display = 'inline-block';
            if (btnSpinner) btnSpinner.style.display = 'none';

            if (data.status === 'success') {
                const profile = {
                    user_id: data.user_id,
                    nama: data.nama,
                    peran: data.peran,
                    keterangan: data.keterangan,
                    tahun: data.tahun,
                    avatar: data.avatar,
                    auth_provider: 'google'
                };
                localStorage.setItem('ikapmawi_participant', JSON.stringify(profile));
                localStorage.setItem('ikapmawi_participant_name', data.nama);
                localStorage.setItem('ikapmawi_participant_role', data.peran);
                syncProfileUI(profile);

                // Update usage counter
                const usageEl = document.getElementById('usageCountDisplay');
                if (usageEl) {
                    const currentVal = parseInt(usageEl.textContent.trim()) || 0;
                    usageEl.textContent = currentVal + 1;
                }

                window.closeParticipantModal();
                triggerDirectDownload();
            } else if (data.status === 'need_role') {
                // User Google baru / belum tentukan peran -> buka selector peran
                const googleSection = document.getElementById('googleAuthSection');
                if (googleSection) googleSection.style.display = 'none';

                const banner = document.getElementById('googleProfileBanner');
                const avatarImg = document.getElementById('googleAvatarImg');
                const nameEl = document.getElementById('googleProfileName');
                if (banner && avatarImg && nameEl) {
                    avatarImg.src = data.avatar || '/assets/icon.png';
                    nameEl.textContent = data.nama + (data.email ? ' (' + data.email + ')' : '');
                    banner.style.display = 'flex';
                }

                const inputNama = document.getElementById('modalNama');
                if (inputNama) {
                    inputNama.value = data.nama;
                    const groupNama = inputNama.closest('.form-group-modal');
                    if (groupNama) groupNama.style.display = 'none';
                }

                if (btnText) btnText.textContent = 'Simpan & Unduh Twibbon ⬇';
                showToast('Akun Google terhubung! Silakan pilih status Anda di MWI.');
            } else {
                if (errEl) {
                    errEl.textContent = data.message || 'Gagal masuk dengan Google.';
                    errEl.style.display = 'block';
                }
            }
        })
        .catch(err => {
            console.error('Error Google Sign-In:', err);
            if (btn) btn.disabled = false;
            if (btnText) btnText.style.display = 'inline-block';
            if (btnSpinner) btnSpinner.style.display = 'none';
            if (errEl) {
                errEl.textContent = 'Koneksi ke Google terganggu. Silakan isi form manual di bawah.';
                errEl.style.display = 'block';
            }
        });
    };

    // --- MODAL CONTROLLER & ROLE SELECTOR ---
    window.selectRole = function(role) {
        const inputRole = document.getElementById('selectedRole');
        if (inputRole) inputRole.value = role;

        const pills = document.querySelectorAll('.role-pill-btn');
        pills.forEach(p => {
            if (p.getAttribute('data-role') === role) {
                p.classList.add('active');
            } else {
                p.classList.remove('active');
            }
        });

        const fieldSantri = document.getElementById('fieldSantri');
        const fieldAlumni = document.getElementById('fieldAlumni');
        const fieldAsatidz = document.getElementById('fieldAsatidz');
        const fieldSimpatisan = document.getElementById('fieldSimpatisan');

        if (fieldSantri) fieldSantri.style.display = (role === 'Santri Aktif') ? 'block' : 'none';
        if (fieldAlumni) fieldAlumni.style.display = (role === 'Alumni') ? 'block' : 'none';
        if (fieldAsatidz) fieldAsatidz.style.display = (role === 'Asatidz / Guru') ? 'block' : 'none';
        if (fieldSimpatisan) fieldSimpatisan.style.display = (role === 'Simpatisan / Umum') ? 'block' : 'none';
    };

    window.openParticipantModal = function() {
        const modal = document.getElementById('participantModal');
        if (modal) {
            modal.style.display = 'flex';
            requestAnimationFrame(() => {
                modal.classList.add('show');
            });
            setTimeout(() => {
                const input = document.getElementById('modalNama');
                if (input) input.focus();
            }, 100);
        }
    };

    window.closeParticipantModal = function() {
        const modal = document.getElementById('participantModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 250);
        }
    };

    // Close on overlay backdrop click or Escape key
    const modalOverlay = document.getElementById('participantModal');
    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                window.closeParticipantModal();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modalOverlay.classList.contains('show')) {
                window.closeParticipantModal();
            }
        });
    }

    window.handleParticipantSubmit = function(e) {
        e.preventDefault();
        const errEl = document.getElementById('modalErrorMsg');
        if (errEl) errEl.style.display = 'none';

        const form = document.getElementById('participantForm');
        const btn = document.getElementById('btnSubmitModal');
        const btnText = btn ? btn.querySelector('.btn-text') : null;
        const btnSpinner = btn ? btn.querySelector('.btn-spinner') : null;

        const nama = document.getElementById('modalNama').value.trim();
        if (!nama) {
            if (errEl) {
                errEl.textContent = 'Nama lengkap wajib diisi!';
                errEl.style.display = 'block';
            }
            return;
        }

        if (btn) btn.disabled = true;
        if (btnText) btnText.style.display = 'none';
        if (btnSpinner) btnSpinner.style.display = 'inline-block';

        const googleToken = document.getElementById('googleCredentialToken') ? document.getElementById('googleCredentialToken').value : '';
        const endpoint = googleToken ? '/auth/google_login.php' : '/record_participant.php';

        const formData = new FormData(form);
        if (googleToken) {
            formData.append('credential', googleToken);
        }

        fetch(endpoint, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (btnText) btnText.style.display = 'inline-block';
            if (btnSpinner) btnSpinner.style.display = 'none';

            if (data.status === 'success') {
                const profile = {
                    user_id: data.user_id,
                    nama: data.nama,
                    peran: data.peran,
                    keterangan: data.keterangan,
                    tahun: data.tahun,
                    avatar: data.avatar || '',
                    auth_provider: googleToken ? 'google' : 'manual'
                };
                localStorage.setItem('ikapmawi_participant', JSON.stringify(profile));
                localStorage.setItem('ikapmawi_participant_name', data.nama);
                localStorage.setItem('ikapmawi_participant_role', data.peran);
                syncProfileUI(profile);

                // Update usage counter
                const usageEl = document.getElementById('usageCountDisplay');
                if (usageEl) {
                    const currentVal = parseInt(usageEl.textContent.trim()) || 0;
                    usageEl.textContent = currentVal + 1;
                }

                window.closeParticipantModal();
                triggerDirectDownload();
            } else {
                if (errEl) {
                    errEl.textContent = data.message || 'Gagal menyimpan data partisipan.';
                    errEl.style.display = 'block';
                }
            }
        })
        .catch(err => {
            console.error('Error recording participant:', err);
            if (btn) btn.disabled = false;
            if (btnText) btnText.style.display = 'inline-block';
            if (btnSpinner) btnSpinner.style.display = 'none';
            // Fail gracefully: tetap unduh jika koneksi bermasalah
            window.closeParticipantModal();
            triggerDirectDownload();
        });
    };

    // --- DOWNLOAD BUTTON HANDLER (ZERO-FRICTION FOR RETURNING USERS) ---
    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            if (!userImg.src) return;

            const isLoggedIn = (canvas.getAttribute('data-logged-in') === '1') || !!getStoredProfile();
            if (!isLoggedIn) {
                window.openParticipantModal();
            } else {
                // Pengguna yang sudah pernah mengisi langsung unduh tanpa modal!
                triggerDirectDownload();
            }
        });
    }
})();
