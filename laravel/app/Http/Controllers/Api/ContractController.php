<?php

namespace App\Http\Controllers\Api;

use App\Actions\Contracts\CreateContractDraft;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContractDraftRequest;
use App\Http\Resources\ContractResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class ContractController extends Controller
{
    public function store(ContractDraftRequest $request, CreateContractDraft $createContractDraft): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('contracts.create'), 403);
        $input = $request->validated();
        $customer = Customer::query()->visibleTo($request->user())->findOrFail($input['customer_id']);
        $this->authorize('view', $customer);

        $contract = $createContractDraft->handle($customer, $input, $request->user());

        return (new ContractResource($contract))
            ->response()
            ->setStatusCode($contract->wasRecentlyCreated ? 201 : 200);
    }
}
