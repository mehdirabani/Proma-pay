<?php

namespace App\Http\Controllers\Api;

use App\Actions\Customers\CreateCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerStoreRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);
        $query = Customer::query()->visibleTo($request->user())->latest('id');
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(fn ($builder) => $builder->where('full_name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%"));
        }

        return CustomerResource::collection($query->paginate(min(max((int) $request->query('per_page', 20), 1), 100)));
    }

    public function store(CustomerStoreRequest $request, CreateCustomer $createCustomer): JsonResponse
    {
        $this->authorize('create', Customer::class);
        $customer = $createCustomer->handle($request->validated(), $request->user(), $request->attributes->get('request_id'));

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function show(Request $request, Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }
}
