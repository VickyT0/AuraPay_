<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AuraPay — Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @vite('resources/js/aurapay.js')
    <style>
        .lang-en .text-bg { display: none !important; }
        .lang-bg .text-en { display: none !important; }
    </style>
</head>
<body id="aurapay-dashboard" data-wallet-id="{{ auth()->user()->wallet?->id }}" class="bg-gray-100 font-sans text-gray-800 antialiased h-screen flex overflow-hidden">

    <aside class="w-72 bg-white shadow-lg flex flex-col h-full z-10 relative">
        <!-- Интерактивно лого -->
        <a href="/welcome" class="p-6 border-b flex justify-center items-center h-24 hover:bg-gray-50 transition" title="Към началото">
            <img src="/images/logo.png" alt="AuraPay" class="h-12 object-contain"
                 onerror="this.outerHTML='<h1 class=\'text-2xl font-bold text-blue-600 tracking-wider\'>AuraPay</h1>'">
        </a>
        
        <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
            <button onclick="switchTab(event, 'wallet')" class="tab-btn w-full text-left px-4 py-3 rounded-md bg-blue-50 text-blue-700 font-semibold transition">
                <span class="text-bg">Портфейл (Wallet)</span><span class="text-en">Wallet</span>
            </button>
            <button onclick="switchTab(event, 'security')" class="tab-btn w-full text-left px-4 py-3 rounded-md text-gray-600 hover:bg-gray-50 font-semibold transition">
                <span class="text-bg">Сигурност (Security)</span><span class="text-en">Security</span>
            </button>
            <button onclick="switchTab(event, 'consent')" class="tab-btn w-full text-left px-4 py-3 rounded-md text-gray-600 hover:bg-gray-50 font-semibold transition">
                <span class="text-bg">Съгласия (Consent)</span><span class="text-en">Consent</span>
            </button>
            <button onclick="switchTab(event, 'send-money')" class="tab-btn w-full text-left px-4 py-3 rounded-md text-gray-600 hover:bg-gray-50 font-semibold transition">
                <span class="text-bg">Превод (A2A)</span><span class="text-en">Send money (A2A)</span>
            </button>
            <button onclick="switchTab(event, 'qr-payment')" class="tab-btn w-full text-left px-4 py-3 rounded-md text-gray-600 hover:bg-gray-50 font-semibold transition">
                <span class="text-bg">QR плащане</span><span class="text-en">QR payment</span>
            </button>
            <button onclick="switchTab(event, 'transactions')" class="tab-btn w-full text-left px-4 py-3 rounded-md text-gray-600 hover:bg-gray-50 font-semibold transition">
                <span class="text-bg">Транзакции</span><span class="text-en">Transactions</span>
            </button>
        </nav>
        
        <div class="p-4 border-t text-center text-sm text-gray-500">
            <span class="text-bg">Влезли сте като</span><span class="text-en">Signed in as</span> <br><span class="font-semibold">{{ auth()->user()->email }}</span><br>
            <form method="POST" action="/logout" class="mt-2">
                @csrf
                <button type="submit" class="text-blue-600 hover:underline">
                    <span class="text-bg">Изход</span><span class="text-en">Log out</span>
                </button>
            </form>
        </div>
    </aside>

    <main class="flex-1 p-10 overflow-y-auto bg-gray-50 relative">
        
        <!-- Бутон за език в Dashboard -->
        <button onclick="toggleLanguage()" class="absolute top-6 right-6 bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-full font-bold text-sm hover:bg-gray-50 transition shadow-sm z-50">
            <span class="text-bg">EN</span>
            <span class="text-en">БГ</span>
        </button>

        <div class="max-w-4xl mx-auto mt-8">
            
            <section id="wallet" class="tab-content block bg-white p-8 rounded-lg shadow-sm border border-gray-100">
                <h2 class="text-2xl font-bold mb-6 text-gray-800"><span class="text-bg">Портфейл</span><span class="text-en">Wallet</span></h2>
                <p class="mb-6 text-lg"><span class="text-bg">Баланс:</span><span class="text-en">Balance:</span> 
                    <strong id="wallet-balance" class="text-2xl text-green-600">—</strong>
                </p>
                <form id="top-up-form" class="flex gap-4">
                    <input type="number" name="amount" step="0.01" min="0.01" placeholder="Amount" required class="border border-gray-300 rounded-md p-3 w-48 focus:ring-2 focus:ring-blue-500 outline-none">
                    <button type="submit" class="bg-gray-200 text-gray-800 font-semibold px-6 py-3 rounded-md hover:bg-gray-300 transition">
                        <span class="text-bg">Тестово зареждане</span><span class="text-en">Test top-up</span>
                    </button>
                </form>
            </section>

            <section id="security" class="tab-content hidden bg-white p-8 rounded-lg shadow-sm border border-gray-100">
                <h2 class="text-2xl font-bold mb-6 text-gray-800"><span class="text-bg">Сигурност</span><span class="text-en">Security</span></h2>
                <button id="passkey-register" type="button" class="bg-gray-200 text-gray-800 font-semibold px-6 py-3 rounded-md hover:bg-gray-300 transition">
                    <span class="text-bg">Регистрирай това устройство като Passkey</span><span class="text-en">Register this device as a Passkey</span>
                </button>
            </section>

            <section id="consent" class="tab-content hidden bg-white p-8 rounded-lg shadow-sm border border-gray-100">
                <h2 class="text-2xl font-bold mb-6 text-gray-800"><span class="text-bg">Управление на съгласия</span><span class="text-en">Consent Management</span></h2>
                <div class="flex gap-4 mb-6">
                    <button type="button" class="grant-consent bg-gray-200 text-gray-800 font-semibold px-6 py-3 rounded-md hover:bg-gray-300 transition" data-scope="account_info">Grant account_info</button>
                    <button type="button" class="grant-consent bg-gray-200 text-gray-800 font-semibold px-6 py-3 rounded-md hover:bg-gray-300 transition" data-scope="payment_initiation">Grant payment_initiation</button>
                </div>
                <ul id="consents-list" class="list-disc pl-5 text-gray-600"></ul>
            </section>

            <section id="send-money" class="tab-content hidden bg-white p-8 rounded-lg shadow-sm border border-gray-100">
                <h2 class="text-2xl font-bold mb-6 text-gray-800"><span class="text-bg">Превод от сметка в сметка (A2A)</span><span class="text-en">Account-to-Account Transfer (A2A)</span></h2>
                <form id="a2a-form" class="space-y-4">
                    <div class="flex gap-4">
                        <input type="number" name="receiver_wallet_id" placeholder="Receiver wallet ID" required class="border border-gray-300 rounded-md p-3 w-1/2 focus:ring-2 focus:ring-blue-500 outline-none">
                        <input type="text" name="receiver_name" placeholder="Receiver name" required class="border border-gray-300 rounded-md p-3 w-1/2 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div class="flex gap-4 items-center">
                        <input type="number" name="amount" step="0.01" min="0.01" placeholder="Amount" required class="border border-gray-300 rounded-md p-3 w-1/3 focus:ring-2 focus:ring-blue-500 outline-none">
                        <button id="vop-check" type="button" class="bg-gray-200 text-gray-800 font-semibold px-6 py-3 rounded-md hover:bg-gray-300 transition">
                            <span class="text-bg">Провери получател</span><span class="text-en">Check recipient</span>
                        </button>
                        <button type="submit" class="bg-blue-600 text-white font-semibold px-8 py-3 rounded-md hover:bg-blue-700 transition">
                            <span class="text-bg">Изпрати</span><span class="text-en">Send</span>
                        </button>
                    </div>
                </form>
                <span id="vop-result" class="block mt-4 font-bold text-gray-700"></span>
            </section>

            <section id="qr-payment" class="tab-content hidden space-y-8">
                <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-100">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800"><span class="text-bg">Създаване на QR заявка</span><span class="text-en">Create QR Payment Request</span></h2>
                    <form id="qr-create-form" class="flex gap-4 mb-6">
                        <input type="number" name="amount" step="0.01" placeholder="Amount" class="border border-gray-300 rounded-md p-3 w-48 focus:ring-2 focus:ring-blue-500 outline-none">
                        <input type="text" name="note" placeholder="Note" class="border border-gray-300 rounded-md p-3 w-64 focus:ring-2 focus:ring-blue-500 outline-none">
                        <button type="submit" class="bg-gray-200 text-gray-800 font-semibold px-6 py-3 rounded-md hover:bg-gray-300 transition">
                            <span class="text-bg">Генерирай</span><span class="text-en">Create</span>
                        </button>
                    </form>
                    <div class="text-gray-600 space-y-2">
                        <p>Reference: <code id="qr-reference-output" class="font-mono bg-gray-100 px-2 py-1 rounded break-all">—</code></p>
                        <p>Signature: <code id="qr-signature-output" class="font-mono bg-gray-100 px-2 py-1 rounded break-all">—</code></p>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-lg shadow-sm border border-gray-100">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800"><span class="text-bg">Плащане по референция</span><span class="text-en">Pay via Reference</span></h2>
                    <form id="qr-pay-form" class="space-y-4">
                        <div class="flex gap-4">
                            <input type="text" name="reference" placeholder="QR reference" required class="border border-gray-300 rounded-md p-3 w-1/2 focus:ring-2 focus:ring-blue-500 outline-none">
                            <input type="text" name="signature" placeholder="QR signature" class="border border-gray-300 rounded-md p-3 w-1/2 focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div class="flex gap-4">
                            <input type="number" name="amount" step="0.01" placeholder="Amount if not fixed" class="border border-gray-300 rounded-md p-3 w-1/3 focus:ring-2 focus:ring-blue-500 outline-none">
                            <button type="submit" class="bg-blue-600 text-white font-semibold px-8 py-3 rounded-md hover:bg-blue-700 transition">
                                <span class="text-bg">Плати</span><span class="text-en">Pay</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section id="transactions" class="tab-content hidden bg-white p-8 rounded-lg shadow-sm border border-gray-100">
                <h2 class="text-2xl font-bold mb-6 text-gray-800"><span class="text-bg">Транзакции</span><span class="text-en">Transactions</span></h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-4 font-semibold text-gray-700">Reference</th>
                                <th class="p-4 font-semibold text-gray-700">Type</th>
                                <th class="p-4 font-semibold text-gray-700">Amount</th>
                                <th class="p-4 font-semibold text-gray-700">Status</th>
                                <th class="p-4 font-semibold text-gray-700">Risk</th>
                            </tr>
                        </thead>
                        <tbody id="transactions-body"></tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>
    
    <script>
        function switchTab(event, tabId) {
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('block');
                el.classList.add('hidden');
            });
            document.getElementById(tabId).classList.remove('hidden');
            document.getElementById(tabId).classList.add('block');

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('bg-blue-50', 'text-blue-700');
                btn.classList.add('text-gray-600');
            });
            event.currentTarget.classList.remove('text-gray-600');
            event.currentTarget.classList.add('bg-blue-50', 'text-blue-700');
        }

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