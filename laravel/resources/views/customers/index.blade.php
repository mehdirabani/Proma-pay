@extends('layouts.app')

@section('title', 'مشتریان')
@section('breadcrumb', 'مشتریان')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-xs font-semibold text-brand-700">مدیریت ارتباط</p><h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">مشتریان</h1><p class="mt-2 text-sm text-slate-500">اطلاعات پایه و وضعیت ثبت مشتریان مجاز.</p></div>
        @can('create', App\Models\Customer::class)<a href="{{ route('customers.create') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700">＋ مشتری جدید</a>@endcan
    </div>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div><h2 class="text-sm font-bold text-slate-900">فهرست مشتریان</h2><p class="mt-1 text-xs text-slate-500">{{ \App\Support\PersianNumber::format($customers->total()) }} مورد در دسترس</p></div>
            <form action="{{ route('customers.index') }}" method="GET" class="flex w-full gap-2 sm:max-w-md">
                <label for="customer-search" class="sr-only">جستجوی نام یا شمارهٔ همراه</label>
                <input id="customer-search" name="search" value="{{ $search }}" type="search" class="h-10 min-w-0 flex-1 rounded-lg border border-slate-200 px-3 text-sm outline-none placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100" placeholder="نام یا شمارهٔ همراه">
                <button class="h-10 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">جستجو</button>
            </form>
        </div>
        @if ($customers->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-xl text-slate-500" aria-hidden="true">◉</span>
                <h3 class="mt-4 text-sm font-bold text-slate-800">{{ $search === '' ? 'هنوز مشتری‌ای ثبت نشده است' : 'موردی با این عبارت پیدا نشد' }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $search === '' ? 'با ثبت اطلاعات مشتری، فهرست شما در اینجا نمایش داده می‌شود.' : 'عبارت جستجو را تغییر دهید و دوباره تلاش کنید.' }}</p>
                @if ($search !== '')<a href="{{ route('customers.index') }}" class="mt-4 inline-flex text-xs font-semibold text-brand-700">پاک کردن جستجو</a>@endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-right text-sm">
                    <thead class="bg-slate-50 text-[11px] font-semibold text-slate-500"><tr><th scope="col" class="px-5 py-3">نام مشتری</th><th scope="col" class="px-4 py-3">شمارهٔ همراه</th><th scope="col" class="px-4 py-3">وضعیت</th><th scope="col" class="px-4 py-3">تاریخ ثبت</th><th scope="col" class="px-5 py-3"><span class="sr-only">عملیات</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($customers as $customer)
                            <tr class="transition hover:bg-slate-50/70"><td class="px-5 py-4 font-semibold text-slate-800"><a href="{{ route('customers.show', $customer) }}" class="rounded-sm hover:text-brand-700 focus-visible:outline-brand-600">{{ $customer->full_name }}</a></td><td dir="ltr" class="px-4 py-4 text-left text-slate-600">{{ \App\Support\PersianDigits::toPersian($customer->mobile) }}</td><td class="px-4 py-4"><span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700">فعال</span></td><td class="px-4 py-4 text-xs text-slate-500"><time data-persian-date="{{ $customer->created_at->toIso8601String() }}">{{ $customer->created_at->format('Y-m-d') }}</time></td><td class="px-5 py-4 text-left"><a href="{{ route('customers.show', $customer) }}" class="text-xs font-semibold text-brand-700">پرونده <span aria-hidden="true">←</span></a></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3 sm:px-5">{{ $customers->onEachSide(1)->links() }}</div>
        @endif
    </section>
@endsection
