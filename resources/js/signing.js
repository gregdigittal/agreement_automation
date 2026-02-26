/**
 * CCRS In-House Signing Module
 *
 * Handles PDF rendering (via pdf.js), signature capture (via signature_pad),
 * signature method tabs (draw, type, upload, webcam), page-by-page enforcement,
 * and form submission.
 */

document.addEventListener('DOMContentLoaded', function () {
    const pdfViewer = document.getElementById('pdf-viewer');
    if (!pdfViewer) return; // Not on a signing page

    initPdfViewer();
    initSignaturePad();
    initSignatureTabs();
    initWebcamCapture();
    initDeclineModal();
    initFormSubmission();
    initStoredSignatures();
});

// ---------------------------------------------------------------------------
// PDF Viewer (with page tracking for enforcement)
// ---------------------------------------------------------------------------

let totalPages = 0;
let viewedPages = new Set();
let requireAllPagesViewed = false;
let requirePageInitials = false;

function initPdfViewer() {
    const viewer = document.getElementById('pdf-viewer');
    const pagesContainer = document.getElementById('pdf-pages');
    const loading = document.getElementById('pdf-loading');
    const pdfUrl = viewer?.dataset?.pdfUrl;

    // Read enforcement flags from data attributes
    requireAllPagesViewed = viewer?.dataset?.requireAllPages === '1';
    requirePageInitials = viewer?.dataset?.requirePageInitials === '1';

    if (!pdfUrl || typeof pdfjsLib === 'undefined') {
        if (loading) {
            loading.textContent = 'Unable to load PDF viewer.';
        }
        return;
    }

    const loadingTask = pdfjsLib.getDocument(pdfUrl);

    loadingTask.promise
        .then(function (pdf) {
            if (loading) loading.style.display = 'none';
            totalPages = pdf.numPages;

            // Show page progress bar if enforcement is active
            if (requireAllPagesViewed || requirePageInitials) {
                createPageProgressBar(viewer, totalPages);
            }

            const renderPage = function (pageNum) {
                pdf.getPage(pageNum).then(function (page) {
                    const scale = 1.5;
                    const viewport = page.getViewport({ scale: scale });

                    const wrapper = document.createElement('div');
                    wrapper.className = 'pdf-page mb-4 flex justify-center relative';
                    wrapper.dataset.pageNumber = pageNum;

                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    canvas.className = 'shadow-sm';

                    wrapper.appendChild(canvas);

                    // Add page number label
                    const pageLabel = document.createElement('div');
                    pageLabel.className = 'absolute top-2 right-2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-70';
                    pageLabel.textContent = 'Page ' + pageNum + ' of ' + pdf.numPages;
                    wrapper.appendChild(pageLabel);

                    pagesContainer.appendChild(wrapper);

                    const context = canvas.getContext('2d');
                    page.render({
                        canvasContext: context,
                        viewport: viewport,
                    });

                    if (pageNum < pdf.numPages) {
                        renderPage(pageNum + 1);
                    } else {
                        // All pages rendered — set up Intersection Observer for tracking
                        if (requireAllPagesViewed || requirePageInitials) {
                            initPageTracking();
                        }
                    }
                });
            };

            renderPage(1);
        })
        .catch(function (error) {
            console.error('Error loading PDF:', error);
            if (loading) {
                loading.innerHTML =
                    '<p class="text-red-500">Failed to load document. Please try refreshing the page.</p>';
            }
        });
}

// ---------------------------------------------------------------------------
// Page Enforcement — Intersection Observer
// ---------------------------------------------------------------------------

function createPageProgressBar(viewer, total) {
    const bar = document.createElement('div');
    bar.id = 'page-progress-bar';
    bar.className = 'mb-3 bg-white border border-gray-200 rounded-lg p-3';

    const label = document.createElement('div');
    label.className = 'flex items-center justify-between mb-2';
    label.innerHTML = '<span class="text-sm font-medium text-gray-700">Page Progress</span>' +
        '<span id="page-progress-count" class="text-sm text-gray-500">0 / ' + total + ' pages viewed</span>';
    bar.appendChild(label);

    const track = document.createElement('div');
    track.className = 'w-full bg-gray-200 rounded-full h-2';
    const fill = document.createElement('div');
    fill.id = 'page-progress-fill';
    fill.className = 'bg-indigo-600 h-2 rounded-full transition-all duration-300';
    fill.style.width = '0%';
    track.appendChild(fill);
    bar.appendChild(track);

    const badges = document.createElement('div');
    badges.id = 'page-badges';
    badges.className = 'flex flex-wrap gap-1 mt-2';
    for (let i = 1; i <= total; i++) {
        const badge = document.createElement('span');
        badge.className = 'page-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500';
        badge.dataset.pageNumber = i;
        badge.textContent = i;
        badges.appendChild(badge);
    }
    bar.appendChild(badges);

    viewer.parentElement.insertBefore(bar, viewer);
}

