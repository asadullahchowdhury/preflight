<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Size of one production table on one day. Numbers only, never row data.
 *
 * @property int $id
 * @property int $repository_id
 * @property string $table_name
 * @property Carbon $captured_on
 * @property int $rows
 * @property int $data_bytes
 * @property int $index_bytes
 */
#[Fillable(['repository_id', 'table_name', 'captured_on', 'rows', 'data_bytes', 'index_bytes'])]
class TableStat extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'rows' => 'integer',
            'data_bytes' => 'integer',
            'index_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
