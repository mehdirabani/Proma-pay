@extends('layouts.app')

@section('title', 'مدارک انتقال بانکی')
@section('breadcrumb', 'پرداخت و وصول / مدارک انتقال بانکی')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">مدارک انتقال بانکی</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">فایل مدرک خصوصی است. تأیید، مبلغ و ماندهٔ جاری قسط را دوباره محاسبه می‌کند و ثبت پرداخت و تخصیص را در یک تراکنش انجام می‌دهد.</p>
        </div>
        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900"><span class="size-2 rounded-full bg-amber-500" aria-hidden="true"></span>{{ \App\Support\PersianNumber::format($receipts->total()) }} مدرک در انتظار بررسی</span>
    </div>

    <section class="mt-6 space-y-4" aria-label="فهرست مدارک در انتظار بررسی">
        @forelse($receipts as $receipt)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-bold text-slate-900">{{ $receipt->customer->full_name }}</h2>
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-800">در انتظار بررسی</span>
                        </div>
                        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-xs sm:grid-cols-2 xl:grid-cols-4">
                            <div><dt class="text-slate-500">قرارداد</dt><dd class="mt-1 font-semibold text-slate-800" dir="ltr">{{ $receipt->contract->contract_number }}</dd></div>
                            <div><dt class="text-slate-500">قسط</dt><dd class="mt-1 font-semibold text-slate-800">{{ \App\Support\PersianNumber::format($receipt->installment->installment_number) }} · {{ \App\Support\PersianNumber::toman($receipt->amount_toman) }}</dd></div>
                            <div><dt class="text-slate-500">ثبت‌کننده</dt><dd class="mt-1 font-semibold text-slate-800">{{ $receipt->submittedBy?->name ?? 'حساب حذف‌شده' }}</dd></div>
                            <div><dt class="text-slate-500">زمان ثبت</dt><dd class="mt-1 font-semibold text-slate-800"><time data-persian-date="{{ $receipt->created_at->toIso8601String() }}">{{ $receipt->created_at->format('Y-m-d H:i') }}</time></dd></div>
                        </dl>
                        <p class="mt-3 text-[11px] text-slate-500">قالب {{ strtoupper(str_replace(['image/', 'application/'], '', $receipt->file_mime)) }} · {{ \App\Support\PersianNumber::format((string) round($receipt->file_size / 1024)) }} کیلوبایت</p>
                    </div>
                    <a href="{{ route('payment-receipts.file', $receipt) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500">دانلود امن مدرک</a>
                </div>

                @can('payments.receipt.review')
                    <div class="mt-4 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 xl:grid-cols-2">
                        <form method="POST" action="{{ route('payment-receipts.review', $receipt) }}" class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3">@csrf
                            <input type="hidden" name="request_id" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="decision" value="approved">
                            <label for="receipt-approved-amount-{{ $receipt->id }}" class="mb-1 block text-[11px] font-semibold text-emerald-950">مبلغ قابل تأیید (در صورت تفاوت اصلاح کنید)</label>
                            <input id="receipt-approved-amount-{{ $receipt->id }}" name="approved_amount_toman" inputmode="numeric" dir="ltr" placeholder="خالی بماند: {{ \App\Support\PersianNumber::toman($receipt->amount_toman) }}" class="h-10 w-full rounded-lg border border-emerald-200 bg-white px-3 text-left text-xs focus:border-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-100">
                            @error('approved_amount_toman')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                            <label for="receipt-approve-note-{{ $receipt->id }}" class="mb-1 mt-3 block text-[11px] font-semibold text-emerald-950">شرح تطبیق</label>
                            <textarea id="receipt-approve-note-{{ $receipt->id }}" name="review_note" required minlength="5" maxlength="1000" rows="2" class="w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-100"></textarea>
                            @error('review_note')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                            <p class="mt-2 text-[10px] leading-5 text-emerald-900">پرداخت بانکی فقط بعد از کنترل دوبارهٔ مانده و قواعد مالی ثبت می‌شود.</p>
                            <button type="submit" class="mt-3 h-10 w-full rounded-lg bg-emerald-700 px-3 text-xs font-semibold text-white hover:bg-emerald-800">تأیید و تخصیص به قسط</button>
                        </form>
                        <form method="POST" action="{{ route('payment-receipts.review', $receipt) }}" class="rounded-lg border border-rose-200 bg-rose-50/50 p-3">@csrf
                            <input type="hidden" name="request_id" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="decision" value="rejected">
                            <label for="receipt-reject-note-{{ $receipt->id }}" class="mb-1 block text-[11px] font-semibold text-rose-950">دلیل رد مدرک</label>
                            <textarea id="receipt-reject-note-{{ $receipt->id }}" name="review_note" required minlength="5" maxlength="1000" rows="3" class="w-full rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs focus:border-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100"></textarea>
                            @error('review_note')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                            <p class="mt-2 text-[10px] leading-5 text-rose-900">رد مدرک، هیچ پرداخت یا تخصیصی ایجاد نمی‌کند. دلیل برای پیگیری در پرونده باقی می‌ماند.</p>
                            <button type="submit" class="mt-3 h-10 w-full rounded-lg border border-rose-300 bg-white px-3 text-xs font-semibold text-rose-800 hover:bg-rose-100">رد مدرک</button>
                        </form>
                    </div>
                @endcan
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-xl bg-emerald-50 text-xl text-emerald-700" aria-hidden="true">✓</span>
                <h2 class="mt-3 text-sm font-bold text-slate-800">مدرک در انتظار بررسی نداریم</h2>
                <p class="mt-1 text-xs text-slate-500">مدارک جدید پس از ثبت در پروندهٔ مشتری در این فهرست ظاهر می‌شوند.</p>
            </div>
        @endforelse
    </section>

    <div class="mt-5">{{ $receipts->links() }}</div>
@endsection
