<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCRS Vendor Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h1 class="text-2xl font-bold text-center mb-6">Vendor Portal</h1>
            <p class="text-gray-600 text-center mb-6">Enter your email to receive a login link.</p>

            @if(session('status'))
                <div class="bg-green-100 text-green-700 rounded-md p-3 mb-4 text-sm">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-100 text-red-700 rounded-md p-3 mb-4 text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('vendor.auth.request') }}">
                @csrf
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="email" required class="w-full rounded-md border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500" />
                </div>
                <button type="submit" class="w-full bg-emerald-600 text-white rounded-md py-2 px-4 hover:bg-emerald-700 transition">Send Login Link</button>
            </form>
        </div>
    </div>
</body>
</html>
