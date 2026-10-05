<?php

namespace App\Http\Controllers\Api;

use App\Actions\Contracts\CreateContractDraft;
use App\Actions\Contracts\TransitionContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContractDraftRequest;
use App\Http\Requests\ContractTransitionRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
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

    public function submit(ContractTransitionRequest $request, Contract $contract, TransitionContract $transitionContract): JsonResponse
    {
        return $this->transition($request, $contract, $transitionContract, 'submit');
    }

    public function approve(ContractTransitionRequest $request, Contract $contract, TransitionContract $transitionContract): JsonResponse
    {
        return $this->transition($request, $contract, $transitionContract, 'approve');
    }

    public function activate(ContractTransitionRequest $request, Contract $contract, TransitionContract $transitionContract): JsonResponse
    {
        return $this->transition($request, $contract, $transitionContract, 'activate');
    }

    private function transition(ContractTransitionRequest $request, Contract $contract, TransitionContract $transitionContract, string $transition): JsonResponse
    {
        $this->authorize('view', $contract->customer);
        $input = $request->validated();
        $updated = $transitionContract->handle($contract->id, $transition, $input['request_id'], $input['reason'], $request->user());

        return (new ContractResource($updated))->response();
    }
}
