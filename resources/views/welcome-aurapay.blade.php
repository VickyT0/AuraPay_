<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AuraPay — Welcome</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .lang-en .text-bg { display: none !important; }
        .lang-bg .text-en { display: none !important; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-white h-screen flex flex-col items-center justify-center font-sans text-center px-4 relative">

    <!-- Бутон за смяна на езика -->
    <button onclick="toggleLanguage()" class="absolute top-6 right-6 bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-full font-bold text-sm hover:bg-gray-50 transition shadow-sm">
        <span class="text-bg">EN</span>
        <span class="text-en">БГ</span>
    </button>

    <img src="/images/logo.png" alt="AuraPay" class="h-32 mb-8 object-contain"
         onerror="this.outerHTML='<h1 class=\'text-5xl font-bold text-blue-600 tracking-wider mb-8\'>AuraPay</h1>'">
    
    <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-6 tracking-tight">
        <span class="text-bg">Добре дошли в AuraPay</span>
        <span class="text-en">Welcome to AuraPay</span>
    </h1>
    
    <p class="text-xl md:text-2xl text-gray-600 max-w-2xl leading-relaxed mb-10">
        <span class="text-bg">Вашият сигурен дигитален партньор. Прототип за отворено банкиране, обединяващ безкомпромисна сигурност, бързина и иновативни финансови технологии.</span>
        <span class="text-en">Your secure digital partner. An open banking prototype combining uncompromising security, speed, and innovative financial technologies.</span>
    </p>
    
    <a href="/login" class="bg-blue-600 text-white font-bold py-3 px-10 rounded-full hover:bg-blue-700 transition shadow-lg text-lg">
        <span class="text-bg">Към портала</span>
        <span class="text-en">Go to Portal</span>
    </a>

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