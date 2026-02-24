<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Document Signing') - CCRS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('head')
</head>
<body class="bg-gray-100 min-h-screen">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-4 focus:py-2 focus:bg-white focus:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:rounded-md">
        Skip to main content
    </a>

    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <span class="text-lg font-semibold text-gray-900">CCRS</span>
            </div>
            <span class="ml-auto text-sm text-gray-500">Document Signing</span>
        </div>
    </header>

    <main id="main-content" tabindex="-1" class="max-w-5xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md">
            @yield('content')
        </div>
    </main>

    <footer class="max-w-5xl mx-auto px-4 py-6 text-center text-xs text-gray-400">
        Powered by CCRS &mdash; Contract &amp; Merchant Agreement Repository System
    </footer>

    @stack('scripts')
</body>
</html>
