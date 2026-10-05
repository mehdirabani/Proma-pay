<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\RegisterPaymentIntent;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentIntentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermissionTo('payments.view'), 403);
        $payments = Payment::query()
            ->with(['customer:id,full_name', 'correction:id,payment_id,created_at'])
            ->whereHas('customer', fn ($query) => $query->visibleTo($request->user()))
            ->latest('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return PaymentResource::collection($payments);
    }

    public function store(PaymentIntentRequest $request, RegisterPaymentIntent $registerPaymentIntent): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('payments.record'), 403);
        $input = $request->validated();
        $customer = Customer::query()->findOrFail($input['customer_id']);
        $this->authorize('view', $customer);

        if (! empty($input['contract_id'])) {
            $contract = Contract::query()->findOrFail($input['contract_id']);
            abort_unless((int) $contract->customer_id === (int) $customer->id, 422, 'قرارداد به این مشتری تعلق ندارد.');
            if (! $request->user()->hasPermissionTo('customers.view_all')) {
                abort_unless((int) $contract->branch_id === (int) $request->user()->branch_id, 403);
            }
        }

        $payment = $registerPaymentIntent->handle($input, $request->user());

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }
}
