<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Pagamento extends Model
{
    use PertenceAEscola;

    public const MESES = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];

    protected $table = 'pagamentos';

    protected $fillable = ['recibo_id', 'aluno_id', 'matricula_id', 'servico_id', 'ano_lectivo_id', 'mes', 'ano_mes', 'descricao', 'valor',
        'data_pagamento', 'forma_pagamento', 'estado', 'registado_por'];

    protected $casts = ['valor' => 'float', 'data_pagamento' => 'date'];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new RuntimeException('Os pagamentos não podem ser eliminados; anule o recibo.');
        });
    }

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(Recibo::class);
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(ServicoPagavel::class, 'servico_id');
    }
}
