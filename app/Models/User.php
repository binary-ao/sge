<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use App\Notifications\RedefinirSenhaNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Utilizador do sistema (tabela "utilizadores").
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RegistaActividade;

    public const ROTULO = 'utilizador';

    protected $table = 'utilizadores';

    protected $fillable = ['nome', 'email', 'telefone', 'password', 'perfil_id', 'municipio_id', 'activo'];

    protected $hidden = ['password', 'remember_token'];

    /** Cache por pedido dos códigos de permissão. */
    private ?array $codigosPermissao = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'activo' => 'boolean',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function escolas(): BelongsToMany
    {
        return $this->belongsToMany(Escola::class, 'utilizador_escola', 'utilizador_id', 'escola_id')->orderBy('nome');
    }

    public function isSuperutilizador(): bool
    {
        return $this->perfil?->codigo === Perfil::SUPERUTILIZADOR;
    }

    public function temPermissao(string $codigo): bool
    {
        if ($this->isSuperutilizador()) {
            return true;
        }
        // Direcções Provincial e Municipal: acesso de consulta, sem escrita operacional.
        if (in_array($this->perfil?->ambito, ['provincia', 'municipio'], true)
            && in_array($codigo, config('permissoes.escrita_operacional', []), true)) {
            return false;
        }
        if ($this->codigosPermissao === null) {
            $this->codigosPermissao = $this->perfil
                ? $this->perfil->permissoes()->pluck('codigo')->all()
                : [];
        }

        return in_array($codigo, $this->codigosPermissao, true);
    }

    public function esquecerPermissoes(): void
    {
        $this->codigosPermissao = null;
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function getIniciaisAttribute(): string
    {
        // Apenas palavras com letras (ex.: "Director do Complexo Escolar n.º 205" → "DE").
        $partes = array_values(array_filter(preg_split('/\s+/', trim($this->nome)), fn ($p) => preg_match('/^\pL{2,}/u', $p)));
        $ini = mb_substr($partes[0] ?? '', 0, 1).(count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');

        return mb_strtoupper($ini);
    }

    /** Descrição do âmbito do utilizador (ex.: "Município do Dande"). */
    public function getAmbitoDescricaoAttribute(): string
    {
        return match ($this->perfil?->ambito) {
            'global' => 'Acesso global',
            'provincia' => 'Província do Bengo',
            'municipio' => $this->municipio ? 'Município: '.$this->municipio->nome : 'Município não atribuído',
            'escola' => $this->escolas->pluck('nome')->join(', ') ?: 'Escola não atribuída',
            default => '—',
        };
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RedefinirSenhaNotification($token));
    }
}
