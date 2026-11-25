<?php

namespace App\Console\Commands;

use App\Services\Budget\SavingsInterest;
use App\Support\Money;
use Illuminate\Console\Command;

class AccrueSavingsInterest extends Command
{
    protected $signature = 'savings:accrue-interest';

    protected $description = 'Начислить дневные проценты по накопительным счетам (сложный процент)';

    public function handle(SavingsInterest $interest): int
    {
        $cents = $interest->accrueDue();
        $this->info(__('Начислено: ').Money::format($cents));

        return self::SUCCESS;
    }
}
