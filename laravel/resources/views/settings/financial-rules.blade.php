@extends('layouts.app')

@section('title', 'قواعد مالی')
@section('breadcrumb', 'تنظیمات / قواعد مالی')

@section('content')
    <div class="mx-auto max-w-4xl space-y-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">قواعد محاسبهٔ مالی</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">این مقادیر در محاسبهٔ پیش‌فاکتور اقساط استفاده می‌شوند. تا زمانی که همهٔ موارد ذخیره نشوند، ثبت پرداخت مسدود است.</p>
        </div>

        @if(! $rules->configured())
            <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">قواعد مالی هنوز تعیین نشده‌اند. نرخ‌ها را پس از تطبیق با سیاست مالی مصوب وارد کنید؛ مقدار خالی یا نرخ پیش‌فرض برای محاسبهٔ پرداخت به کار نمی‌رود.</div>
        @else
            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">نسخهٔ فعال: {{ \App\Support\PersianNumber::format($rules->version) }} · ذخیره‌شده در <time data-persian-date="{{ $rules->updated_at?->toIso8601String() }}">{{ $rules->updated_at?->format('Y-m-d H:i') }}</time></div>
        @endif

        <section class="rounded-xl border border-slate-200 bg-white">
            <form method="POST" action="{{ route('settings.financial-rules.update') }}" class="space-y-5 p-5 sm:p-7">@csrf
                <input type="hidden" name="request_id" value="{{ old('request_id', $requestId) }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="monthly_penalty_rate" class="mb-1.5 block text-xs font-semibold text-slate-700">جریمهٔ عادی ماهانه (درصد)</label>
                        <input id="monthly_penalty_rate" name="monthly_penalty_rate" inputmode="decimal" dir="ltr" required value="{{ old('monthly_penalty_rate', $rules->monthly_penalty_rate) }}" placeholder="مثلاً ۱۰ یا ۱۰.۵" class="h-11 w-full rounded-lg border border-slate-300 px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        @error('monthly_penalty_rate')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="legal_monthly_penalty_rate" class="mb-1.5 block text-xs font-semibold text-slate-700">جریمهٔ حقوقی ماهانه (درصد)</label>
                        <input id="legal_monthly_penalty_rate" name="legal_monthly_penalty_rate" inputmode="decimal" dir="ltr" required value="{{ old('legal_monthly_penalty_rate', $rules->legal_monthly_penalty_rate) }}" placeholder="مثلاً ۲۰" class="h-11 w-full rounded-lg border border-slate-300 px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        @error('legal_monthly_penalty_rate')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="monthly_reward_rate" class="mb-1.5 block text-xs font-semibold text-slate-700">پاداش تسویهٔ زودهنگام ماهانه (درصد)</label>
                        <input id="monthly_reward_rate" name="monthly_reward_rate" inputmode="decimal" dir="ltr" required value="{{ old('monthly_reward_rate', $rules->monthly_reward_rate) }}" placeholder="برای غیرفعال‌بودن: ۰" class="h-11 w-full rounded-lg border border-slate-300 px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        @error('monthly_reward_rate')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="late_penalty_grace_days" class="mb-1.5 block text-xs font-semibold text-slate-700">مهلت ارفاق پس از سررسید (روز)</label>
                        <input id="late_penalty_grace_days" name="late_penalty_grace_days" inputmode="numeric" dir="ltr" required value="{{ old('late_penalty_grace_days', $rules->late_penalty_grace_days) }}" placeholder="۰ تا ۳۶۵" class="h-11 w-full rounded-lg border border-slate-300 px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        @error('late_penalty_grace_days')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                @error('request_id')<p role="alert" class="text-xs text-rose-700">{{ $message }}</p>@enderror
                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="max-w-xl text-[11px] leading-5 text-slate-500">هر ذخیره‌سازی نسخه‌دار و در تاریخچهٔ غیرقابل‌ویرایش ثبت می‌شود. پیش‌فاکتور باز هنگام تغییر قواعد، در زمان ثبت پرداخت دوباره محاسبه و رد می‌شود.</p>
                    <button type="submit" class="h-11 rounded-lg bg-brand-600 px-5 text-sm font-semibold text-white hover:bg-brand-700">ذخیرهٔ قواعد مالی</button>
                </div>
            </form>
        </section>
    </div>
@endsection
