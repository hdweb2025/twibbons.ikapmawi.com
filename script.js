const canvas = document.getElementById('mainCanvas');

if (canvas) {
    const ctx = canvas.getContext('2d');
    const upload = document.getElementById('upload');
    const downloadBtn = document.getElementById('download');

    let userImg = new Image();
    let templateImg = new Image();

    // State for photo positioning
    let imgX = 0, imgY = 0, imgScale = 1;
    let isDragging = false;
    let startX, startY;

    // Load template from the selected event
    const templateSrc = canvas.getAttribute('data-template');
    if (templateSrc) {
        templateImg.crossOrigin = "anonymous"; // Hindari isu CORS jika perlu
        templateImg.src = templateSrc;
        templateImg.onload = () => {
            console.log("Template loaded:", templateSrc);
            draw();
        };
        templateImg.onerror = () => {
            console.error("Failed to load template:", templateSrc);
        };
    }

    upload.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            // First, upload the original photo to the server
            const formData = new FormData();
            formData.append('photo', file);

            fetch('upload_photo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Gagal mengunggah foto: ' + data.error);
                    return;
                }

                // If upload is successful, then process the image for the canvas
                const reader = new FileReader();
                reader.onload = (event) => {
                    userImg.src = event.target.result;
                    userImg.onload = () => {
                        // Center the image initially
                        imgScale = Math.max(canvas.width / userImg.width, canvas.height / userImg.height);
                        imgX = (canvas.width - userImg.width * imgScale) / 2;
                        imgY = (canvas.height - userImg.height * imgScale) / 2;
                        downloadBtn.disabled = false;
                        draw();
                    };
                };
                reader.readAsDataURL(file);
            })
            .catch(error => {
                console.error('Upload error:', error);
                alert('Terjadi kesalahan saat mengunggah foto.');
            });
        }
    });

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // 1. Draw user image
        if (userImg.src) {
            ctx.drawImage(userImg, imgX, imgY, userImg.width * imgScale, userImg.height * imgScale);
        }
        
        // 2. Draw template on top
        if (templateImg.complete) {
            ctx.drawImage(templateImg, 0, 0, canvas.width, canvas.height);
        }
    }

    // Mouse/Touch events for positioning
    canvas.addEventListener('mousedown', (e) => {
        isDragging = true;
        startX = e.offsetX;
        startY = e.offsetY;
    });

    window.addEventListener('mouseup', () => isDragging = false);

    canvas.addEventListener('mousemove', (e) => {
        if (isDragging) {
            const dx = e.offsetX - startX;
            const dy = e.offsetY - startY;
            imgX += dx;
            imgY += dy;
            startX = e.offsetX;
            startY = e.offsetY;
            draw();
        }
    });

    // Zoom with mouse wheel
    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const scaleFactor = 1.1;
        if (e.deltaY < 0) imgScale *= scaleFactor;
        else imgScale /= scaleFactor;
        draw();
    }, { passive: false });

    downloadBtn.addEventListener('click', () => {
        const link = document.createElement('a');
        link.download = 'twibbon-ikapmawi.png';
        link.href = canvas.toDataURL('image/png');
        link.click();

        // Record usage in background
        const eventId = canvas.getAttribute('data-event-id');
        const formData = new FormData();
        formData.append('event_id', eventId);
        fetch('record_usage.php', {
            method: 'POST',
            body: formData
        });
    });
}
