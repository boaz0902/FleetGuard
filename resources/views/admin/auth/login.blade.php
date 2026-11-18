<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FleetGuard Admin Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-login-page bg-slate-900 text-slate-100">
    <main class="mx-auto flex min-h-screen max-w-md items-center justify-center p-6">
        <div class="w-full rounded-2xl border border-slate-700 bg-slate-800 p-8 shadow-2xl">
            <h1 class="mb-6 text-2xl font-semibold">FleetGuard Admin</h1>

            @if ($errors->any())
                <div class="mb-4 rounded border border-red-500 bg-red-500/10 p-3 text-sm text-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Email</label>
                    <input id="email" name="email" type="email" required class="w-full rounded-xl border border-slate-600 bg-slate-900 px-3 py-2 text-slate-100 focus:border-cyan-400 focus:outline-none">
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-200">Password</label>
                    <input id="password" name="password" type="password" required class="w-full rounded-xl border border-slate-600 bg-slate-900 px-3 py-2 text-slate-100 focus:border-cyan-400 focus:outline-none">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="remember" value="1">
                    Keep me signed in
                </label>

                <button type="submit" class="w-full rounded-xl bg-cyan-500 px-4 py-2 font-medium text-slate-950 hover:bg-cyan-400">
                    Sign in
                </button>
            </form>
        </div>
    </main>
</body>
</html>
