<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>پرماپی</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="grid min-h-screen place-items-center bg-[#f5f6fa] px-5 text-slate-800">
    <main class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <span class="mx-auto grid size-12 place-items-center rounded-xl bg-brand-600 text-lg font-extrabold text-white">P</span>
        <h1 class="mt-5 text-2xl font-bold">پرماپی</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">سامانهٔ یکپارچهٔ مدیریت مشتری، اعتبار و عملیات مالی.</p>
        <a href="{{ route('login') }}" class="mt-6 inline-flex h-11 items-center justify-center rounded-lg bg-brand-600 px-5 text-sm font-semibold text-white hover:bg-brand-700">ورود به سامانه</a>
    </main>
</body>
</html>