function initPageTracking() {
    const pages = document.querySelectorAll('.pdf-page');
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                const pageNum = parseInt(entry.target.dataset.pageNumber, 10);
                if (pageNum && !viewedPages.has(pageNum)) {
                    viewedPages.add(pageNum);
                    updatePageProgress();
                }
            }
        });
    }, { threshold: 0.5 });

    pages.forEach(function (page) {
        observer.observe(page);
    });
}

function updatePageProgress() {
    const count = viewedPages.size;
    const countEl = document.getElementById('page-progress-count');
    const fillEl = document.getElementById('page-progress-fill');
    const badges = document.querySelectorAll('.page-badge');

    if (countEl) countEl.textContent = count + ' / ' + totalPages + ' pages viewed';
    if (fillEl) fillEl.style.width = (count / totalPages * 100) + '%';

    badges.forEach(function (badge) {
        const num = parseInt(badge.dataset.pageNumber, 10);
        if (viewedPages.has(num)) {
            badge.className = 'page-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700';
        }
    });

    // Enable/disable submit button based on page progress
    updateSubmitButtonState();
}

function updateSubmitButtonState() {
    const submitBtn = document.getElementById('submit-btn');
    if (!submitBtn) return;

    if (requireAllPagesViewed && viewedPages.size < totalPages) {
        submitBtn.disabled = true;
        submitBtn.title = 'Please view all pages before submitting (' + viewedPages.size + '/' + totalPages + ')';
    } else {
        submitBtn.disabled = false;
        submitBtn.title = '';
    }
}

// ---------------------------------------------------------------------------
// Signature Pad (Draw)
// ---------------------------------------------------------------------------

let signaturePad = null;

function initSignaturePad() {
    const canvas = document.getElementById('signature-pad-canvas');
    if (!canvas) return;

    // Resize canvas to match display size
    function resizeCanvas() {
        const container = canvas.parentElement;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = container.offsetWidth - 16; // account for padding
        canvas.height = 200;
        canvas.getContext('2d').scale(ratio, ratio);
    }

    resizeCanvas();

    // Use signature_pad if available, otherwise fallback to simple canvas drawing
    if (typeof SignaturePad !== 'undefined') {
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)',
        });
    } else {
        // Simple canvas drawing fallback
        signaturePad = createSimpleSignaturePad(canvas);
    }

    // Clear button
    const clearBtn = document.getElementById('clear-signature');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (signaturePad) {
                signaturePad.clear();
            }
        });
    }

    // Handle window resize
    window.addEventListener('resize', function () {
        const data = signaturePad && !signaturePad.isEmpty() ? signaturePad.toDataURL() : null;
        resizeCanvas();
        if (signaturePad && typeof SignaturePad !== 'undefined') {
            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(0, 0, 0)',
            });
            if (data) {
                signaturePad.fromDataURL(data);
            }
        }
    });
}

/**
 * Simple fallback signature pad using plain canvas API.
 */
function createSimpleSignaturePad(canvas) {
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let hasDrawn = false;

    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = 'black';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top,
        };
    }

    canvas.addEventListener('mousedown', function (e) {
        drawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    });

    canvas.addEventListener('mousemove', function (e) {
        if (!drawing) return;
        hasDrawn = true;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    });

    canvas.addEventListener('mouseup', function () {
        drawing = false;
    });

    canvas.addEventListener('mouseleave', function () {
        drawing = false;
    });

    // Touch support
    canvas.addEventListener('touchstart', function (e) {
        e.preventDefault();
        drawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    });

    canvas.addEventListener('touchmove', function (e) {
        e.preventDefault();
        if (!drawing) return;
        hasDrawn = true;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    });

    canvas.addEventListener('touchend', function () {
        drawing = false;
    });

    return {
        isEmpty: function () {
            return !hasDrawn;
        },
        clear: function () {
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = 'black';
            hasDrawn = false;
        },
        toDataURL: function (type) {
            return canvas.toDataURL(type || 'image/png');
        },
    };
}

// ---------------------------------------------------------------------------
// Webcam Capture
// ---------------------------------------------------------------------------

let webcamStream = null;
let webcamCapturedData = null;

