@extends('signing.layout')

@section('title', 'Signing Complete')

@section('content')
<div class="p-8 text-center">
    <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-6">
        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-2">Thank You, {{ $signer->signer_name }}!</h1>
    <p class="text-gray-600 mb-6">
        Your signature has been recorded successfully.
    </p>

    <div class="bg-gray-50 rounded-lg p-4 max-w-md mx-auto text-sm text-gray-600">
        <p>You will receive an email confirmation once all parties have completed signing.</p>
        <p class="mt-2">You may now close this window.</p>
    </div>
</div>
@endsection
