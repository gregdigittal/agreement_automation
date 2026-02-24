/**
 * CCRS In-House Signing Module
 *
 * Handles PDF rendering (via pdf.js), signature capture (via signature_pad),
 * signature method tabs, and form submission.
 */

document.addEventListener('DOMContentLoaded', function () {
    const pdfViewer = document.getElementById('pdf-viewer');
    if (!pdfViewer) return; // Not on a signing page

    initPdfViewer();
    initSignaturePad();
    initSignatureTabs();
    initDeclineModal();
    initFormSubmission();
});

// ---------------------------------------------------------------------------
// PDF Viewer
// ---------------------------------------------------------------------------

function initPdfViewer() {
    const viewer = document.getElementById('pdf-viewer');
    const pagesContainer = document.getElementById('pdf-pages');
    const loading = document.getElementById('pdf-loading');
    const pdfUrl = viewer?.dataset?.pdfUrl;

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

            const renderPage = function (pageNum) {
                pdf.getPage(pageNum).then(function (page) {
                    const scale = 1.5;
                    const viewport = page.getViewport({ scale: scale });

                    const wrapper = document.createElement('div');
                    wrapper.className = 'pdf-page mb-4 flex justify-center';

                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    canvas.className = 'shadow-sm';

                    wrapper.appendChild(canvas);
                    pagesContainer.appendChild(wrapper);

                    const context = canvas.getContext('2d');
                    page.render({
                        canvasContext: context,
                        viewport: viewport,
                    });

                    if (pageNum < pdf.numPages) {
                        renderPage(pageNum + 1);
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

        const method = document.getElementById('signature-method-input')?.value || 'draw';
        const imageInput = document.getElementById('signature-image-input');
        let signatureData = null;

        if (method === 'draw') {
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
        }

        if (!signatureData) {
            alert('Please provide a signature before submitting.');
            return;
        }

        // Strip data URL prefix to get raw base64
        const base64Data = signatureData.replace(/^data:image\/\w+;base64,/, '');
        if (imageInput) {
            imageInput.value = base64Data;
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
