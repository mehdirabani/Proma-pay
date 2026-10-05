@extends('layouts.app')

@section('title', 'داشبورد')
@section('breadcrumb', 'داشبورد')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-semibold text-brand-700">مرکز عملیات</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-[28px]">نمای کلی سامانه</h1>
            <p class="mt-2 text-sm text-slate-500">وضعیت امروز را از داده‌های ثبت‌شده دنبال کنید.</p>
        </div>
        @can('create', App\Models\Customer::class)
            <a href="{{ route('customers.create') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 focus-visible:outline-brand-600"><span aria-hidden="true">＋</span> ثبت مشتری</a>
        @endcan
    </div>

    <section aria-label="شاخص‌های سامانه" class="mt-7 grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        @foreach ([['label' => 'مشتریان قابل‌دسترسی', 'value' => $metrics['customers'], 'caption' => 'بر اساس سطح دسترسی شما', 'icon' => '◉', 'tone' => 'bg-violet-50 text-violet-700'], ['label' => 'قراردادها', 'value' => $metrics['contracts'], 'caption' => 'ثبت‌شده در شعبه‌های مجاز', 'icon' => '▤', 'tone' => 'bg-sky-50 text-sky-700'], ['label' => 'اقساط باز', 'value' => $metrics['open_installments'], 'caption' => 'نیازمند پیگیری', 'icon' => '◷', 'tone' => 'bg-amber-50 text-amber-700'], ['label' => 'درخواست پرداخت', 'value' => $metrics['pending_payments'], 'caption' => 'در انتظار تعیین وضعیت', 'icon' => '↗', 'tone' => 'bg-emerald-50 text-emerald-700']] as $metric)
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,.03)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ \App\Support\PersianNumber::format($metric['value']) }}</p>
                    </div>
                    <span aria-hidden="true" class="grid size-10 place-items-center rounded-lg {{ $metric['tone'] }}">{{ $metric['icon'] }}</span>
                </div>
                <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">{{ $metric['caption'] }}</p>
            </article>
        @endforeach
    </section>

    <div class="mt-6 grid grid-cols-1 gap-5 2xl:grid-cols-[minmax(0,1.3fr)_minmax(20rem,.7fr)]">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div><h2 class="text-sm font-bold text-slate-900">مشتریان اخیر</h2><p class="mt-1 text-xs text-slate-500">آخرین ثبت‌های قابل‌دسترسی</p></div>
                @can('viewAny', App\Models\Customer::class)<a href="{{ route('customers.index') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">همهٔ مشتریان <span aria-hidden="true">←</span></a>@endcan
            </div>
            @if ($recentCustomers->isEmpty())
                <div class="px-5 py-12 text-center">
                    <span class="mx-auto grid size-11 place-items-center rounded-full bg-slate-100 text-lg text-slate-500" aria-hidden="true">◉</span>
                    <p class="mt-3 text-sm font-semibold text-slate-700">هنوز مشتری‌ای ثبت نشده است</p>
                    <p class="mt-1 text-xs text-slate-500">پس از ثبت اولین مشتری، فهرست در این بخش نمایش داده می‌شود.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-right text-sm">
                        <thead class="bg-slate-50/80 text-[11px] font-semibold text-slate-500"><tr><th scope="col" class="px-5 py-3">مشتری</th><th scope="col" class="px-4 py-3">شمارهٔ همراه</th><th scope="col" class="px-4 py-3">وضعیت</th><th scope="col" class="px-5 py-3">تاریخ ثبت</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentCustomers as $customer)
                                <tr class="hover:bg-slate-50/70"><td class="px-5 py-3.5 font-semibold text-slate-800"><a href="{{ route('customers.show', $customer) }}" class="hover:text-brand-700">{{ $customer->full_name }}</a></td><td dir="ltr" class="px-4 py-3.5 text-left text-slate-600">{{ \App\Support\PersianDigits::toPersian($customer->mobile) }}</td><td class="px-4 py-3.5"><span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700">فعال</span></td><td class="px-5 py-3.5 text-xs text-slate-500"><time data-persian-date="{{ $customer->created_at->toIso8601String() }}">{{ $customer->created_at->format('Y-m-d') }}</time></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">آخرین درخواست‌های پرداخت</h2><p class="mt-1 text-xs text-slate-500">پرداخت‌های در حال بررسی</p></div>
            @if ($recentPayments->isEmpty())
                <div class="px-5 py-12 text-center"><span class="mx-auto grid size-11 place-items-center rounded-full bg-slate-100 text-lg text-slate-500" aria-hidden="true">↗</span><p class="mt-3 text-sm font-semibold text-slate-700">درخواستی برای پرداخت وجود ندارد</p><p class="mt-1 text-xs text-slate-500">این بخش فقط درخواست‌های واقعی را نشان می‌دهد.</p></div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentPayments as $payment)
                        <li class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ $payment->customer->full_name }}</p><p class="mt-1 text-xs text-slate-500">{{ \App\Support\PaymentLabels::method($payment->method) }} <span class="mx-1 text-slate-300">·</span><time data-persian-date="{{ $payment->requested_at->toIso8601String() }}">{{ $payment->requested_at->format('Y-m-d') }}</time></p></div>
                            <div class="shrink-0 text-left"><p class="text-sm font-bold text-slate-800">{{ \App\Support\PersianNumber::toman($payment->amount_toman) }}</p><p class="mt-1 text-[11px] text-amber-700">در انتظار</p></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-brand-100 bg-brand-50/70 p-4 sm:flex sm:items-center sm:justify-between sm:gap-4 sm:px-5">
        <div><p class="text-sm font-semibold text-slate-800">حسابداری امن با ردپای روشن</p><p class="mt-1 text-xs leading-5 text-slate-600">تغییر سقف اعتبار به‌صورت تراکنشی انجام و در دفتر غیرقابل‌ویرایش ثبت می‌شود.</p></div>
        <span class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-brand-700 sm:mt-0"><span class="size-2 rounded-full bg-emerald-500"></span> ثبت رویدادها فعال است</span>
    </section>
@endsection