function initWebcamCapture() {
    const startBtn = document.getElementById('start-camera-btn');
    const captureBtn = document.getElementById('capture-btn');
    const stopBtn = document.getElementById('stop-camera-btn');
    const acceptBtn = document.getElementById('accept-capture-btn');
    const retakeBtn = document.getElementById('retake-btn');

    if (!startBtn) return; // No webcam panel on this page

    startBtn.addEventListener('click', startCamera);
    if (captureBtn) captureBtn.addEventListener('click', captureFromCamera);
    if (stopBtn) stopBtn.addEventListener('click', stopCamera);
    if (acceptBtn) acceptBtn.addEventListener('click', acceptCapture);
    if (retakeBtn) retakeBtn.addEventListener('click', retakeCapture);
}

async function startCamera() {
    try {
        webcamStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } }
        });

        const video = document.getElementById('webcam-video');
        if (video) {
            video.srcObject = webcamStream;
        }

        showWebcamState('active');
    } catch (err) {
        alert('Unable to access camera. Please ensure camera permissions are granted and you are using HTTPS.');
        console.error('Camera access error:', err);
    }
}

function stopCamera() {
    if (webcamStream) {
        webcamStream.getTracks().forEach(function (track) { track.stop(); });
        webcamStream = null;
    }
    showWebcamState('start');
}

function captureFromCamera() {
    const video = document.getElementById('webcam-video');
    const canvas = document.getElementById('webcam-canvas');
    if (!video || !canvas) return;

    const ctx = canvas.getContext('2d');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Process the captured image to extract signature
    webcamCapturedData = processSignatureFromCamera(canvas);

    const preview = document.getElementById('webcam-preview');
    if (preview) preview.src = webcamCapturedData;

    stopCamera();
    showWebcamState('captured');
}

/**
 * Process a camera capture to extract dark signature strokes from a light background.
 * Similar to macOS Preview signature capture.
 */
function processSignatureFromCamera(sourceCanvas) {
    const processCanvas = document.getElementById('webcam-process-canvas');
    if (!processCanvas) return sourceCanvas.toDataURL('image/png');

    const ctx = processCanvas.getContext('2d');
    processCanvas.width = sourceCanvas.width;
    processCanvas.height = sourceCanvas.height;
    ctx.drawImage(sourceCanvas, 0, 0);

    const imageData = ctx.getImageData(0, 0, processCanvas.width, processCanvas.height);
    const data = imageData.data;
    const threshold = 128;

    for (let i = 0; i < data.length; i += 4) {
        // Convert to grayscale
        const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;

        if (gray > threshold) {
            // Light pixel — make white/transparent (background removal)
            data[i] = 255;
            data[i + 1] = 255;
            data[i + 2] = 255;
            data[i + 3] = 0;
        } else {
            // Dark pixel — keep as black signature stroke
            data[i] = 0;
            data[i + 1] = 0;
            data[i + 2] = 0;
            data[i + 3] = 255;
        }
    }

    ctx.putImageData(imageData, 0, 0);
    return processCanvas.toDataURL('image/png');
}

function acceptCapture() {
    // webcamCapturedData is already set — it will be used during form submission
    // Nothing else needed; the form submission handler checks for webcam data
}

function retakeCapture() {
    webcamCapturedData = null;
    startCamera();
}

function showWebcamState(state) {
    var startEl = document.getElementById('webcam-start');
    var activeEl = document.getElementById('webcam-active');
    var capturedEl = document.getElementById('webcam-captured');

    if (startEl) startEl.classList.toggle('hidden', state !== 'start');
    if (activeEl) activeEl.classList.toggle('hidden', state !== 'active');
    if (capturedEl) capturedEl.classList.toggle('hidden', state !== 'captured');
}

// ---------------------------------------------------------------------------
// Stored Signatures
// ---------------------------------------------------------------------------

function initStoredSignatures() {
    var storedItems = document.querySelectorAll('.stored-signature-item');
    storedItems.forEach(function (item) {
        item.addEventListener('click', function () {
            var imgSrc = item.dataset.imageSrc;
            var sigType = item.dataset.sigType;

            if (imgSrc) {
                // Set signature image and mark method
                var imageInput = document.getElementById('signature-image-input');
                var methodInput = document.getElementById('signature-method-input');

                // Fetch the stored signature image and convert to base64
                fetch(imgSrc)
                    .then(function (resp) { return resp.blob(); })
                    .then(function (blob) {
                        var reader = new FileReader();
                        reader.onload = function () {
                            var base64 = reader.result.replace(/^data:image\/\w+;base64,/, '');
                            if (imageInput) imageInput.value = base64;
                            if (methodInput) methodInput.value = 'draw'; // stored sigs use the same pipeline
                        };
                        reader.readAsDataURL(blob);
                    });

                // Highlight selected
                storedItems.forEach(function (s) {
                    s.classList.remove('ring-2', 'ring-indigo-500');
                });
                item.classList.add('ring-2', 'ring-indigo-500');
            }
        });
    });
}

// ---------------------------------------------------------------------------
// Signature Method Tabs
// ---------------------------------------------------------------------------

