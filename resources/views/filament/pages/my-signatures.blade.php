<x-filament-panels::page>
    {{-- Existing Signatures --}}
    <x-filament::section>
        <x-slot name="heading">Your Stored Signatures</x-slot>
        <x-slot name="description">Manage your saved signatures and initials for quick reuse when signing documents.</x-slot>

        @if($this->storedSignatures->isEmpty())
            <div class="text-center py-8 text-gray-500">
                <x-heroicon-o-pencil-square class="mx-auto h-12 w-12 text-gray-400" />
                <p class="mt-2 text-sm">No stored signatures yet.</p>
                <p class="text-xs">Add a signature below to use when signing documents.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($this->storedSignatures as $sig)
                    <div class="border rounded-lg p-4 {{ $sig->is_default ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-700' }}">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $sig->label ?? ($sig->type === 'initials' ? 'Initials' : 'Signature') }}
                                </span>
                                @if($sig->is_default)
                                    <span class="ml-1 inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-800 dark:text-primary-300">Default</span>
                                @endif
                            </div>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                {{ ucfirst($sig->type) }}
                            </span>
                        </div>

                        {{-- Signature Preview --}}
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded p-2 mb-3 flex items-center justify-center" style="min-height: 80px;">
                            @if($sig->image_path)
                                <img src="{{ $sig->getImageUrl() }}"
                                     alt="{{ $sig->label }}"
                                     class="max-h-20 max-w-full object-contain"
                                     onerror="this.parentElement.innerHTML='<span class=\'text-xs text-gray-400\'>Preview unavailable</span>'">
                            @else
                                <span class="text-xs text-gray-400">No preview</span>
                            @endif
                        </div>

                        <div class="text-xs text-gray-500 mb-3">
                            Captured via {{ ucfirst($sig->capture_method) }} &middot; {{ $sig->created_at->diffForHumans() }}
                        </div>

                        <div class="flex items-center gap-2">
                            @unless($sig->is_default)
                                <button type="button"
                                        wire:click="setDefault('{{ $sig->id }}')"
                                        class="text-xs text-primary-600 hover:text-primary-800 font-medium">
                                    Set as Default
                                </button>
                            @endunless
                            <button type="button"
                                    wire:click="deleteSignature('{{ $sig->id }}')"
                                    wire:confirm="Are you sure you want to delete this signature?"
                                    class="text-xs text-danger-600 hover:text-danger-800 font-medium">
                                Delete
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- Add New Signature --}}
    <x-filament::section :collapsed="!$showAddForm" collapsible>
        <x-slot name="heading">Add New Signature</x-slot>
        <x-slot name="description">Draw, type, upload, or capture a signature using your camera.</x-slot>

        <div class="space-y-6">
            {{-- Capture Method Tabs --}}
            <div x-data="signatureCapture()" class="space-y-4">
                {{-- Method selector --}}
                <div class="flex space-x-1 border-b border-gray-200 dark:border-gray-700">
                    <template x-for="tab in ['draw', 'type', 'upload', 'webcam']" :key="tab">
                        <button type="button"
                                @click="method = tab"
                                :class="method === tab
                                    ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors capitalize">
                            <span x-text="tab === 'webcam' ? 'Camera' : tab.charAt(0).toUpperCase() + tab.slice(1)"></span>
                        </button>
                    </template>
                </div>

                {{-- Label & Type fields --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Label</label>
                        <input type="text" x-model="label" placeholder="e.g. My formal signature"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                        <select x-model="sigType" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-sm">
                            <option value="signature">Full Signature</option>
                            <option value="initials">Initials</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" x-model="isDefault" class="rounded border-gray-300 text-primary-600">
                            Set as default
                        </label>
                    </div>
                </div>

                {{-- Draw Panel --}}
                <div x-show="method === 'draw'" x-cloak>
                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-800">
                        <canvas x-ref="drawCanvas"
                                class="w-full border border-gray-200 dark:border-gray-600 rounded cursor-crosshair"
                                width="600" height="200"
                                style="touch-action: none;"></canvas>
                    </div>
                    <div class="flex items-center gap-3 mt-2">
                        <button type="button" @click="clearCanvas()"
                                class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                            Clear
                        </button>
                        <button type="button" @click="saveDrawn()"
                                class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
                            Save Signature
                        </button>
                    </div>
                </div>

                {{-- Type Panel --}}
                <div x-show="method === 'type'" x-cloak>
                    <input type="text" x-model="typedName" placeholder="Type your full name"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm text-2xl"
                           style="font-family: 'Brush Script MT', 'Dancing Script', cursive;">
                    <p class="mt-1 text-xs text-gray-500">Your typed name will be rendered as a signature image.</p>
                    <canvas x-ref="typeCanvas" class="hidden" width="600" height="200"></canvas>
                    <button type="button" @click="saveTyped()"
                            class="mt-2 fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
                        Save Signature
                    </button>
                </div>

                {{-- Upload Panel --}}
                <div x-show="method === 'upload'" x-cloak>
                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center">
                        <input type="file" x-ref="uploadInput" accept="image/png,image/jpeg" class="hidden"
                               @change="handleUpload($event)">
                        <button type="button" @click="$refs.uploadInput.click()"
                                class="text-sm text-primary-600 hover:text-primary-800 font-medium">
                            Click to upload a signature image (PNG or JPEG)
                        </button>
                        <img x-ref="uploadPreview" class="mx-auto mt-4 max-h-24 hidden" alt="Uploaded signature">
                    </div>
                    <button type="button" @click="saveUploaded()" x-show="uploadData"
                            class="mt-2 fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
                        Save Signature
                    </button>
                </div>

                {{-- Webcam Panel --}}
                <div x-show="method === 'webcam'" x-cloak>
                    <div class="space-y-3">
                        <div x-show="!cameraActive && !capturedImage">
                            <button type="button" @click="startCamera()"
                                    class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
                                <x-heroicon-m-camera class="w-4 h-4" /> Start Camera
                            </button>
                            <p class="mt-1 text-xs text-gray-500">Hold your signature on white paper up to the camera, then click Capture.</p>
                        </div>

                        <div x-show="cameraActive" class="relative">
                            <video x-ref="webcamVideo" autoplay playsinline
                                   class="w-full max-w-lg rounded-lg border border-gray-300 dark:border-gray-600"></video>
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" @click="captureFromCamera()"
                                        class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-success-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-success-500">
                                    Capture
                                </button>
                                <button type="button" @click="stopCamera()"
                                        class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <div x-show="capturedImage">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Captured Signature:</p>
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded p-2 inline-block">
                                <img :src="capturedImage" class="max-h-24" alt="Captured signature">
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" @click="saveCaptured()"
                                        class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
                                    Save Signature
                                </button>
                                <button type="button" @click="retakeCamera()"
                                        class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                                    Retake
                                </button>
                            </div>
                        </div>
                    </div>
                    <canvas x-ref="webcamCanvas" class="hidden" width="640" height="480"></canvas>
                    <canvas x-ref="processCanvas" class="hidden" width="640" height="480"></canvas>
                </div>
            </div>
        </div>
    </x-filament::section>

    @push('scripts')
    <script>
    function signatureCapture() {
        return {
            method: 'draw',
            label: '',
            sigType: 'signature',
            isDefault: false,
            typedName: '',
            uploadData: null,
            cameraActive: false,
            capturedImage: null,
            stream: null,

            // Simple canvas drawing
            drawing: false,
            hasDrawn: false,
            ctx: null,

            init() {
                this.$nextTick(() => this.initDrawCanvas());
                this.$watch('method', () => {
                    if (this.method === 'draw') {
                        this.$nextTick(() => this.initDrawCanvas());
                    }
                    if (this.method !== 'webcam') {
                        this.stopCamera();
                    }
                });
            },

            initDrawCanvas() {
                const canvas = this.$refs.drawCanvas;
                if (!canvas) return;
                this.ctx = canvas.getContext('2d');
                this.ctx.fillStyle = 'white';
                this.ctx.fillRect(0, 0, canvas.width, canvas.height);
                this.ctx.strokeStyle = 'black';
                this.ctx.lineWidth = 2;
                this.ctx.lineCap = 'round';
                this.hasDrawn = false;

                const getPos = (e) => {
                    const rect = canvas.getBoundingClientRect();
                    const scaleX = canvas.width / rect.width;
                    const scaleY = canvas.height / rect.height;
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
                };

                canvas.onmousedown = (e) => { this.drawing = true; const p = getPos(e); this.ctx.beginPath(); this.ctx.moveTo(p.x, p.y); };
                canvas.onmousemove = (e) => { if (!this.drawing) return; this.hasDrawn = true; const p = getPos(e); this.ctx.lineTo(p.x, p.y); this.ctx.stroke(); };
                canvas.onmouseup = () => { this.drawing = false; };
                canvas.onmouseleave = () => { this.drawing = false; };
                canvas.ontouchstart = (e) => { e.preventDefault(); this.drawing = true; const p = getPos(e); this.ctx.beginPath(); this.ctx.moveTo(p.x, p.y); };
                canvas.ontouchmove = (e) => { e.preventDefault(); if (!this.drawing) return; this.hasDrawn = true; const p = getPos(e); this.ctx.lineTo(p.x, p.y); this.ctx.stroke(); };
                canvas.ontouchend = () => { this.drawing = false; };
            },

            clearCanvas() {
                const canvas = this.$refs.drawCanvas;
                if (!canvas || !this.ctx) return;
                this.ctx.fillStyle = 'white';
                this.ctx.fillRect(0, 0, canvas.width, canvas.height);
                this.ctx.strokeStyle = 'black';
                this.hasDrawn = false;
            },

            saveDrawn() {
                if (!this.hasDrawn) { alert('Please draw your signature first.'); return; }
                const canvas = this.$refs.drawCanvas;
                const base64 = canvas.toDataURL('image/png').replace(/^data:image\/\w+;base64,/, '');
                this.$wire.saveDrawnSignature(base64, this.label, this.sigType, 'draw', this.isDefault);
            },

            saveTyped() {
                if (!this.typedName.trim()) { alert('Please type your name.'); return; }
                const canvas = this.$refs.typeCanvas;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = 'white';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = 'black';
                ctx.font = '48px "Brush Script MT", "Dancing Script", cursive';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(this.typedName, canvas.width / 2, canvas.height / 2);
                const base64 = canvas.toDataURL('image/png').replace(/^data:image\/\w+;base64,/, '');
                this.$wire.saveDrawnSignature(base64, this.label || this.typedName, this.sigType, 'type', this.isDefault);
            },

            handleUpload(event) {
                const file = event.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.uploadData = e.target.result;
                    this.$refs.uploadPreview.src = e.target.result;
                    this.$refs.uploadPreview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            },

            saveUploaded() {
                if (!this.uploadData) { alert('Please upload a signature image.'); return; }
                const base64 = this.uploadData.replace(/^data:image\/\w+;base64,/, '');
                this.$wire.saveDrawnSignature(base64, this.label, this.sigType, 'upload', this.isDefault);
            },

            async startCamera() {
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } } });
                    this.$refs.webcamVideo.srcObject = this.stream;
                    this.cameraActive = true;
                    this.capturedImage = null;
                } catch (err) {
                    alert('Unable to access camera. Please ensure camera permissions are granted.');
                    console.error('Camera error:', err);
                }
            },

            stopCamera() {
                if (this.stream) {
                    this.stream.getTracks().forEach(t => t.stop());
                    this.stream = null;
                }
                this.cameraActive = false;
            },

            captureFromCamera() {
                const video = this.$refs.webcamVideo;
                const canvas = this.$refs.webcamCanvas;
                const ctx = canvas.getContext('2d');

                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                // Process: extract signature (dark strokes) from white/light background
                this.capturedImage = this.processSignatureImage(canvas);
                this.stopCamera();
            },

            processSignatureImage(sourceCanvas) {
                const processCanvas = this.$refs.processCanvas;
                const ctx = processCanvas.getContext('2d');
                processCanvas.width = sourceCanvas.width;
                processCanvas.height = sourceCanvas.height;
                ctx.drawImage(sourceCanvas, 0, 0);

                const imageData = ctx.getImageData(0, 0, processCanvas.width, processCanvas.height);
                const data = imageData.data;
                const threshold = 128;

                for (let i = 0; i < data.length; i += 4) {
                    const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
                    if (gray > threshold) {
                        // Light pixel → make transparent (white background removal)
                        data[i] = 255; data[i + 1] = 255; data[i + 2] = 255; data[i + 3] = 0;
                    } else {
                        // Dark pixel → keep as black signature stroke
                        data[i] = 0; data[i + 1] = 0; data[i + 2] = 0; data[i + 3] = 255;
                    }
                }

                ctx.putImageData(imageData, 0, 0);
                return processCanvas.toDataURL('image/png');
            },

            retakeCamera() {
                this.capturedImage = null;
                this.startCamera();
            },

            saveCaptured() {
                if (!this.capturedImage) { alert('No captured signature.'); return; }
                const base64 = this.capturedImage.replace(/^data:image\/\w+;base64,/, '');
                this.$wire.saveDrawnSignature(base64, this.label, this.sigType, 'webcam', this.isDefault);
            }
        };
    }
    </script>
    @endpush
</x-filament-panels::page>
