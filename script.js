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
    let startX, startY;
    let lastTouchX = 0, lastTouchY = 0, lastPinchDist = 0;

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
            ctx.drawImage(userImg, imgX, imgY, userImg.width * imgScale, userImg.height * imgScale);
        }
        if (templateImg.complete) {
            ctx.drawImage(templateImg, 0, 0, canvas.width, canvas.height);
        }
    }

    function updateSlider() {
        if (zoomSlider) zoomSlider.value = imgScale;
    }

    function resetImageState() {
        // Default: Cover the canvas (zoom-to-fill)
        imgScale = Math.max(canvas.width / userImg.width, canvas.height / userImg.height);
        imgX = (canvas.width - userImg.width * imgScale) / 2;
        imgY = (canvas.height - userImg.height * imgScale) / 2;
        updateSlider();
        draw();
    }

    // --- Event Listeners ---
    if (zoomSlider) {
        zoomSlider.addEventListener('input', (e) => {
            const oldScale = imgScale;
            imgScale = parseFloat(e.target.value);
            imgX -= (userImg.width * (imgScale - oldScale)) / 2;
            imgY -= (userImg.height * (imgScale - oldScale)) / 2;
            draw();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', resetImageState);
    }

    upload.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('photo', file);
        fetch('/upload_photo.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.error) return alert(data.error);
                const reader = new FileReader();
                reader.onload = (ev) => {
                    userImg.src = ev.target.result;
                    userImg.onload = () => {
                        resetImageState();
                        downloadBtn.disabled = false;
                    };
                };
                reader.readAsDataURL(file);
            });
    });

    // --- MOUSE ---
    canvas.addEventListener('mousedown', (e) => {
        isDragging = true;
        startX = e.offsetX;
        startY = e.offsetY;
    });
    window.addEventListener('mouseup', () => isDragging = false);
    canvas.addEventListener('mousemove', (e) => {
        if (isDragging) {
            imgX += e.movementX;
            imgY += e.movementY;
            draw();
        }
    });
    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const factor = e.deltaY < 0 ? 1.05 : 0.95;
        imgScale *= factor;
        updateSlider();
        draw();
    }, { passive: false });

    // --- TOUCH (MOBILE) ---
    function getDist(t1, t2) { return Math.hypot(t1.pageX - t2.pageX, t1.pageY - t2.pageY); }
    function getMidpoint(t1, t2) { return { x: (t1.pageX + t2.pageX) / 2, y: (t1.pageY + t2.pageY) / 2 }; }

    canvas.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
            isDragging = true;
            lastTouchX = e.touches[0].pageX;
            lastTouchY = e.touches[0].pageY;
        } else if (e.touches.length === 2) {
            lastPinchDist = getDist(e.touches[0], e.touches[1]);
        }
    }, { passive: true });

    canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        if (e.touches.length === 1 && isDragging) {
            imgX += e.touches[0].pageX - lastTouchX;
            imgY += e.touches[0].pageY - lastTouchY;
            lastTouchX = e.touches[0].pageX;
            lastTouchY = e.touches[0].pageY;
        } else if (e.touches.length === 2) {
            const currentDist = getDist(e.touches[0], e.touches[1]);
            if (lastPinchDist > 0) {
                const scaleFactor = currentDist / lastPinchDist;
                const midpoint = getMidpoint(e.touches[0], e.touches[1]);
                const rect = canvas.getBoundingClientRect();
                const mouseX = midpoint.x - rect.left;
                const mouseY = midpoint.y - rect.top;

                imgX = mouseX - (mouseX - imgX) * scaleFactor;
                imgY = mouseY - (mouseY - imgY) * scaleFactor;
                imgScale *= scaleFactor;
                updateSlider();
            }
            lastPinchDist = currentDist;
        }
        draw();
    }, { passive: false });

    canvas.addEventListener('touchend', () => {
        isDragging = false;
        lastPinchDist = 0;
    }, { passive: true });

    // --- DOWNLOAD ---
    downloadBtn.addEventListener('click', () => {
        const link = document.createElement('a');
        link.download = 'twibbon-ikapmawi.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        
        const fd = new FormData();
        fd.append('event_id', canvas.getAttribute('data-event-id'));
        fetch('/record_usage.php', { method: 'POST', body: fd });
    });
})();
