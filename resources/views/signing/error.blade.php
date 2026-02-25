@extends('signing.layout')

@section('title', 'Signing Error')

@section('content')
<div class="p-8 text-center">
    <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-6">
        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-2">Unable to Access Signing</h1>
    <p class="text-gray-600 mb-6">
        {{ $message ?? 'An error occurred while loading the signing page.' }}
    </p>

    <div class="bg-gray-50 rounded-lg p-4 max-w-md mx-auto text-sm text-gray-600">
        <p>If you believe this is an error, please contact the person who sent you the signing request to obtain a new link.</p>
    </div>
</div>
@endsection
