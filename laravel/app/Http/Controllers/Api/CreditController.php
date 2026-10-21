<?php

namespace App\Http\Controllers\Api;

use App\Actions\Credit\AdjustCreditLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreditLimitAdjustmentRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        abort_unless($request->user()->hasPermissionTo('credit.view'), 403);
        $account = $customer->creditAccount()->firstOrFail();
        $committedToman = bcadd((string) $account->used_toman, (string) $account->reserved_toman, 0);

        return response()->json(['data' => [
            'customer_id' => $customer->id,
            'currency_code' => $account->currency_code,
            'limit_toman' => (string) $account->limit_toman,
            'used_toman' => (string) $account->used_toman,
            'reserved_toman' => (string) $account->reserved_toman,
            'available_toman' => bcsub((string) $account->limit_toman, $committedToman, 0),
            'status' => $account->status,
        ]]);
    }

    public function adjust(CreditLimitAdjustmentRequest $request, Customer $customer, AdjustCreditLimit $adjustCreditLimit): JsonResponse
    {
        $this->authorize('view', $customer);
        abort_unless($request->user()->hasPermissionTo('credit.adjust'), 403);
        $entry = $adjustCreditLimit->handle(
            $customer->id,
            $request->validated('new_limit_toman'),
            $request->validated('reason'),
            $request->validated('request_id'),
            $request->user(),
        );

        return response()->json(['data' => [
            'entry_id' => $entry->id,
            'request_id' => $entry->request_id,
            'old_limit_toman' => (string) $entry->old_limit_toman,
            'new_limit_toman' => (string) $entry->new_limit_toman,
            'currency_code' => config('proma.currency_code'),
        ]]);
    }
}
