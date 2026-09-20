<?php

namespace App\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    protected $message = 'Wallet does not have sufficient funds to complete this transaction.';
}
