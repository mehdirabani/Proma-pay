<?php

namespace App\Http\Controllers;

use App\Actions\Customers\CreateCustomer;
use App\Http\Requests\CustomerStoreRequest;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerPageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);
        $query = Customer::query()->visibleTo($request->user())->latest('id');
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(fn ($builder) => $builder->where('full_name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%"));
        }

        return view('customers.index', ['customers' => $query->paginate(15)->withQueryString(), 'search' => $search]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create');
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);
        $customer->load([
            'creditAccount',
            'kycCases' => fn ($query) => $query->latest('id'),
            'payments' => fn ($query) => $query->latest('id')->limit(5),
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'creditRequestId' => (string) Str::uuid(),
            'paymentIdempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(CustomerStoreRequest $request, CreateCustomer $createCustomer): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $customer = $createCustomer->handle($request->validated(), $request->user(), $request->attributes->get('request_id'));

        return redirect()->route('customers.index')->with('success', 'مشتری با موفقیت ثبت شد.')->with('created_customer_id', $customer->id);
    }
}
