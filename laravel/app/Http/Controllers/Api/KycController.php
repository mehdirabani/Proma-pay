<?php

namespace App\Http\Controllers\Api;

use App\Actions\Kyc\ReviewKycCase;
use App\Actions\Kyc\SubmitKycCase;
use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\KycReviewRequest;
use App\Http\Requests\KycSubmissionRequest;
use App\Models\Customer;
use App\Models\KycCase;
use Illuminate\Http\JsonResponse;

class KycController extends Controller
{
    public function store(KycSubmissionRequest $request, Customer $customer, SubmitKycCase $submitKycCase): JsonResponse
    {
        $this->authorize('view', $customer);
        $case = $submitKycCase->handle($customer, $request->user(), $request->validated('identity_provider'), $request->attributes->get('request_id'), $request->ip());

        return response()->json(['data' => ['id' => $case->id, 'status' => $case->status->value]], 201);
    }

    public function review(KycReviewRequest $request, KycCase $kycCase, ReviewKycCase $reviewKycCase): JsonResponse
    {
        $this->authorize('view', $kycCase->customer);
        $status = KycStatus::from($request->validated('status'));

        $reviewKycCase->handle($kycCase, $status, $request->validated('review_note'), $request->user(), $request->attributes->get('request_id'), $request->ip());

        return response()->json(['data' => ['id' => $kycCase->id, 'status' => $status->value]]);
    }
}
