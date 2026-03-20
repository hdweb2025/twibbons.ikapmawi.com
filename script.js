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
        if (zoomSlider) zoomSlider.value = imgScale;
    }

    function resetImageState() {
        imgScale = Math.max(canvas.width / userImg.width, canvas.height / userImg.height);
        imgX = canvas.width / 2;
        imgY = canvas.height / 2;
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

    canvas.addEventListener('mousedown', (e) => handlePanStart(e.offsetX, e.offsetY));
    canvas.addEventListener('mousemove', (e) => handlePanMove(e.offsetX, e.offsetY));
    window.addEventListener('mouseup', handlePanEnd);
    canvas.addEventListener('mouseleave', handlePanEnd);

    canvas.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
            handlePanStart(e.touches[0].pageX, e.touches[0].pageY);
        } else if (e.touches.length === 2) {
            lastPinchDist = getDist(e.touches[0], e.touches[1]);
        }
    }, { passive: true });

    canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        if (e.touches.length === 1) {
            handlePanMove(e.touches[0].pageX, e.touches[0].pageY);
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
            draw();
        }
    }, { passive: false });

    canvas.addEventListener('touchend', (e) => {
        if (e.touches.length < 2) lastPinchDist = 0;
        if (e.touches.length < 1) handlePanEnd();
    });

    function getDist(t1, t2) { return Math.hypot(t1.pageX - t2.pageX, t1.pageY - t2.pageY); }
    function getMidpoint(t1, t2) { return { x: (t1.pageX + t2.pageX) / 2, y: (t1.pageY + t2.pageY) / 2 }; }

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
