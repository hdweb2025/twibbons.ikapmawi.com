(function() {
    const canvas = document.getElementById('mainCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const upload = document.getElementById('upload');
    const downloadBtn = document.getElementById('download');
    const zoomSlider = document.getElementById('zoomSlider');

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
        templateImg.onload = () => { draw(); };
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
        if (zoomSlider) {
            // Ensure the slider can represent the current scale
            if (imgScale < parseFloat(zoomSlider.min)) zoomSlider.min = (imgScale * 0.5).toFixed(3);
            if (imgScale > parseFloat(zoomSlider.max)) zoomSlider.max = (imgScale * 1.5).toFixed(3);
            zoomSlider.value = imgScale;
        }
    }

    // Input handlers
    if (zoomSlider) {
        ['input', 'change'].forEach(evt => {
            zoomSlider.addEventListener(evt, (e) => {
                imgScale = parseFloat(e.target.value);
                draw(); // Draw immediately
            });
        });
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
                        imgScale = Math.max(canvas.width / userImg.width, canvas.height / userImg.height);
                        imgX = (canvas.width - userImg.width * imgScale) / 2;
                        imgY = (canvas.height - userImg.height * imgScale) / 2;
                        downloadBtn.disabled = false;
                        updateSlider();
                        draw();
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
            imgX += (e.offsetX - startX);
            imgY += (e.offsetY - startY);
            startX = e.offsetX;
            startY = e.offsetY;
            draw(); // Draw immediately
        }
    });
    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const factor = e.deltaY < 0 ? 1.05 : 0.95;
        imgScale *= factor;
        updateSlider();
        draw(); // Draw immediately
    }, { passive: false });

    // --- TOUCH (MOBILE) ---
    function getDist(t1, t2) { return Math.hypot(t1.pageX - t2.pageX, t1.pageY - t2.pageY); }

    canvas.addEventListener('touchstart', (e) => {
        // Only prevent default if we're on the canvas to avoid blocking other elements
        if (e.target === canvas) e.preventDefault();
        
        if (e.touches.length === 1) {
            lastTouchX = e.touches[0].pageX;
            lastTouchY = e.touches[0].pageY;
        } else if (e.touches.length === 2) {
            lastPinchDist = getDist(e.touches[0], e.touches[1]);
        }
    }, { passive: false });

    canvas.addEventListener('touchmove', (e) => {
        if (e.target === canvas) e.preventDefault();
        if (e.touches.length === 1) {
            imgX += (e.touches[0].pageX - lastTouchX);
            imgY += (e.touches[0].pageY - lastTouchY);
            lastTouchX = e.touches[0].pageX;
            lastTouchY = e.touches[0].pageY;
        } else if (e.touches.length === 2) {
            const dist = getDist(e.touches[0], e.touches[1]);
            if (lastPinchDist > 0) {
                const scaleFactor = dist / lastPinchDist;
                imgScale = Math.max(0.001, imgScale * scaleFactor); // Prevent scale from hitting 0
                updateSlider();
            }
            lastPinchDist = dist;
        }
        draw(); // Draw immediately
    }, { passive: false });

    canvas.addEventListener('touchend', () => { lastPinchDist = 0; }, { passive: false });

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
