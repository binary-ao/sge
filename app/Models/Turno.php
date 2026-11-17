<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    use RegistaActividade;

    public const ROTULO = 'turno';

    protected $table = 'turnos';

    protected $fillable = ['nome', 'hora_inicio', 'hora_fim', 'ordem', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function getHorarioAttribute(): string
    {
        return $this->hora_inicio && $this->hora_fim
            ? substr($this->hora_inicio, 0, 5).' – '.substr($this->hora_fim, 0, 5)
            : '—';
    }
}
