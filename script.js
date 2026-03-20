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
            // Simpan state canvas agar transform tidak merusak gambar lain
            ctx.save();
            ctx.translate(imgX, imgY);
            ctx.scale(imgScale, imgScale);
            ctx.drawImage(userImg, 0, 0);
            ctx.restore();
        }

        if (templateImg.complete) {
            ctx.drawImage(templateImg, 0, 0, canvas.width, canvas.height);
        }
    }

    function resetImageState() {
        // Logika "Cover": Foto memenuhi canvas secara proporsional
        const scaleW = canvas.width / userImg.width;
        const scaleH = canvas.height / userImg.height;
        imgScale = Math.max(scaleW, scaleH);
        
        // Posisikan di tengah
        imgX = (canvas.width - userImg.width * imgScale) / 2;
        imgY = (canvas.height - userImg.height * imgScale) / 2;
        
        if (zoomSlider) {
            // Atur range slider dinamis berdasarkan ukuran foto
            zoomSlider.min = (imgScale * 0.5).toFixed(2);
            zoomSlider.max = (imgScale * 3).toFixed(2);
            zoomSlider.step = "0.01";
            zoomSlider.value = imgScale;
        }
        draw();
    }

    // --- ZOOM SLIDER FIX ---
    if (zoomSlider) {
        zoomSlider.addEventListener('input', (e) => {
            const newScale = parseFloat(e.target.value);
            
            // Zoom dari titik tengah foto
            const centerX = imgX + (userImg.width * imgScale) / 2;
            const centerY = imgY + (userImg.height * imgScale) / 2;
            
            imgX = centerX - (userImg.width * newScale) / 2;
            imgY = centerY - (userImg.height * newScale) / 2;
            
            imgScale = newScale;
            draw();
        });
    }

    // --- MOUSE & WHEEL ---
    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const delta = e.deltaY > 0 ? 0.95 : 1.05;
        const newScale = imgScale * delta;
        
        // Update slider agar sinkron
        if (zoomSlider) zoomSlider.value = newScale;
        
        imgScale = newScale;
        draw();
    }, { passive: false });

    // --- TOUCH (MOBILE PINCH RESIZE) ---
    function getDist(t1, t2) { return Math.hypot(t1.pageX - t2.pageX, t1.pageY - t2.pageY); }

    canvas.addEventListener('touchmove', (e) => {
        if (e.touches.length === 2) {
            e.preventDefault();
            const currentDist = getDist(e.touches[0], e.touches[1]);
            
            if (lastPinchDist > 0) {
                const delta = currentDist / lastPinchDist;
                imgScale *= delta;
                if (zoomSlider) zoomSlider.value = imgScale;
            }
            lastPinchDist = currentDist;
            draw();
        }
    }, { passive: false });

    // --- UPLOAD ---
    upload.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (ev) => {
            userImg = new Image(); // Reset instance
            userImg.onload = () => {
                resetImageState();
                downloadBtn.disabled = false;
            };
            userImg.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    // Event listener lainnya (mousedown, mousemove, dsb) tetap sama seperti sebelumnya...
    // (Tambahkan kembali sisa kode mouse & download milikmu di sini)
})();