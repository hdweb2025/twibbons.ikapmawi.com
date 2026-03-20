(function() {
    const canvas = document.getElementById('mainCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const upload = document.getElementById('upload');
    const downloadBtn = document.getElementById('download');
    const zoomSlider = document.getElementById('zoomSlider');
    const resetBtn = document.getElementById('resetBtn');

    let userImg = new Image();
    let templateImg = new Image();

    // State
    let imgX = 0, imgY = 0, imgScale = 1;
    let isDragging = false;
    let lastPinchDist = 0;

    // Load template
    const templateSrc = canvas.getAttribute('data-template');
    if (templateSrc) {
        templateImg.crossOrigin = "anonymous";
        templateImg.src = templateSrc;
        templateImg.onload = draw;
    }

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (userImg.src) {
            ctx.save();
            ctx.translate(imgX, imgY);
            ctx.scale(imgScale, imgScale);
            ctx.drawImage(userImg, -userImg.width / 2, -userImg.height / 2);
            ctx.restore();
        }
        if (templateImg.complete) {
            ctx.drawImage(templateImg, 0, 0, canvas.width, canvas.height);
        }
    }

    function updateSlider() {
        if (zoomSlider) min/max slider secara dinamis jika diperlukan agar tidak membatasi ukuran foto
            if (imgScale < parseFloat(zoomSlider.min)) zoomSlider.min = (imgScale * 0.5).toFixed(3);
            if (imgScale > parseFloat(zoomSlider.max)) zoomSlider.max = (imgScale * 2).toFixed(3);
            zoomSlider.value = imgScale;
        }
    }

    function resetImageState() {
        const scaleToFit = Math.g.height);
        imgScale = scaleToCover;
        imgX = canvas.width / 2;
        imgY = canvas.height / 2;
        if (zoomSlider) {
            zoomSlider.min = (scaleToFit * 0.1).toFixed(3); // Memungkinkan perkecil hingga jauh di bawah ukuran kanvas
            zoomSlider.max = (scaleToCover * 5).toFixed(3);
        }
        updateSlider();
        draw();
    }

    // --- Event Listeners ---
    if (zoomSlider) {
        zoomSlider.addEventListener('input', (e) => {
            imgScale = parseFloat(e.target.value);
            draw();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', resetImageState);
    }

    upload.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (ev) => {
            userImg = new Image();
            userImg.onload = () => {
                resetImageState();
                downloadBtn.disabled = false;
            };
            userImg.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    // --- MOUSE & TOUCH ---
    let startX, startY;

    // Perhitungan koordinat yang akurat mengatasi rasio CSS saat kanvas mengecil di HP/Desktop
    function getPointerPos(clientX, clientY) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    function handlePanStart(x, y) {
        isDragging = true;
        startX = x;
        startY = y;
    }

    function handlePanMove(x, y) {
        if (isDragging) {
            imgX += x - startX;
            imgY += y - startY;
            startX = x;
            startY = y;
            draw();
        }
    }

    function handlePanEnd() {
        isDragging = false;
    }

    canvas.addEventListener('mousedown', (e) => {
        const pos = getPointerPos(e.clientX, e.clientY);
        handlePanStart(pos.x, pos.y);{
        const pos = getPointerPos(e.clientX, e.clientY);
        handlePanMove(pos.x, pos.y);
    });
    window.addEventListener('mouseup', handlePanEnd);
    canvas.addEventListener('mouseleave', handlePanEnd);

    // Mengaktifkan fitur ZOOM menggunakan scroll (Mouse Wheel) seperti tertulis di UI index.php
    canvas.addEventListener('wheel', (e) => {
        if (!userImg.src) return;
        e.preventDefault();
        const zoomAmount = e.deltaY > 0 ? 0.95 : 1.05;
        
        const pos = getPointerPos(e.clientX, e.clientY);
        imgX = pos.x - (pos.x - imgX) * zoomAmount;
        imgY = pos.y - (pos.y - imgY) * zoomAmount;
        imgScale *= zoomAmount;
        
        updateSlider();
        draw();
    }, { passive: false });

    canvas.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
            const pos = getPointerPos(e.touches[0].clientX, e.touches[0].clientY);
            handlePanStart(pos.x, pos.y);
        } else if (e.touches.length === 2) {
            lastPinchDist = getDist(e.touches[0], e.touches[1]);
        }ve: true });

    canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        if (e.touches.length === 1) {
            const pos = getPointerPos(e.touches[0].clientX, e.touches[0].clientY);
            handlePanMove(pos.x, pos.y);
        } else if (e.touches.length === 2) {
            const currentDist = getDist(e.touches[0], e.touches[1]);
            if (lastPinchDist > 0) {
                const midpoint = getMidpoint(e.touches[0], e.touches[1]);
                const pos = getPointerPos(midpoint.x, midpoint.y);

                imgX = pos.x - (pos.x - imgX) * scaleFactor;
                imgY = pos.y - (pos.y - imgY) * scaleFactor;
                imgScale *= scaleFactor;
                updateSlider();
        }
    }, { passive: false });
ength < 2) lastPinchDist = 0;
        if (e.touches.length < 1) handlePanEnd();
    });

    function getDist(t1, t2) { return Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY); }
    function getMidpoint(t1, t2) { return { x: (t1.clientX + t2.clientX) / 2, y: (t1.clientY + t2.clientY) / 2 }; }

    // --- DOWNLOAD ---
    downloadBtn.addEventListener('click', () => {
        const link = document.createElement('a');
        link.download = 'twibbon-ikapmawi.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        

    });
})();
