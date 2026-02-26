@extends('signing.layout')

@section('title', 'Sign: ' . ($contract->title ?? 'Document'))

@push('head')
<style>
    #signature-pad-canvas {
        touch-action: none;
    }
    .tab-active {
        background-color: #4f46e5;
        color: #ffffff;
    }
    .tab-inactive {
        background-color: #f3f4f6;
        color: #374151;
    }
</style>
@endpush

@section('content')
<div class="p-6 lg:p-8">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $contract->title ?? 'Document' }}</h1>
        <p class="mt-1 text-sm text-gray-600">
            Signing as: <span class="font-medium">{{ $signer->signer_name }}</span>
            ({{ $signer->signer_email }})
        </p>
    </div>

    {{-- PDF Viewer --}}
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Document Preview</h2>
        <div id="pdf-viewer"
             class="border border-gray-300 rounded-lg bg-gray-50 overflow-auto"
             style="max-height: 600px;"
             data-pdf-url="{{ route('signing.document', $rawToken) }}"
             data-require-all-pages="{{ $session->require_all_pages_viewed ? '1' : '0' }}"
             data-require-page-initials="{{ $session->require_page_initials ? '1' : '0' }}"
             role="document"
             aria-label="Contract PDF viewer">
            <div id="pdf-loading" class="flex items-center justify-center py-20 text-gray-500" role="status" aria-label="Loading document">
                <svg class="animate-spin h-6 w-6 mr-3" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Loading document...
            </div>
            <div id="pdf-pages"></div>
        </div>
    </div>

    {{-- Signing Form --}}
    <form id="signing-form"
          method="POST"
          action="{{ route('signing.submit', $rawToken) }}">
        @csrf

        {{-- Form Fields --}}
        @if ($session->fields && $session->fields->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Required Fields</h2>
            <div class="space-y-4">
                @foreach ($session->fields as $field)
                <div>
                    <label for="field-{{ $field->id }}"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        {{ $field->label }}
                        @if ($field->is_required)
                            <span class="text-red-500">*</span>
                        @endif
                    </label>

                    @if ($field->field_type === 'text')
                        <input type="text"
                               id="field-{{ $field->id }}"
                               name="fields[{{ $loop->index }}][value]"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                               {{ $field->is_required ? 'required' : '' }}>
                    @elseif ($field->field_type === 'date')
                        <input type="date"
                               id="field-{{ $field->id }}"
                               name="fields[{{ $loop->index }}][value]"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                               {{ $field->is_required ? 'required' : '' }}>
                    @elseif ($field->field_type === 'checkbox')
                        <div class="flex items-center">
                            <input type="checkbox"
                                   id="field-{{ $field->id }}"
                                   name="fields[{{ $loop->index }}][value]"
                                   value="true"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                   {{ $field->is_required ? 'required' : '' }}>
                            <span class="ml-2 text-sm text-gray-600">{{ $field->label }}</span>
                        </div>
                    @else
                        <input type="text"
                               id="field-{{ $field->id }}"
                               name="fields[{{ $loop->index }}][value]"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                               {{ $field->is_required ? 'required' : '' }}>
                    @endif

                    <input type="hidden" name="fields[{{ $loop->index }}][id]" value="{{ $field->id }}">
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Stored Signatures --}}
        @if (isset($storedSignatures) && $storedSignatures->isNotEmpty())
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Saved Signatures</h2>
            <p class="text-sm text-gray-500 mb-3">Click a saved signature to use it, or create a new one below.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach ($storedSignatures as $stored)
                @php $storedImageUrl = $stored->getImageUrl(); @endphp
                <div class="stored-signature-item border border-gray-200 rounded-lg p-3 cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition-colors"
                     data-image-src="{{ $storedImageUrl }}"
                     data-sig-type="{{ $stored->type }}">
                    <div class="bg-white border border-gray-100 rounded p-2 flex items-center justify-center mb-2" style="min-height: 60px;">
                        <img src="{{ $storedImageUrl }}"
                             alt="{{ $stored->label }}"
                             class="max-h-14 max-w-full object-contain"
                             onerror="this.style.display='none'">
                    </div>
                    <div class="text-xs text-gray-600 truncate">
                        {{ $stored->label ?? ucfirst($stored->type) }}
                        @if ($stored->is_default)
                            <span class="text-indigo-600 font-medium">(default)</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Signature Area --}}
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Your Signature</h2>

            {{-- Signature Method Tabs --}}
            <div class="flex space-x-1 mb-4" role="tablist" aria-label="Signature method">
                <button type="button"
                        class="signature-tab tab-active px-4 py-2 rounded-md text-sm font-medium transition-colors"
                        data-method="draw"
                        role="tab"
                        aria-selected="true"
                        aria-controls="tab-panel-draw">
                    Draw
                </button>
                <button type="button"
                        class="signature-tab tab-inactive px-4 py-2 rounded-md text-sm font-medium transition-colors"
                        data-method="type"
                        role="tab"
                        aria-selected="false"
                        aria-controls="tab-panel-type">
                    Type
                </button>
                <button type="button"
                        class="signature-tab tab-inactive px-4 py-2 rounded-md text-sm font-medium transition-colors"
                        data-method="upload"
                        role="tab"
                        aria-selected="false"
                        aria-controls="tab-panel-upload">
                    Upload
                </button>
                <button type="button"
                        class="signature-tab tab-inactive px-4 py-2 rounded-md text-sm font-medium transition-colors"
                        data-method="webcam"
                        role="tab"
                        aria-selected="false"
                        aria-controls="tab-panel-webcam">
                    Camera
                </button>
            </div>

            {{-- Draw Panel --}}
            <div id="tab-panel-draw" class="signature-panel" role="tabpanel">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-2 bg-white">
                    <canvas id="signature-pad-canvas"
                            class="w-full border border-gray-200 rounded"
                            width="600"
                            height="200"
                            aria-label="Signature drawing area">
                    </canvas>
                </div>
                <button type="button"
                        id="clear-signature"
                        class="mt-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    Clear signature
                </button>
            </div>

            {{-- Type Panel --}}
            <div id="tab-panel-type" class="signature-panel hidden" role="tabpanel">
                <input type="text"
                       id="typed-signature"
                       placeholder="Type your full name"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-2xl"
                       style="font-family: 'Brush Script MT', 'Dancing Script', cursive;">
                <p class="mt-1 text-xs text-gray-500">Your typed name will be used as your signature.</p>
            </div>

            {{-- Upload Panel --}}
            <div id="tab-panel-upload" class="signature-panel hidden" role="tabpanel">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                    <input type="file"
                           id="signature-upload"
                           accept="image/png,image/jpeg"
                           class="hidden">
                    <label for="signature-upload" class="cursor-pointer">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-600">Click to upload a signature image</p>
                        <p class="text-xs text-gray-400">PNG or JPEG</p>
                    </label>
                    <img id="signature-upload-preview" class="mx-auto mt-4 max-h-24 hidden" alt="Uploaded signature preview">
                </div>
            </div>

            {{-- Webcam Panel --}}
            <div id="tab-panel-webcam" class="signature-panel hidden" role="tabpanel">
                <div id="webcam-start" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <button type="button" id="start-camera-btn" class="mt-3 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        Start Camera
                    </button>
                    <p class="mt-1 text-xs text-gray-400">Hold your signature on white paper up to the camera</p>
                </div>
                <div id="webcam-active" class="hidden">
                    <video id="webcam-video" autoplay playsinline class="w-full max-w-lg rounded-lg border border-gray-300"></video>
                    <div class="flex items-center space-x-3 mt-2">
                        <button type="button" id="capture-btn" class="bg-green-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-green-700">
                            Capture
                        </button>
                        <button type="button" id="stop-camera-btn" class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                            Cancel
                        </button>
                    </div>
                </div>
                <div id="webcam-captured" class="hidden">
                    <p class="text-sm font-medium text-gray-700 mb-2">Captured Signature:</p>
                    <div class="bg-white border border-gray-200 rounded p-2 inline-block">
                        <img id="webcam-preview" class="max-h-24" alt="Captured signature">
                    </div>
                    <div class="flex items-center space-x-3 mt-2">
                        <button type="button" id="accept-capture-btn" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700">
                            Use This Signature
                        </button>
                        <button type="button" id="retake-btn" class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                            Retake
                        </button>
                    </div>
                </div>
                <canvas id="webcam-canvas" class="hidden" width="640" height="480"></canvas>
                <canvas id="webcam-process-canvas" class="hidden" width="640" height="480"></canvas>
            </div>

            {{-- Hidden inputs for form submission --}}
            <input type="hidden" name="signature_image" id="signature-image-input">
            <input type="hidden" name="signature_method" id="signature-method-input" value="draw">
            @if (isset($storedSignatures))
                @php $defaultInitials = $storedSignatures->where('type', 'initials')->where('is_default', true)->first(); @endphp
                @if ($defaultInitials)
                    <input type="hidden" id="stored-initials-data" value="auto">
                @endif
            @endif
        </div>

        {{-- Save for future use --}}
        <div class="mb-6">
            <label class="flex items-center space-x-2 cursor-pointer">
                <input type="checkbox" name="save_signature" value="1"
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-600">Save this signature for future use</span>
            </label>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between border-t border-gray-200 pt-6">
            <button type="button"
                    id="decline-btn"
                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                Decline to Sign
            </button>

            <button type="submit"
                    id="submit-btn"
                    class="bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                Submit Signature
            </button>
        </div>
    </form>

    {{-- Decline Modal --}}
    <div id="decline-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" role="dialog" aria-modal="true" aria-labelledby="decline-modal-title">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
            <h3 id="decline-modal-title" class="text-lg font-semibold text-gray-900 mb-4">Decline to Sign</h3>
            <p class="text-sm text-gray-600 mb-4">
                Are you sure you want to decline? This will cancel the signing session for all parties.
            </p>
            <form method="POST" action="{{ route('signing.decline', $rawToken) }}">
                @csrf
                <div class="mb-4">
                    <label for="decline-reason" class="block text-sm font-medium text-gray-700 mb-1">
                        Reason (optional)
                    </label>
                    <textarea id="decline-reason"
                              name="reason"
                              rows="3"
                              maxlength="1000"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:ring-red-500 focus:border-red-500"
                              placeholder="Please provide a reason for declining..."></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                            id="cancel-decline"
                            class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Decline &amp; Cancel Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{--
    pdf.js is loaded from CDN rather than the npm package because bundling its
    Web Worker via Vite requires non-trivial configuration. The signing page is
    a standalone view outside Filament, so a CDN script tag is acceptable here.
    signature_pad was also removed from npm — signing.js includes a built-in
    canvas fallback that provides equivalent draw functionality.
--}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"
    integrity="sha384-/1qUCSGwTur9vjf/z9lmu/eCUYbpOTgSjmpbMQZ1/CtX2v/WcAIKqRv+U1DUCG6e"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"></script>
<script>
    // Set pdf.js worker
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
</script>
@vite('resources/js/signing.js')
@endpush
