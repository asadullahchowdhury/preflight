<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $analysis_id
 * @property int $statement_index
 * @property string|null $migration
 * @property string|null $table_name
 * @property string $rule
 * @property string $severity
 * @property string|null $algorithm
 * @property string|null $lock_level
 * @property bool $rebuilds_table
 * @property bool $blocks_writes
 * @property string $operation
 * @property string $message
 * @property string|null $suggestion
 */
#[Fillable([
    'analysis_id', 'statement_index', 'migration', 'table_name', 'rule', 'severity', 'algorithm', 'lock_level',
    'rebuilds_table', 'blocks_writes', 'operation', 'message', 'suggestion',
])]
class Finding extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statement_index' => 'integer',
            'rebuilds_table' => 'boolean',
            'blocks_writes' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Analysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }
}
