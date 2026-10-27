<?php

class OperatorController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator']);
        $operatorId = Auth::role() === 'operator' ? Auth::id() : null;
        $contracts = Contract::all($operatorId ? ['operator_id' => $operatorId] : []);
        $contractContacts = Contract::contactDirectoryForContracts(array_column($contracts, 'id'));
        $this->render('operator/index', [
            'title' => 'پنل اپراتور',
            'contracts' => $contracts,
            'contractContacts' => $contractContacts,
            'calls' => OperatorCall::all($operatorId),
        ]);
    }

    public function call()
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        try {
            $contract = $this->authorizedContract((int) ($_POST['contract_id'] ?? 0));
            $input = $_POST;
            $input['contract_id'] = (int) $contract['id'];
            $input['action_type'] = $input['action_type'] ?? 'customer_call';
            $input['contact_target'] = $input['contact_target'] ?? 'customer';
            CollectionCommandCenter::recordFollowup($input, (int) Auth::id(), (string) Auth::role());
            set_flash('success', 'گزارش پیگیری در تاریخچهٔ واحد ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت پیگیری انجام نشد.');
        }
        redirect('operator');
    }

    public function referLegal()
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        try {
            $contract = $this->authorizedContract((int) ($_POST['contract_id'] ?? 0));
            LegalCase::createCase(null, (int) $contract['id'], $_POST['notes'] ?? '', $_POST['reason'] ?? 'ارجاع اپراتور');
            set_flash('success', 'پرونده برای بررسی حقوقی ارسال شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ارجاع حقوقی انجام نشد.');
        }
        redirect('operator');
    }

    protected function authorizedContract($contractId)
    {
        $contract = Contract::find((int) $contractId);
        if (!$contract) {
            throw new InvalidArgumentException('قرارداد پیدا نشد.');
        }
        if (Auth::role() === 'operator' && (int) ($contract['assigned_operator_id'] ?? 0) !== (int) Auth::id()) {
            throw new InvalidArgumentException('این قرارداد به شما ارجاع نشده است.');
        }
        return $contract;
    }
}
