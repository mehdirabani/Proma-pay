<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $actor = $request->user();
        $visibleContracts = Contract::query()->whereHas('customer', fn ($query) => $query->visibleTo($actor));

        $metrics = [
            'customers' => Customer::query()->visibleTo($actor)->count(),
            'contracts' => (clone $visibleContracts)->count(),
            'open_installments' => Installment::query()
                ->whereHas('contract.customer', fn ($query) => $query->visibleTo($actor))
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->count(),
            'pending_payments' => Payment::query()
                ->where('status', 'pending')
                ->whereHas('customer', fn ($query) => $query->visibleTo($actor))
                ->count(),
        ];

        return view('dashboard', [
            'metrics' => $metrics,
            'recentCustomers' => Customer::query()->visibleTo($actor)->latest('id')->limit(6)->get(['id', 'full_name', 'mobile', 'status', 'created_at']),
            'recentPayments' => Payment::query()->with('customer:id,full_name')
                ->whereHas('customer', fn ($query) => $query->visibleTo($actor))
                ->latest('id')->limit(5)->get(),
        ]);
    }
}
