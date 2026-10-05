<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\CreateContractDraft;
use App\Actions\Contracts\TransitionContract;
use App\Actions\Credit\AdjustCreditLimit;
use App\Actions\Kyc\ReviewKycCase;
use App\Actions\Kyc\SubmitKycCase;
use App\Actions\Payments\RegisterPaymentIntent;
use App\Enums\KycStatus;
use App\Http\Requests\ContractDraftRequest;
use App\Http\Requests\ContractTransitionRequest;
use App\Http\Requests\CreditLimitAdjustmentRequest;
use App\Http\Requests\KycReviewRequest;
use App\Http\Requests\KycSubmissionRequest;
use App\Http\Requests\PaymentIntentRequest;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\KycCase;
use Illuminate\Http\RedirectResponse;

class CustomerActionsController extends Controller
{
    public function submitContractForApproval(ContractTransitionRequest $request, Contract $contract, TransitionContract $transitionContract): RedirectResponse
    {
        return $this->transitionContract($request, $contract, $transitionContract, 'submit', 'قرارداد برای بررسی ارسال شد.');
    }

    public function approveContract(ContractTransitionRequest $request, Contract $contract, TransitionContract $transitionContract): RedirectResponse
    {
        return $this->transitionContract($request, $contract, $transitionContract, 'approve', 'قرارداد تأیید شد و اعتبار آن از رزرو به مصرف‌شده منتقل شد.');
    }

    public function activateContract(ContractTransitionRequest $request, Contract $contract, TransitionContract $transitionContract): RedirectResponse
    {
        return $this->transitionContract($request, $contract, $transitionContract, 'activate', 'قرارداد فعال و برنامهٔ اقساط آن منتشر شد.');
    }

    private function transitionContract(ContractTransitionRequest $request, Contract $contract, TransitionContract $transitionContract, string $transition, string $successMessage): RedirectResponse
    {
        $this->authorize('view', $contract->customer);
        $input = $request->validated();
        $transitionContract->handle($contract->id, $transition, $input['request_id'], $input['reason'], $request->user());

        return redirect()->route('customers.show', $contract->customer_id)->with('success', $successMessage);
    }

    public function createContractDraft(ContractDraftRequest $request, Customer $customer, CreateContractDraft $createContractDraft): RedirectResponse
    {
        $this->authorize('view', $customer);
        $input = $request->validated();
        abort_unless((int) $input['customer_id'] === (int) $customer->id, 422, 'مشتری فرم با پروندهٔ بازشده یکسان نیست.');

        $createContractDraft->handle($customer, $input, $request->user());

        return redirect()->route('customers.show', $customer)->with('success', 'پیش‌نویس قرارداد و برنامهٔ اقساط آن ذخیره شد. پرداخت یا رزرو اعتبار انجام نشده است.');
    }

    public function submitKyc(KycSubmissionRequest $request, Customer $customer, SubmitKycCase $submitKycCase): RedirectResponse
    {
        $this->authorize('update', $customer);
        $submitKycCase->handle($customer, $request->user(), 'manual', $request->attributes->get('request_id'), $request->ip());

        return redirect()->route('customers.show', $customer)->with('success', 'پروندهٔ احراز هویت در صف بررسی قرار گرفت.');
    }

    public function reviewKyc(KycReviewRequest $request, KycCase $kycCase, ReviewKycCase $reviewKycCase): RedirectResponse
    {
        $this->authorize('view', $kycCase->customer);
        $status = KycStatus::from($request->validated('status'));
        $reviewKycCase->handle($kycCase, $status, $request->validated('review_note'), $request->user(), $request->attributes->get('request_id'), $request->ip());

        return redirect()->route('customers.show', $kycCase->customer_id)->with('success', 'نتیجهٔ بررسی احراز هویت ثبت شد.');
    }

    public function adjustCredit(CreditLimitAdjustmentRequest $request, Customer $customer, AdjustCreditLimit $adjustCreditLimit): RedirectResponse
    {
        $this->authorize('view', $customer);
        abort_unless($request->user()->hasPermissionTo('credit.adjust'), 403);
        $adjustCreditLimit->handle(
            $customer->id,
            $request->validated('new_limit_toman'),
            $request->validated('reason'),
            $request->validated('request_id'),
            $request->user(),
        );

        return redirect()->route('customers.show', $customer)->with('success', 'سقف اعتبار با ثبت سابقه تغییر کرد.');
    }

    public function registerPayment(PaymentIntentRequest $request, Customer $customer, RegisterPaymentIntent $registerPaymentIntent): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('payments.record'), 403);
        $this->authorize('view', $customer);
        $input = $request->validated();
        abort_unless((int) $input['customer_id'] === (int) $customer->id, 422, 'مشتری درخواست با پروندهٔ بازشده یکسان نیست.');

        if (! empty($input['contract_id'])) {
            $contract = Contract::query()->findOrFail($input['contract_id']);
            abort_unless((int) $contract->customer_id === (int) $customer->id, 422, 'قرارداد به این مشتری تعلق ندارد.');
            if (! $request->user()->hasPermissionTo('customers.view_all')) {
                abort_unless((int) $contract->branch_id === (int) $request->user()->branch_id, 403);
            }
        }

        $registerPaymentIntent->handle($input, $request->user());

        return redirect()->route('customers.show', $customer)->with('success', 'درخواست پرداخت ثبت شد و تا تطبیق درگاه در وضعیت انتظار می‌ماند.');
    }
}
