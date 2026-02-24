@extends('signing.layout')

@section('title', 'Signing Declined')

@section('content')
<div class="p-8 text-center">
    <div class="mx-auto w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mb-6">
        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-2">Signing Declined</h1>
    <p class="text-gray-600 mb-6">
        You have declined to sign this document.
    </p>

    <div class="bg-gray-50 rounded-lg p-4 max-w-md mx-auto text-sm text-gray-600">
        <p>The signing session has been cancelled and all parties have been notified.</p>
        <p class="mt-2">If this was done in error, please contact the person who sent you the signing request.</p>
        <p class="mt-2">You may now close this window.</p>
    </div>
</div>
@endsection
