<?php

namespace App\Repositories\Signals;

use App\Models\SignalBackfillBatch;

class EloquentSignalBackfillBatchRepository implements SignalBackfillBatchRepository
{
    public function create(array $data): SignalBackfillBatch
    {
        return SignalBackfillBatch::query()->create($data);
    }

    public function update(SignalBackfillBatch $batch, array $data): SignalBackfillBatch
    {
        $batch->fill($data);
        $batch->save();

        return $batch;
    }
}
