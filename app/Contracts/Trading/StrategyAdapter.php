<?php

namespace App\Contracts\Trading;

use App\DTOs\Trading\InstrumentData;
use App\DTOs\Trading\StrategyResult;

interface StrategyAdapter
{
    /** Generate fixed Entry/SL/TP scenarios using only data available at each entry. */
    public function generate(InstrumentData $data, array $configuration, bool $simulateExits = false): StrategyResult;
}
