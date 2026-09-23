<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AuraPay — Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @vite('resources/js/aurapay.js')
    <style>
        .lang-en .text-bg { display: none !important; }
        .lang-bg .text-en { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased flex flex-col items-center justify-center min-h-screen relative">

    <button onclick="toggleLanguage()" class="absolute top-6 right-6 bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-full font-bold text-sm hover:bg-gray-50 transition shadow-sm">
        <span class="text-bg">EN</span>
        <span class="text-en">БГ</span>
    </button>

    <!-- Интерактивно лого -->
    <a href="/welcome" class="mb-8 flex justify-center w-full transition transform hover:scale-105">
        <img src="/images/logo.png" alt="AuraPay Logo" class="h-24 object-contain" 
             onerror="this.outerHTML='<h1 class=\'text-4xl font-bold text-blue-600 tracking-wider\'>AuraPay</h1>'">
    </a>

    <div class="w-full max-w-md bg-white rounded-lg shadow-xl p-8">
        <h2 class="text-center font-bold text-xl text-gray-700 mb-8">
    <span class="text-bg">Вход в демонстрационния профил</span>
    <span class="text-en">Login to Demonstration Profile</span>
</h2>

@if (request('reason') === 'idle-timeout')
    <div class="mb-6 rounded-md border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
        <span class="text-bg">
            Сесията Ви изтече след 5 минути неактивност.
            Моля, влезте отново.
        </span>

        <span class="text-en">
            Your session expired after 5 minutes of inactivity.
            Please sign in again.
        </span>
    </div>
@endif

<form method="POST" action="/login" class="space-y-5">
            @csrf
            <div>
                <label class="block font-semibold mb-2 text-gray-700">Email</label>
                <input type="email" name="email" class="w-full border border-gray-300 rounded-md p-3 focus:ring-2 focus:ring-blue-500 outline-none transition" required>
            </div>
            <div>
                <label class="block font-semibold mb-2 text-gray-700">
                    <span class="text-bg">Парола</span>
                    <span class="text-en">Password</span>
                </label>
                <input type="password" name="password" class="w-full border border-gray-300 rounded-md p-3 focus:ring-2 focus:ring-blue-500 outline-none transition" required>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-md hover:bg-blue-700 transition mt-6">
                <span class="text-bg">Вход с парола</span>
                <span class="text-en">Login with Password</span>
            </button>
            
            <button
    id="passkey-login"
    type="button"
    class="w-full bg-gray-100 text-gray-700 border border-gray-300 font-bold py-3 rounded-md hover:bg-gray-200 transition mt-3"
>
                <span class="text-bg">Вход с Passkey</span>
                <span class="text-en">Login with Passkey</span>
            </button>
        </form>

    <div class="mt-6 text-center text-sm text-gray-600">
    <span class="text-bg">Нямате демонстрационен профил?</span>
    <span class="text-en">Don't have a demonstration account?</span>

    <a 
        href="/register" 
        class="ml-1 font-semibold text-blue-600 hover:text-blue-700"
    >
        <span class="text-bg">Регистрация</span>
        <span class="text-en">Register</span>
    </a>
</div>

    </div>

    <script>
        function toggleLanguage() {
            const body = document.body;
            const newLang = body.classList.contains('lang-bg') ? 'lang-en' : 'lang-bg';
            body.classList.remove('lang-bg', 'lang-en');
            body.classList.add(newLang);
            localStorage.setItem('aurapay_lang', newLang);
        }
        document.addEventListener('DOMContentLoaded', () => {
            const savedLang = localStorage.getItem('aurapay_lang') || 'lang-bg';
            document.body.classList.add(savedLang);
        });
    </script>
</body>
</html>