function initSignatureTabs() {
    const tabs = document.querySelectorAll('.signature-tab');
    const panels = document.querySelectorAll('.signature-panel');
    const methodInput = document.getElementById('signature-method-input');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const method = tab.dataset.method;

            // Update tab styles
            tabs.forEach(function (t) {
                t.classList.remove('tab-active');
                t.classList.add('tab-inactive');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.remove('tab-inactive');
            tab.classList.add('tab-active');
            tab.setAttribute('aria-selected', 'true');

            // Show/hide panels
            panels.forEach(function (p) {
                p.classList.add('hidden');
            });
            const targetPanel = document.getElementById('tab-panel-' + method);
            if (targetPanel) {
                targetPanel.classList.remove('hidden');
            }

            // Update hidden method input
            if (methodInput) {
                methodInput.value = method;
            }

            // Stop camera when switching away from webcam tab
            if (method !== 'webcam' && webcamStream) {
                stopCamera();
            }
        });
    });
}

// ---------------------------------------------------------------------------
// Decline Modal
// ---------------------------------------------------------------------------

function initDeclineModal() {
    const declineBtn = document.getElementById('decline-btn');
    const modal = document.getElementById('decline-modal');
    const cancelBtn = document.getElementById('cancel-decline');

    if (declineBtn && modal) {
        declineBtn.addEventListener('click', function () {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    }

    if (cancelBtn && modal) {
        cancelBtn.addEventListener('click', function () {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });
    }

    // Close on backdrop click
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
    }
}

// ---------------------------------------------------------------------------
// Form Submission
// ---------------------------------------------------------------------------

function initFormSubmission() {
    const form = document.getElementById('signing-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Check page enforcement
        if (requireAllPagesViewed && viewedPages.size < totalPages) {
            alert('Please scroll through all pages before submitting. You have viewed ' + viewedPages.size + ' of ' + totalPages + ' pages.');
            return;
        }

        const method = document.getElementById('signature-method-input')?.value || 'draw';
        const imageInput = document.getElementById('signature-image-input');
        let signatureData = null;

        // Check if a stored signature was already selected (imageInput pre-filled)
        if (imageInput && imageInput.value && imageInput.value.length > 100) {
            // Stored signature already set — use it directly
            signatureData = 'pre-filled';
        } else if (method === 'draw') {
            if (signaturePad && signaturePad.isEmpty()) {
                alert('Please provide your signature before submitting.');
                return;
            }
            signatureData = signaturePad ? signaturePad.toDataURL('image/png') : null;
        } else if (method === 'type') {
            const typedInput = document.getElementById('typed-signature');
            const typedName = typedInput ? typedInput.value.trim() : '';
            if (!typedName) {
                alert('Please type your name to use as a signature.');
                return;
            }
            signatureData = generateTypedSignatureImage(typedName);
        } else if (method === 'upload') {
            const preview = document.getElementById('signature-upload-preview');
            if (!preview || preview.classList.contains('hidden')) {
                alert('Please upload a signature image before submitting.');
                return;
            }
            signatureData = preview.src;
        } else if (method === 'webcam') {
            if (!webcamCapturedData) {
                alert('Please capture your signature using the camera before submitting.');
                return;
            }
            signatureData = webcamCapturedData;
        }

        if (!signatureData) {
            alert('Please provide a signature before submitting.');
            return;
        }

        // For non-pre-filled signatures, strip data URL prefix
        if (signatureData !== 'pre-filled') {
            const base64Data = signatureData.replace(/^data:image\/\w+;base64,/, '');
            if (imageInput) {
                imageInput.value = base64Data;
            }
        }

        // Disable submit button
        const submitBtn = document.getElementById('submit-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        }

        form.submit();
    });

    // Handle signature upload preview
    const uploadInput = document.getElementById('signature-upload');
    if (uploadInput) {
        uploadInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                const preview = document.getElementById('signature-upload-preview');
                if (preview) {
                    preview.src = event.target.result;
                    preview.classList.remove('hidden');
                }
            };
            reader.readAsDataURL(file);
        });
    }

    // Initial submit button state
    if (requireAllPagesViewed) {
        updateSubmitButtonState();
    }
}

/**
 * Generate a signature image from typed text using canvas.
 */
function generateTypedSignatureImage(text) {
    const canvas = document.createElement('canvas');
    canvas.width = 600;
    canvas.height = 200;
    const ctx = canvas.getContext('2d');

    // White background
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Draw text in cursive style
    ctx.fillStyle = 'black';
    ctx.font = '48px "Brush Script MT", "Dancing Script", cursive';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, canvas.width / 2, canvas.height / 2);

    return canvas.toDataURL('image/png');
}
