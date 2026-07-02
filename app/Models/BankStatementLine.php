<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_statement_id',
        'fecha',
        'codigo',
        'etiqueta',
        'importe',
        'saldo',
        'casos',
        'sugerencia',
        'contacto',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
        'saldo' => 'decimal:2'
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }
}
