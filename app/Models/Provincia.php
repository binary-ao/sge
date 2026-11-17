<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
{
    use RegistaActividade;

    public const ROTULO = 'província';

    protected $table = 'provincias';

    protected $fillable = ['nome', 'codigo', 'capital', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class);
    }
}
