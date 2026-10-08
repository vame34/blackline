<?php

namespace App\Repositories\Signals;

use App\Models\SignalBackfillBatch;

interface SignalBackfillBatchRepository
{
    public function create(array $data): SignalBackfillBatch;

    public function update(SignalBackfillBatch $batch, array $data): SignalBackfillBatch;
}
