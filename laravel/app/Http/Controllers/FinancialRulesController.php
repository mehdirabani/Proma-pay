<?php

namespace App\Http\Controllers;

use App\Actions\Payments\UpdateFinancialRules;
use App\Http\Requests\FinancialRuleUpdateRequest;
use App\Models\FinancialRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class FinancialRulesController extends Controller
{
    public function edit(): View
    {
        return view('settings.financial-rules', [
            'rules' => FinancialRule::query()->findOrFail(1),
            'requestId' => (string) Str::uuid(),
        ]);
    }

    public function update(FinancialRuleUpdateRequest $request, UpdateFinancialRules $updateFinancialRules): RedirectResponse
    {
        $updateFinancialRules->handle($request->validated(), $request->user());

        return redirect()->route('settings.financial-rules.edit')->with('success', 'قواعد محاسبه ذخیره و نسخهٔ جدید آن ثبت شد.');
    }
}
