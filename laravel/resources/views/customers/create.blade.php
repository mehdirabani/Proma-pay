@extends('layouts.app')

@section('title', 'ثبت مشتری')
@section('breadcrumb', 'مشتریان / ثبت مشتری')

@section('content')
    <div class="mb-6">
        <a href="{{ route('customers.index') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">← بازگشت به فهرست</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">ثبت مشتری جدید</h1>
        <p class="mt-2 text-sm text-slate-500">پس از ثبت، یک حساب اعتبار با سقف اولیهٔ صفر برای مشتری ساخته می‌شود.</p>
    </div>

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <p class="font-semibold">لطفاً موارد مشخص‌شده را اصلاح کنید.</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-xs">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('customers.store') }}" class="max-w-3xl overflow-hidden rounded-xl border border-slate-200 bg-white">
        @csrf
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">اطلاعات هویتی</h2><p class="mt-1 text-xs text-slate-500">اطلاعات حساس با رمزنگاری برنامه ذخیره می‌شوند.</p></div>
        <div class="grid grid-cols-1 gap-x-5 gap-y-5 p-5 sm:grid-cols-2 sm:p-6">
            <div class="sm:col-span-2"><label for="full_name" class="mb-2 block text-sm font-medium text-slate-700">نام و نام خانوادگی <span class="text-rose-600">*</span></label><input id="full_name" name="full_name" value="{{ old('full_name') }}" required maxlength="190" autocomplete="name" class="h-12 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 @error('full_name') border-rose-400 @enderror">@error('full_name')<p class="mt-1.5 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
            <div><label for="mobile" class="mb-2 block text-sm font-medium text-slate-700">شمارهٔ همراه <span class="text-rose-600">*</span></label><input id="mobile" name="mobile" value="{{ old('mobile') }}" required inputmode="tel" autocomplete="tel" dir="ltr" placeholder="09123456789" class="h-12 w-full rounded-lg border border-slate-300 px-3 text-left text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 @error('mobile') border-rose-400 @enderror">@error('mobile')<p class="mt-1.5 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
            <div><label for="email" class="mb-2 block text-sm font-medium text-slate-700">ایمیل <span class="text-xs font-normal text-slate-400">اختیاری</span></label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" dir="ltr" placeholder="customer@example.ir" class="h-12 w-full rounded-lg border border-slate-300 px-3 text-left text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 @error('email') border-rose-400 @enderror">@error('email')<p class="mt-1.5 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
            <div><label for="national_id" class="mb-2 block text-sm font-medium text-slate-700">کد ملی <span class="text-xs font-normal text-slate-400">اختیاری</span></label><input id="national_id" name="national_id" value="{{ old('national_id') }}" inputmode="numeric" autocomplete="off" dir="ltr" maxlength="10" class="h-12 w-full rounded-lg border border-slate-300 px-3 text-left text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 @error('national_id') border-rose-400 @enderror"><p class="mt-1.5 text-[11px] text-slate-500">در پایگاه داده به‌صورت رمزنگاری‌شده نگهداری می‌شود.</p>@error('national_id')<p class="mt-1.5 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
            <div><label for="birth_date" class="mb-2 block text-sm font-medium text-slate-700">تاریخ تولد <span class="text-xs font-normal text-slate-400">اختیاری</span></label><input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date') }}" dir="ltr" class="h-12 w-full rounded-lg border border-slate-300 px-3 text-left text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 @error('birth_date') border-rose-400 @enderror"><p class="mt-1.5 text-[11px] text-slate-500">مقدار ذخیره‌شده به میلادی است؛ نمایش تاریخ شمسی است.</p>@error('birth_date')<p class="mt-1.5 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
        </div>
        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50/70 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
            <a href="{{ route('customers.index') }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50">انصراف</a>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-600 px-5 text-sm font-semibold text-white hover:bg-brand-700 focus-visible:outline-brand-600">ثبت مشتری</button>
        </div>
    </form>
@endsection
