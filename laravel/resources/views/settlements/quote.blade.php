@extends('layouts.app')

@section('title', 'پیش‌فاکتور تسویه')
@section('breadcrumb', 'مشتریان / پیش‌فاکتور تسویه')

@section('content')
    @php
        $snapshot = $quote->plan_snapshot;
        $allocations = collect($snapshot['allocations'] ?? [])->filter(fn ($row) => (int) ($row['allocated_amount'] ?? 0) > 0);
        $scopeLabel = match($quote->scope) {
            'selected' => 'فقط اقساط انتخاب‌شده',
            'schedule' => 'کل اقساط قابل پرداخت قرارداد',
            'forward' => 'از قسط انتخاب‌شده به بعد',
            default => 'نامشخص',
        };
    @endphp

    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <a href="{{ route('customers.show', $quote->customer_id) }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">← بازگشت به پروندهٔ مشتری</a>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">بازبینی پیش‌فاکتور تسویه</h1>
                <p class="mt-2 text-sm text-slate-500">قرارداد <span dir="ltr" class="font-semibold text-slate-700">{{ $quote->contract->contract_number }}</span> · {{ $scopeLabel }}</p>
            </div>
            <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">نسخهٔ محاسبه {{ \App\Support\PersianNumber::format($quote->financial_rules_version) }}</span>
        </div>

        @if(! $quote->isOpen())
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-900">این پیش‌فاکتور قبلاً برای ثبت پرداخت مصرف شده است.</div>
        @elseif($quote->expires_at->lessThanOrEqualTo(now()))
            <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">مهلت پیش‌فاکتور پایان یافته است. از پروندهٔ مشتری مبلغ را دوباره محاسبه کنید.</div>
        @else
            <div role="status" class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs leading-6 text-sky-900">این محاسبه تا <time data-persian-date="{{ $quote->expires_at->toIso8601String() }}">{{ $quote->expires_at->format('Y-m-d H:i') }}</time> معتبر است. پیش از ثبت، مبلغ و ریز تخصیص را با رسید دریافتی تطبیق دهید؛ قواعد و ماندهٔ اقساط هنگام ثبت دوباره بررسی می‌شوند. ماژول حقوقی هنوز در دسترس نیست؛ جریمه و هزینهٔ حقوقی در این مبلغ وارد نشده است.</div>
        @endif

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-[11px] text-slate-500">مبلغی که قرار است دریافت شود</p><p class="mt-2 text-lg font-extrabold text-slate-900">{{ \App\Support\PersianNumber::toman($quote->amount_toman) }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-[11px] text-slate-500">کل بدهی در دامنهٔ انتخاب‌شده</p><p class="mt-2 text-lg font-bold text-slate-800">{{ \App\Support\PersianNumber::toman($quote->full_settlement_toman) }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-[11px] text-slate-500">قسط‌های درگیر</p><p class="mt-2 text-lg font-bold text-slate-800">{{ \App\Support\PersianNumber::format(count($quote->installment_ids)) }} قسط</p></div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">ریز تخصیص پیشنهادی</h2><p class="mt-1 text-xs text-slate-500">ترتیب تخصیص مطابق اولویت مالی و سررسید Legacy است.</p></div>
            @if($allocations->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-slate-500">ردیف تخصیصی در این پیش‌فاکتور وجود ندارد.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($allocations as $row)
                        <article class="grid grid-cols-1 gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div><p class="text-sm font-semibold text-slate-800">قسط {{ \App\Support\PersianNumber::format($row['installment_number']) }} <span class="mx-1 text-slate-300">·</span> سررسید <time data-persian-date="{{ $row['due_date'] }}T12:00:00+03:30">{{ $row['due_date'] }}</time></p><p class="mt-1 text-[11px] leading-5 text-slate-500">اصل {{ \App\Support\PersianNumber::toman($row['principal_applied']) }} · جریمهٔ عادی {{ \App\Support\PersianNumber::toman($row['normal_penalty_applied']) }} · جریمهٔ حقوقی {{ \App\Support\PersianNumber::toman($row['legal_penalty_applied']) }} · پاداش {{ \App\Support\PersianNumber::toman($row['reward_applied']) }}</p></div>
                            <p class="text-sm font-bold text-slate-900">{{ \App\Support\PersianNumber::toman($row['allocated_amount']) }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
            <dl class="grid grid-cols-2 gap-2 border-t border-slate-100 bg-slate-50/70 p-4 sm:grid-cols-4">
                <div><dt class="text-[10px] text-slate-500">اصل مانده</dt><dd class="mt-1 text-xs font-semibold">{{ \App\Support\PersianNumber::toman($snapshot['principal_total'] ?? 0) }}</dd></div>
                <div><dt class="text-[10px] text-slate-500">جریمهٔ عادی</dt><dd class="mt-1 text-xs font-semibold">{{ \App\Support\PersianNumber::toman($snapshot['normal_penalty_total'] ?? 0) }}</dd></div>
                <div><dt class="text-[10px] text-slate-500">جریمهٔ حقوقی</dt><dd class="mt-1 text-xs font-semibold">{{ \App\Support\PersianNumber::toman($snapshot['legal_penalty_total'] ?? 0) }}</dd></div>
                <div><dt class="text-[10px] text-slate-500">پاداش واجد شرایط</dt><dd class="mt-1 text-xs font-semibold">{{ \App\Support\PersianNumber::toman($snapshot['reward_total'] ?? 0) }}</dd></div>
            </dl>
        </section>

        @if($quote->isOpen() && $quote->expires_at->isFuture())
            <section class="rounded-xl border border-rose-200 bg-rose-50/70 p-4 sm:p-5">
                <h2 class="text-sm font-bold text-rose-950">ثبت نهایی دریافت</h2>
                <p class="mt-1 text-xs leading-5 text-rose-900">پس از ثبت، پرداخت به‌صورت نهایی ذخیره و اقساط به‌روزرسانی می‌شوند. مسیر اصلاح یا برگشت پرداخت هنوز در این نسخه پیاده‌سازی نشده است؛ پیش از ادامه، رسید و مبلغ را دوباره کنترل کنید.</p>
                <form method="POST" action="{{ route('settlement-quotes.capture', $quote) }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">@csrf
                    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <div><label for="capture-method" class="mb-1 block text-xs font-semibold text-slate-700">روش دریافت</label><select id="capture-method" name="method" required class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"><option value="cash">نقدی</option><option value="card">کارت بانکی</option><option value="pos">کارت‌خوان</option><option value="bank_transfer">انتقال بانکی</option><option value="manual">ثبت دستی</option></select>@error('method')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                    <div><label for="capture-reference" class="mb-1 block text-xs font-semibold text-slate-700">شمارهٔ پیگیری رسید (اختیاری)</label><input id="capture-reference" name="provider_reference" maxlength="120" dir="auto" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">@error('provider_reference')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                    @error('settlement_quote')<p role="alert" class="text-xs text-rose-700 sm:col-span-2">{{ $message }}</p>@enderror
                    @error('idempotency_key')<p role="alert" class="text-xs text-rose-700 sm:col-span-2">{{ $message }}</p>@enderror
                    <div class="sm:col-span-2"><button type="submit" class="h-11 w-full rounded-lg bg-rose-700 px-4 text-sm font-semibold text-white hover:bg-rose-800">ثبت دریافت {{ \App\Support\PersianNumber::toman($quote->amount_toman) }}</button></div>
                </form>
            </section>
        @endif
    </div>
@endsection
