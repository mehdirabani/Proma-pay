@extends('layouts.app')

@section('title', $customer->full_name)
@section('breadcrumb', 'مشتریان / پرونده')

@section('content')
    @php
        $latestKycCase = $customer->kycCases->first();
        $contractKycApproved = $latestKycCase?->status === \App\Enums\KycStatus::Approved;
        $creditAccount = $customer->creditAccount;
        $committedCredit = $creditAccount ? bcadd((string) $creditAccount->used_toman, (string) $creditAccount->reserved_toman, 0) : '0';
        $availableCredit = $creditAccount ? bcsub((string) $creditAccount->limit_toman, $committedCredit, 0) : '0';
    @endphp

    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('customers.index') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">← بازگشت به مشتریان</a>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $customer->full_name }}</h1>
                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">فعال</span>
            </div>
            <p class="mt-2 text-sm text-slate-500" dir="ltr">{{ \App\Support\PersianDigits::toPersian($customer->mobile) }}</p>
        </div>
        <p class="text-xs text-slate-500">ثبت‌شده در <time data-persian-date="{{ $customer->created_at->toIso8601String() }}">{{ $customer->created_at->format('Y-m-d') }}</time></p>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(21rem,.85fr)]">
        <div class="space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-bold text-slate-900">اطلاعات هویتی</h2><p class="mt-1 text-xs text-slate-500">داده‌های حساس به شکل رمزنگاری‌شده نگهداری می‌شوند.</p></div><span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] text-slate-600">شناسه {{ \App\Support\PersianNumber::format($customer->id) }}</span></div>
                <dl class="grid grid-cols-1 gap-y-4 px-5 py-5 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">نام و نام خانوادگی</dt><dd class="mt-1.5 text-sm font-semibold text-slate-800">{{ $customer->full_name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">شمارهٔ همراه</dt><dd class="mt-1.5 text-sm font-semibold text-slate-800" dir="ltr">{{ \App\Support\PersianDigits::toPersian($customer->mobile) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">ایمیل</dt><dd class="mt-1.5 text-sm text-slate-700" dir="ltr">{{ $customer->email ?: 'ثبت نشده' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">تاریخ تولد</dt><dd class="mt-1.5 text-sm text-slate-700">@if($customer->birth_date)<time data-persian-date="{{ $customer->birth_date->toIso8601String() }}">{{ $customer->birth_date->format('Y-m-d') }}</time>@else ثبت نشده @endif</dd></div>
                </dl>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">قراردادها</h2><p class="mt-1 text-xs text-slate-500">پیش‌نویس، تأیید و فعال‌سازی هرکدام مرحله و سابقهٔ جدا دارند.</p></div>
                @if($contracts->isEmpty())
                    <div class="px-5 py-7 text-center text-xs text-slate-500">هنوز قراردادی برای این مشتری ثبت نشده است.</div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($contracts as $contract)
                            @php($contractStatusLabel = match($contract->status) {'draft' => 'پیش‌نویس', 'pending_approval' => 'در انتظار تأیید', 'approved' => 'تأییدشده', 'active' => 'فعال', 'completed' => 'تسویه‌شده', 'overdue' => 'معوق', 'legal' => 'حقوقی', 'cancelled' => 'لغوشده', default => 'نامشخص'})
                            <li class="space-y-3 px-5 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div><p class="text-sm font-bold text-slate-800" dir="ltr">{{ $contract->contract_number }}</p><p class="mt-1 text-xs text-slate-500">{{ \App\Support\PersianNumber::format($contract->installment_count) }} قسط · {{ \App\Support\PersianNumber::toman($contract->total_amount_toman) }}</p></div>
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800">{{ $contractStatusLabel }}</span>
                                </div>
                                @if($contract->status === 'draft')
                                    @can('contracts.submit')
                                        <form method="POST" action="{{ route('contracts.submit', $contract) }}" class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">@csrf<input type="hidden" name="request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                                            <label class="sr-only" for="contract-submit-reason-{{ $contract->id }}">دلیل ارسال قرارداد {{ $contract->contract_number }}</label><input id="contract-submit-reason-{{ $contract->id }}" name="reason" required minlength="5" maxlength="500" placeholder="شرح کوتاه برای ارسال به بررسی" class="h-10 rounded-lg border border-slate-200 px-3 text-xs focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                                            <button type="submit" class="h-10 rounded-lg border border-brand-200 bg-brand-50 px-3 text-xs font-semibold text-brand-800 hover:bg-brand-100">ارسال برای تأیید و رزرو اعتبار</button>
                                        </form>
                                    @endcan
                                @elseif($contract->status === 'pending_approval')
                                    @can('contracts.approve')
                                        <form method="POST" action="{{ route('contracts.approve', $contract) }}" class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">@csrf @method('PUT')<input type="hidden" name="request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                                            <label class="sr-only" for="contract-approve-reason-{{ $contract->id }}">دلیل تأیید قرارداد {{ $contract->contract_number }}</label><input id="contract-approve-reason-{{ $contract->id }}" name="reason" required minlength="5" maxlength="500" placeholder="دلیل بررسی و تأیید" class="h-10 rounded-lg border border-slate-200 px-3 text-xs focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                                            <button type="submit" class="h-10 rounded-lg bg-emerald-700 px-3 text-xs font-semibold text-white hover:bg-emerald-800">تأیید قرارداد</button>
                                        </form>
                                    @endcan
                                @elseif($contract->status === 'approved')
                                    @can('contracts.activate')
                                        <form method="POST" action="{{ route('contracts.activate', $contract) }}" class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">@csrf<input type="hidden" name="request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                                            <label class="sr-only" for="contract-activate-reason-{{ $contract->id }}">دلیل فعال‌سازی قرارداد {{ $contract->contract_number }}</label><input id="contract-activate-reason-{{ $contract->id }}" name="reason" required minlength="5" maxlength="500" placeholder="شرح کوتاه برای فعال‌سازی" class="h-10 rounded-lg border border-slate-200 px-3 text-xs focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                                            <button type="submit" class="h-10 rounded-lg bg-brand-600 px-3 text-xs font-semibold text-white hover:bg-brand-700">فعال‌سازی و انتشار اقساط</button>
                                        </form>
                                    @endcan
                                @endif
                                @if($contract->status === 'active')
                                    @can('payments.record')
                                        <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                                            <div class="flex items-start gap-3">
                                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-sky-700" aria-hidden="true">↙</span>
                                                <div><h3 class="text-xs font-bold text-slate-800">محاسبه و ثبت دریافت قسط</h3><p class="mt-1 text-[11px] leading-5 text-slate-600">مبلغ ابتدا پیش‌فاکتور می‌شود؛ ثبت نهایی فقط پس از بازبینی تخصیص‌ها انجام می‌گیرد.</p></div>
                                            </div>
                                            @if($contract->installments->isNotEmpty())
                                                <details class="mt-3 rounded-lg border border-sky-100 bg-white px-3 py-2">
                                                    <summary class="cursor-pointer text-[11px] font-semibold text-slate-700">مشاهدهٔ برنامهٔ اقساط</summary>
                                                    <div class="mt-2 max-h-56 space-y-2 overflow-y-auto border-t border-slate-100 pt-2">
                                                        @foreach($contract->installments as $installment)
                                                            @php($installmentLabel = match($installment->status) {'draft' => 'پیش‌نویس', 'pending' => 'در انتظار پرداخت', 'overdue' => 'سررسید گذشته', 'partial' => 'پرداخت ناقص', 'paid' => 'پرداخت‌شده', 'cancelled' => 'لغوشده', default => 'نامشخص'})
                                                            <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-[11px] hover:bg-slate-50">
                                                                <input form="settlement-quote-{{ $contract->id }}" type="checkbox" name="installment_ids[]" value="{{ $installment->id }}" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                                                <span class="min-w-0 flex-1">قسط {{ \App\Support\PersianNumber::format($installment->installment_number) }} · <time data-persian-date="{{ $installment->due_date->toIso8601String() }}">{{ $installment->due_date->format('Y-m-d') }}</time></span>
                                                                <span class="text-slate-500">{{ $installmentLabel }}</span>
                                                                <span class="font-semibold text-slate-700">{{ \App\Support\PersianNumber::toman($installment->amount_toman) }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @endif
                                            <form id="settlement-quote-{{ $contract->id }}" method="POST" action="{{ route('customers.contracts.settlement-quotes.store', [$customer, $contract]) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">@csrf
                                                <input type="hidden" name="request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                                                <div><label for="settlement-scope-{{ $contract->id }}" class="mb-1 block text-[11px] font-semibold text-slate-700">دامنهٔ تخصیص</label><select id="settlement-scope-{{ $contract->id }}" name="scope" required class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs"><option value="selected">فقط اقساط انتخاب‌شده</option><option value="schedule">کل اقساط قابل پرداخت قرارداد</option><option value="forward">از قدیمی‌ترین قسط انتخاب‌شده به بعد</option></select></div>
                                                <div><label for="settlement-amount-{{ $contract->id }}" class="mb-1 block text-[11px] font-semibold text-slate-700">مبلغ دریافتی (تومان)</label><input id="settlement-amount-{{ $contract->id }}" name="amount_toman" required inputmode="numeric" dir="ltr" placeholder="مثلاً ۵۰۰٬۰۰۰" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100"></div>
                                                <p class="text-[10px] leading-5 text-slate-500 sm:col-span-2">برای دامنهٔ «فقط انتخاب‌شده» یا «از قسط انتخاب‌شده به بعد»، از فهرست بالا قسط انتخاب کنید. دامنهٔ کل برنامه همهٔ اقساط باز را در نظر می‌گیرد. به‌دلیل نبود مدل پرونده و ارجاع حقوقی، جریمه و هزینهٔ حقوقی در این پیش‌فاکتور محاسبه نمی‌شود.</p>
                                                @error('amount_toman')<p role="alert" class="text-xs text-rose-700 sm:col-span-2">{{ $message }}</p>@enderror
                                                @error('installment_ids')<p role="alert" class="text-xs text-rose-700 sm:col-span-2">{{ $message }}</p>@enderror
                                                @error('financial_rules')<p role="alert" class="text-xs text-rose-700 sm:col-span-2">{{ $message }}</p>@enderror
                                                @error('request_id')<p role="alert" class="text-xs text-rose-700 sm:col-span-2">{{ $message }}</p>@enderror
                                                <div class="sm:col-span-2"><button type="submit" class="h-10 w-full rounded-lg bg-brand-600 px-4 text-xs font-semibold text-white hover:bg-brand-700">محاسبهٔ پیش‌فاکتور و بازبینی</button></div>
                                            </form>
                                        </div>
                                    @endcan
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @can('contracts.create')
                    @if($contractKycApproved)
                        <form method="POST" action="{{ route('customers.contracts.store', $customer) }}" class="border-t border-slate-100 bg-slate-50/60 p-5">@csrf
                            <input type="hidden" name="customer_id" value="{{ $customer->id }}"><input type="hidden" name="request_uuid" value="{{ old('request_uuid', $contractRequestUuid) }}">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div><label for="principal_toman" class="mb-1.5 block text-xs font-medium text-slate-700">اصل قرارداد (تومان)</label><input id="principal_toman" name="principal_toman" required inputmode="numeric" dir="ltr" value="{{ old('principal_toman') }}" placeholder="۱٬۲۰۰٬۰۰۰" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">@error('principal_toman')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                                <div><label for="down_payment_toman" class="mb-1.5 block text-xs font-medium text-slate-700">پیش‌پرداخت (تومان)</label><input id="down_payment_toman" name="down_payment_toman" required inputmode="numeric" dir="ltr" value="{{ old('down_payment_toman', '۰') }}" placeholder="۰" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">@error('down_payment_toman')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                                <div><label for="monthly_interest_rate" class="mb-1.5 block text-xs font-medium text-slate-700">نرخ سود ماهانه (درصد)</label><input id="monthly_interest_rate" name="monthly_interest_rate" required inputmode="decimal" dir="ltr" value="{{ old('monthly_interest_rate', '۰') }}" placeholder="۵" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">@error('monthly_interest_rate')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                                <div><label for="interest_type" class="mb-1.5 block text-xs font-medium text-slate-700">روش محاسبهٔ سود</label><select id="interest_type" name="interest_type" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"><option value="simple" @selected(old('interest_type', 'simple') === 'simple')>ساده</option><option value="compound" @selected(old('interest_type') === 'compound')>مرکب</option></select></div>
                                <div><label for="installment_count" class="mb-1.5 block text-xs font-medium text-slate-700">تعداد اقساط</label><input id="installment_count" name="installment_count" required inputmode="numeric" dir="ltr" value="{{ old('installment_count', '۶') }}" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">@error('installment_count')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                                <div><label for="start_date" class="mb-1.5 block text-xs font-medium text-slate-700">تاریخ قرارداد</label><input id="start_date" name="start_date" type="date" required dir="ltr" value="{{ old('start_date', $contractStartDate) }}" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">@error('start_date')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                                <div><label for="first_due_date" class="mb-1.5 block text-xs font-medium text-slate-700">اولین سررسید</label><input id="first_due_date" name="first_due_date" type="date" required dir="ltr" value="{{ old('first_due_date', $contractFirstDueDate) }}" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">@error('first_due_date')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                            </div>
                            @error('request_uuid')<p role="alert" class="mt-3 text-xs text-rose-700">{{ $message }}</p>@enderror
                            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">این عملیات فقط پیش‌نویس و اقساط پیشنهادی را ثبت می‌کند؛ اعتبار رزرو یا پرداختی ثبت نمی‌شود. سقف فنی این فرم ۶۰۰ قسط است.</p>
                            <div class="mt-4 flex justify-end"><button type="submit" class="h-10 rounded-lg bg-brand-600 px-4 text-xs font-semibold text-white hover:bg-brand-700">ذخیرهٔ پیش‌نویس قرارداد</button></div>
                        </form>
                    @else
                        <div class="border-t border-slate-100 bg-amber-50/60 px-5 py-4 text-xs leading-5 text-amber-900">ساخت پیش‌نویس پس از تأیید آخرین پروندهٔ احراز هویت در دسترس است.</div>
                    @endif
                @endcan
            </section>

            <section class="rounded-xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-bold text-slate-900">احراز هویت</h2><p class="mt-1 text-xs text-slate-500">وضعیت پرونده و سابقهٔ بررسی</p></div>
                    @can('update', $customer)
                        <form method="POST" action="{{ route('customers.kyc.store', $customer) }}">@csrf<button type="submit" class="h-9 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">＋ ارسال برای بررسی</button></form>
                    @endcan
                </div>
                @if($customer->kycCases->isEmpty())
                    <div class="px-5 py-9 text-center"><p class="text-sm font-semibold text-slate-700">پروندهٔ احراز هویت ثبت نشده است</p><p class="mt-1 text-xs text-slate-500">با ارسال درخواست، یک پرونده در وضعیت «در انتظار» ساخته می‌شود.</p></div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($customer->kycCases as $kycCase)
                            @php($kycLabel = match($kycCase->status->value) {'pending' => 'در انتظار بررسی', 'under_review' => 'در حال بررسی', 'approved' => 'تأیید شده', 'rejected' => 'رد شده', 'suspended' => 'معلق', default => 'نامشخص'})
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2"><span class="size-2 rounded-full {{ $kycCase->status === \App\Enums\KycStatus::Approved ? 'bg-emerald-500' : ($kycCase->status === \App\Enums\KycStatus::Rejected ? 'bg-rose-500' : 'bg-amber-400') }}" aria-hidden="true"></span><span class="text-sm font-semibold text-slate-800">{{ $kycLabel }}</span></div><time class="text-xs text-slate-500" data-persian-date="{{ $kycCase->submitted_at->toIso8601String() }}">{{ $kycCase->submitted_at->format('Y-m-d') }}</time></div>
                                @if($kycCase->review_note)<p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">{{ $kycCase->review_note }}</p>@endif
                                @if($kycCase->status === \App\Enums\KycStatus::Pending || $kycCase->status === \App\Enums\KycStatus::UnderReview)
                                    @can('kyc.review')
                                        <form method="POST" action="{{ route('kyc-cases.review', $kycCase) }}" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-[9rem_minmax(0,1fr)_auto]">@csrf @method('PUT')
                                            <label class="sr-only" for="kyc-status-{{ $kycCase->id }}">نتیجهٔ بررسی</label><select id="kyc-status-{{ $kycCase->id }}" name="status" class="h-10 rounded-lg border border-slate-200 bg-white px-2 text-xs"><option value="approved">تأیید</option><option value="rejected">رد درخواست</option></select>
                                            <label class="sr-only" for="kyc-note-{{ $kycCase->id }}">دلیل یا یادداشت بررسی</label><input id="kyc-note-{{ $kycCase->id }}" name="review_note" maxlength="2000" placeholder="دلیل رد یا یادداشت بررسی" class="h-10 rounded-lg border border-slate-200 px-3 text-xs">
                                            <button type="submit" class="h-10 rounded-lg bg-slate-800 px-3 text-xs font-semibold text-white hover:bg-slate-700">ثبت نتیجه</button>
                                        </form>
                                    @endcan
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">درخواست‌های پرداخت</h2><p class="mt-1 text-xs text-slate-500">ثبت درخواست به‌تنهایی پرداخت را نهایی یا به قسط تخصیص نمی‌دهد.</p></div>
                @if($customer->payments->isEmpty())
                    <div class="px-5 py-7 text-center text-xs text-slate-500">هنوز درخواستی ثبت نشده است.</div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($customer->payments as $payment)
                            @php($paymentMethodLabel = match($payment->method->value) {'cash' => 'نقدی', 'card' => 'کارت', 'pos' => 'کارت‌خوان', 'bank_transfer' => 'انتقال بانکی', 'online_gateway' => 'درگاه آنلاین', 'wallet' => 'کیف پول', default => 'ثبت دستی'})
                            @php($paymentStatusLabel = match($payment->status->value) {'paid' => 'پرداخت‌شده', 'failed' => 'ناموفق', 'reversed' => 'برگشت‌خورده', default => 'در انتظار'})
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5"><span class="text-xs font-medium text-slate-700">{{ $paymentMethodLabel }} · {{ $paymentStatusLabel }}</span><span class="text-sm font-bold text-slate-800">{{ \App\Support\PersianNumber::toman($payment->amount_toman) }}</span><time class="w-full text-[11px] text-slate-500" data-persian-date="{{ $payment->requested_at->toIso8601String() }}">{{ $payment->requested_at->format('Y-m-d') }}</time></li>
                        @endforeach
                    </ul>
                @endif
                @can('payments.record')
                    <form method="POST" action="{{ route('customers.payments.store', $customer) }}" class="border-t border-slate-100 bg-slate-50/60 p-5">@csrf
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}"><input type="hidden" name="idempotency_key" value="{{ $paymentIdempotencyKey }}">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_11rem]">
                            <div><label for="amount_toman" class="mb-1.5 block text-xs font-medium text-slate-700">مبلغ (تومان)</label><input id="amount_toman" name="amount_toman" required inputmode="numeric" dir="ltr" placeholder="۱٬۲۵۰٬۰۰۰" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">@error('amount_toman')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror</div>
                            <div><label for="method" class="mb-1.5 block text-xs font-medium text-slate-700">روش پرداخت</label><select id="method" name="method" class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"><option value="manual">ثبت دستی</option><option value="cash">نقدی</option><option value="card">کارت</option><option value="pos">کارت‌خوان</option><option value="bank_transfer">انتقال بانکی</option></select></div>
                        </div>
                        <p class="mt-3 text-[11px] leading-5 text-slate-500">درخواست تا زمان تأیید یا تطبیق درگاه در وضعیت انتظار می‌ماند.</p>
                        <div class="mt-4 flex justify-end"><button type="submit" class="h-10 rounded-lg bg-brand-600 px-4 text-xs font-semibold text-white hover:bg-brand-700">ثبت درخواست پرداخت</button></div>
                    </form>
                @endcan
            </section>
        </div>

        <div class="space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">حساب اعتبار</h2><p class="mt-1 text-xs text-slate-500">مقادیر به تومان و بدون اعشار نگهداری می‌شوند.</p></div>
                <dl class="grid grid-cols-2 gap-3 p-5">
                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-[11px] text-slate-500">سقف اعتبار</dt><dd class="mt-2 text-sm font-bold text-slate-800">{{ \App\Support\PersianNumber::toman($creditAccount?->limit_toman ?? 0) }}</dd></div>
                    <div class="rounded-lg bg-emerald-50/70 p-3"><dt class="text-[11px] text-slate-500">اعتبار در دسترس</dt><dd class="mt-2 text-sm font-bold text-emerald-800">{{ \App\Support\PersianNumber::toman($availableCredit) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-[11px] text-slate-500">مصرف‌شده</dt><dd class="mt-2 text-sm font-semibold text-slate-800">{{ \App\Support\PersianNumber::toman($creditAccount?->used_toman ?? 0) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-[11px] text-slate-500">رزروشده</dt><dd class="mt-2 text-sm font-semibold text-slate-800">{{ \App\Support\PersianNumber::toman($creditAccount?->reserved_toman ?? 0) }}</dd></div>
                </dl>
                @can('credit.adjust')
                    <form method="POST" action="{{ route('customers.credit.adjust', $customer) }}" class="border-t border-slate-100 p-5">@csrf
                        <input type="hidden" name="request_id" value="{{ $creditRequestId }}">
                        <label for="new_limit_toman" class="mb-1.5 block text-xs font-medium text-slate-700">تنظیم سقف جدید (تومان)</label>
                        <input id="new_limit_toman" name="new_limit_toman" required inputmode="numeric" value="{{ old('new_limit_toman', $creditAccount?->limit_toman ?? 0) }}" dir="ltr" class="h-11 w-full rounded-lg border border-slate-300 px-3 text-left text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        @error('new_limit_toman')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        <label for="reason" class="mb-1.5 mt-4 block text-xs font-medium text-slate-700">دلیل تغییر</label>
                        <textarea id="reason" name="reason" required minlength="5" maxlength="500" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">{{ old('reason') }}</textarea>
                        @error('reason')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        @error('request_id')<p role="alert" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        <p class="mt-2 text-[11px] leading-5 text-slate-500">کاهش سقف کمتر از اعتبار مصرف‌شده و رزروشده پذیرفته نمی‌شود.</p>
                        <button type="submit" class="mt-4 h-10 w-full rounded-lg border border-brand-200 bg-brand-50 text-xs font-semibold text-brand-800 hover:bg-brand-100">ثبت تغییر اعتبار</button>
                    </form>
                @endcan
            </section>
            <section class="rounded-xl border border-amber-200 bg-amber-50/70 p-4"><p class="text-xs font-semibold text-amber-900">ردپای تغییرات</p><p class="mt-1 text-xs leading-5 text-amber-800">هر تغییر سقف اعتبار همراه با عامل، دلیل، مقدار قبل و بعد و شناسهٔ درخواست ثبت می‌شود.</p></section>
        </div>
    </div>
@endsection
