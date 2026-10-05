<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Pos = 'pos';
    case BankTransfer = 'bank_transfer';
    case OnlineGateway = 'online_gateway';
    case Wallet = 'wallet';
    case Manual = 'manual';
}
