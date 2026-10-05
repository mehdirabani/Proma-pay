<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت') | {{ config('app.name', 'پرماپی') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f6fa] text-slate-800 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="hidden min-h-screen flex-col bg-[#17213a] px-4 py-5 text-white lg:flex">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 focus-visible:outline-white">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-sm font-extrabold tracking-tight">P</span>
                <span>
                    <span class="block text-sm font-bold">پرماپی</span>
                    <span class="mt-0.5 block text-xs text-slate-400">سامانه مدیریت مالی</span>
                </span>
            </a>

            <div class="mt-9 px-3 text-[11px] font-semibold tracking-wide text-slate-500">نمای کلی</div>
            <nav aria-label="ناوبری اصلی" class="mt-2 space-y-1">
                <a href="{{ route('dashboard') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition', 'bg-white/10 font-semibold text-white' => request()->routeIs('dashboard'), 'text-slate-300 hover:bg-white/5 hover:text-white' => !request()->routeIs('dashboard')])>
                    <span aria-hidden="true" class="text-base">⌂</span><span>داشبورد</span>
                </a>
            </nav>

            <div class="mt-8 px-3 text-[11px] font-semibold tracking-wide text-slate-500">عملیات</div>
            <nav aria-label="ماژول‌ها" class="mt-2 space-y-1">
                @can('viewAny', App\Models\Customer::class)
                    <a href="{{ route('customers.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition', 'bg-white/10 font-semibold text-white' => request()->routeIs('customers.*'), 'text-slate-300 hover:bg-white/5 hover:text-white' => !request()->routeIs('customers.*')])>
                        <span aria-hidden="true" class="text-base">◉</span><span>مشتریان</span>
                    </a>
                @endcan
                <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-500" aria-disabled="true">
                    <span aria-hidden="true" class="text-base">▤</span><span>قراردادها و اقساط</span><span class="ms-auto text-[10px]">به‌زودی</span>
                </span>
                <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-500" aria-disabled="true">
                    <span aria-hidden="true" class="text-base">↗</span><span>پرداخت و وصول</span><span class="ms-auto text-[10px]">به‌زودی</span>
                </span>
                @can('finance.rules.manage')
                    <a href="{{ route('settings.financial-rules.edit') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition', 'bg-white/10 font-semibold text-white' => request()->routeIs('settings.financial-rules.*'), 'text-slate-300 hover:bg-white/5 hover:text-white' => !request()->routeIs('settings.financial-rules.*')])>
                        <span aria-hidden="true" class="text-base">⚙</span><span>قواعد مالی</span>
                    </a>
                @endcan
            </nav>

            <div class="mt-auto rounded-xl border border-white/10 bg-white/[0.04] p-3">
                <p class="text-xs font-semibold">محیط عملیاتی</p>
                <p class="mt-1 text-[11px] leading-5 text-slate-400">هر اقدام مالی ثبت می‌شود و تغییر اعتبار سابقهٔ غیرقابل‌ویرایش دارد.</p>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/95 backdrop-blur">
                <div class="flex min-h-[68px] items-center justify-between gap-4 px-4 sm:px-6 xl:px-9">
                    <div class="flex items-center gap-3">
                        <details class="group relative lg:hidden">
                            <summary class="grid size-10 cursor-pointer list-none place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" aria-label="باز کردن منو">
                                <span aria-hidden="true">☰</span>
                            </summary>
                            <nav class="absolute right-0 top-12 z-30 w-56 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-xl" aria-label="ناوبری موبایل">
                                <a href="{{ route('dashboard') }}" class="block rounded-lg px-3 py-2.5 hover:bg-slate-50">داشبورد</a>
                                @can('viewAny', App\Models\Customer::class)
                                    <a href="{{ route('customers.index') }}" class="block rounded-lg px-3 py-2.5 hover:bg-slate-50">مشتریان</a>
                                @endcan
                                @can('finance.rules.manage')
                                    <a href="{{ route('settings.financial-rules.edit') }}" class="block rounded-lg px-3 py-2.5 hover:bg-slate-50">قواعد مالی</a>
                                @endcan
                            </nav>
                        </details>
                        <div>
                            <p class="text-xs text-slate-500">پرماپی <span class="mx-1 text-slate-300">/</span> @yield('breadcrumb', 'مدیریت')</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-800" data-persian-today>امروز</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3">
                        <form action="{{ route('customers.index') }}" method="GET" class="hidden md:block">
                            <label class="sr-only" for="global-customer-search">جستجوی مشتری</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">⌕</span>
                                <input id="global-customer-search" name="search" type="search" placeholder="جستجوی نام یا موبایل" class="h-10 w-64 rounded-lg border border-slate-200 bg-slate-50 pe-9 ps-3 text-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-100">
                            </div>
                        </form>
                        <span class="hidden h-8 w-px bg-slate-200 sm:block" aria-hidden="true"></span>
                        <div class="hidden text-left sm:block">
                            <p class="text-xs font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">{{ auth()->user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">خروج</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 sm:py-8 xl:px-9">
                @if (session('success'))
                    <div role="status" class="mb-6 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <span aria-hidden="true">✓</span><span>{{ session('success') }}</span>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
