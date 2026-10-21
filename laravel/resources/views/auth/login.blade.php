<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ورود به پرماپی</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f6fa] font-sans text-slate-800">
    <main class="mx-auto grid min-h-screen max-w-6xl items-center gap-10 px-5 py-12 lg:grid-cols-[1fr_0.9fr] lg:px-12">
        <section class="mx-auto w-full max-w-md">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-3 rounded-xl focus-visible:outline-brand-600">
                <span class="grid size-11 place-items-center rounded-xl bg-brand-600 text-lg font-extrabold text-white">P</span>
                <span class="text-right"><span class="block text-base font-bold">پرماپی</span><span class="mt-0.5 block text-xs text-slate-500">سامانه مدیریت مالی</span></span>
            </a>
            <div class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_12px_36px_-28px_rgba(23,33,58,.38)] sm:p-8">
                <p class="text-xs font-semibold text-brand-700">ورود امن</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">خوش آمدید</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">برای ادامه، با حساب کاربری سازمانی خود وارد شوید.</p>

                <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-700">ایمیل کاری</label>
                        <input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus dir="ltr" class="h-12 w-full rounded-lg border border-slate-300 bg-white px-3 text-left text-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100 @error('email') border-rose-400 @enderror" placeholder="name@company.ir" aria-describedby="email-error">
                        @error('email')<p id="email-error" role="alert" class="mt-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <label for="password" class="text-sm font-medium text-slate-700">گذرواژه</label>
                            <span class="text-xs text-slate-400">حداقل ۱۲ نویسه</span>
                        </div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required dir="ltr" class="h-12 w-full rounded-lg border border-slate-300 bg-white px-3 text-left text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100" aria-describedby="password-error">
                        @error('password')<p id="password-error" role="alert" class="mt-2 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input name="remember" type="checkbox" value="1" class="size-4 rounded border-slate-300 accent-brand-600">
                        <span>مرا به خاطر بسپار</span>
                    </label>
                    <button type="submit" class="flex h-12 w-full items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 focus-visible:outline-brand-600">ورود به پنل</button>
                </form>
                <p class="mt-6 border-t border-slate-100 pt-5 text-xs leading-6 text-slate-500">اگر حساب ندارید، مدیر سامانه می‌تواند آن را از محیط سرور ایجاد کند.</p>
            </div>
            <p class="mt-6 text-center text-[11px] text-slate-400">دسترسی شما بر اساس نقش و شعبه محدود می‌شود.</p>
        </section>

        <aside class="hidden lg:block" aria-label="اطلاعات سامانه">
            <div class="relative overflow-hidden rounded-3xl bg-[#17213a] p-10 text-white">
                <div class="absolute -left-24 -top-20 size-72 rounded-full border border-white/10"></div>
                <div class="absolute -left-10 -top-6 size-44 rounded-full border border-white/10"></div>
                <p class="relative text-xs font-semibold text-violet-200">مرکز عملیات پرماپی</p>
                <h2 class="relative mt-4 max-w-md text-3xl font-bold leading-[1.7]">مدیریت شفاف،<br>تصمیم مطمئن</h2>
                <p class="relative mt-4 max-w-md text-sm leading-7 text-slate-300">مشتری، اعتبار و رویدادهای مالی در یک گردش‌کار ثبت‌شده و قابل پیگیری.</p>
                <div class="relative mt-12 grid grid-cols-3 gap-3 border-t border-white/10 pt-6 text-xs">
                    <div><span class="block text-lg font-bold">۰۱</span><span class="mt-1 block text-slate-400">دادهٔ یکپارچه</span></div>
                    <div><span class="block text-lg font-bold">۰۲</span><span class="mt-1 block text-slate-400">کنترل دسترسی</span></div>
                    <div><span class="block text-lg font-bold">۰۳</span><span class="mt-1 block text-slate-400">ردپای مالی</span></div>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>
