<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_path',
        'bank_type',
        'account_number',
        'clabe',
        'period_start',
        'period_end',
        'saldo_inicial',
        'saldo_final',
        'total_cargos',
        'total_abonos',
        'count_cargos',
        'count_abonos',
        'calculated_cargos',
        'calculated_abonos',
        'difference_cargos',
        'difference_abonos',
        'is_balanced',
        'status',
        'error_message'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'saldo_inicial' => 'decimal:2',
        'saldo_final' => 'decimal:2',
        'total_cargos' => 'decimal:2',
        'total_abonos' => 'decimal:2',
        'calculated_cargos' => 'decimal:2',
        'calculated_abonos' => 'decimal:2',
        'difference_cargos' => 'decimal:2',
        'difference_abonos' => 'decimal:2',
        'is_balanced' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
