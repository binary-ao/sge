<?php

namespace App\Support;

use App\Models\Escola;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Determina o âmbito de dados do utilizador autenticado:
 *  - global / provincia: todas as escolas (a província não escreve dados operacionais);
 *  - municipio: apenas as escolas do respectivo município;
 *  - escola: apenas as escolas atribuídas (utilizador_escola).
 *
 * Mantém também a "escola activa" em sessão, usada pelos módulos operacionais.
 * Toda a validação é feita no servidor.
 */
class ContextoEscola
{
    public const SESSAO = 'escola_activa_id';

    private ?array $idsVisiveisCache = null;
    private bool $idsVisiveisResolvidos = false;
    private ?int $escolaActivaCache = null;
    private bool $escolaActivaResolvida = false;
    private ?int $utilizadorResolvido = null;

    public function utilizador(): ?User
    {
        $u = Auth::user();
        // Se o utilizador autenticado mudar (ex.: testes, novo login), invalida a cache.
        if ($u?->id !== $this->utilizadorResolvido) {
            $this->limparCache();
            $this->utilizadorResolvido = $u?->id;
        }

        return $u;
    }

    public function ambito(): ?string
    {
        return $this->utilizador()?->perfil?->ambito;
    }

    /**
     * IDs das escolas que o utilizador pode consultar. null = todas.
     */
    public function idsEscolasVisiveis(): ?array
    {
        $u = $this->utilizador();
        if ($this->idsVisiveisResolvidos) {
            return $this->idsVisiveisCache;
        }

        $ids = match ($this->ambito()) {
            'global', 'provincia' => null,
            'municipio' => $u->municipio_id
                ? Escola::query()->where('municipio_id', $u->municipio_id)->pluck('id')->map(fn ($i) => (int) $i)->all()
                : [],
            // Escolas desactivadas no cadastro deixam de estar disponíveis para o seu pessoal.
            'escola' => $u->escolas()->where('escolas.activo', true)->pluck('escolas.id')->map(fn ($i) => (int) $i)->all(),
            default => [],
        };

        $this->idsVisiveisResolvidos = true;

        return $this->idsVisiveisCache = $ids;
    }

    public function podeVerEscola(int|Escola $escola): bool
    {
        $id = $escola instanceof Escola ? (int) $escola->id : $escola;
        $ids = $this->idsEscolasVisiveis();

        return $ids === null || in_array($id, $ids, true);
    }

    /**
     * Escola activa para os módulos operacionais (alunos, turmas, notas…).
     */
    public function escolaActivaId(): ?int
    {
        if (! $this->utilizador()) {
            return null;
        }
        if ($this->escolaActivaResolvida) {
            return $this->escolaActivaCache;
        }

        $sessao = session(self::SESSAO);
        $sessao = $sessao !== null ? (int) $sessao : null;

        if ($this->ambito() === 'escola') {
            $ids = $this->idsEscolasVisiveis();
            $id = ($sessao && in_array($sessao, $ids, true)) ? $sessao : ($ids[0] ?? null);
        } else {
            $id = ($sessao && $this->podeVerEscola($sessao) && Escola::whereKey($sessao)->where('activo', true)->exists()) ? $sessao : null;
        }

        $this->escolaActivaResolvida = true;

        return $this->escolaActivaCache = $id;
    }

    public function escolaActiva(): ?Escola
    {
        $id = $this->escolaActivaId();

        return $id ? Escola::with('municipio', 'tipologia')->find($id) : null;
    }

    public function definirEscolaActiva(?int $escolaId): bool
    {
        if ($escolaId !== null && ! $this->podeVerEscola($escolaId)) {
            return false;
        }
        session([self::SESSAO => $escolaId]);
        $this->escolaActivaResolvida = false;

        return true;
    }

    /**
     * Apenas utilizadores de âmbito global ou de escola podem alterar registos operacionais.
     * Direcção Provincial e Municipal têm acesso apenas de consulta.
     */
    public function podeEscreverDadosOperacionais(): bool
    {
        return in_array($this->ambito(), ['global', 'escola'], true);
    }

    /**
     * Ficha de professor do utilizador na escola activa (apenas para o perfil Professor).
     * O professor só consulta as turmas e alunos que lhe estão atribuídos.
     */
    public function professorActual(): ?\App\Models\Professor
    {
        $u = $this->utilizador();
        if (! $u || $u->perfil?->codigo !== \App\Models\Perfil::PROFESSOR || ! $this->escolaActivaId()) {
            return null;
        }

        return \App\Models\Professor::where('utilizador_id', $u->id)->first();
    }

    /** O utilizador tem acesso restrito às suas atribuições (perfil Professor). */
    public function restritoAsAtribuicoes(): bool
    {
        return $this->utilizador()?->perfil?->codigo === \App\Models\Perfil::PROFESSOR;
    }

    public function limparCache(): void
    {
        $this->idsVisiveisResolvidos = false;
        $this->escolaActivaResolvida = false;
        $this->idsVisiveisCache = null;
        $this->escolaActivaCache = null;
    }
}
