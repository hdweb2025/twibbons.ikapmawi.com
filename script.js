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

    // --- DOWNLOAD ---
    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            if (!userImg.src) return;

            // Generate filename based on event slug
            const eventId = canvas.getAttribute('data-event-id');
            const filename = 'twibbon-ikapmawi-' + (eventId || 'frame') + '.png';

            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/png', 1.0);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Silent tracking usage
            if (eventId) {
                fetch('/track_usage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'event_id=' + encodeURIComponent(eventId)
                }).catch(err => console.error(err));
            }
        });
    }
})();
