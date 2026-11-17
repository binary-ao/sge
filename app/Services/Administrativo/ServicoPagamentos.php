<?php

namespace App\Services\Administrativo;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Matricula;
use App\Models\Pagamento;
use App\Models\Recibo;
use App\Models\ServicoPagavel;
use App\Support\Auditoria;
use App\Support\Sequencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pagamentos, recibos (numeração sequencial por escola e ano) e mensalidades em atraso.
 */
class ServicoPagamentos
{
    /** Serviços aplicáveis à classe: um serviço específico da classe substitui o geral do mesmo tipo. */
    public function servicosPara(?int $classeId): Collection
    {
        $todos = ServicoPagavel::paraClasse($classeId)->orderBy('tipo')->orderBy('nome')->get();
        $especificos = $todos->whereNotNull('classe_id')->pluck('tipo')->all();

        return $todos->reject(fn ($s) => $s->classe_id === null && $s->tipo === 'mensalidade' && in_array('mensalidade', $especificos, true))->values();
    }

    /** Meses do ano lectivo [['mes' => 9, 'ano' => 2026], …]. */
    public function mesesDoAno(AnoLectivo $ano): Collection
    {
        $meses = collect();
        for ($d = $ano->data_inicio->copy()->startOfMonth(); $d->lte($ano->data_fim); $d->addMonth()) {
            $meses->push(['mes' => (int) $d->month, 'ano' => (int) $d->year]);
        }

        return $meses;
    }

    /** Mensalidades em dívida de uma matrícula até à data (inclui o mês corrente após o dia de vencimento). */
    public function emDivida(Matricula $m, ?Carbon $data = null): Collection
    {
        $data ??= now();
        $servico = $this->servicosPara($m->classe_id)->firstWhere('tipo', 'mensalidade');
        if (! $servico || $m->estado !== 'activa') {
            return collect();
        }
        $ano = $m->anoLectivo ?? AnoLectivo::find($m->ano_lectivo_id);
        $pagos = Pagamento::semFiltroEscola()->where('aluno_id', $m->aluno_id)->where('ano_lectivo_id', $m->ano_lectivo_id)
            ->where('estado', 'valido')->whereNotNull('mes')->get()->map(fn ($p) => $p->ano_mes.'-'.$p->mes)->all();
        $dia = (int) config('administrativo.pagamentos.dia_vencimento', 10);

        return $this->mesesDoAno($ano)->filter(function ($mm) use ($data, $dia, $pagos) {
            $vencimento = Carbon::create($mm['ano'], $mm['mes'], $dia)->endOfDay();

            return $vencimento->lt($data) && ! in_array($mm['ano'].'-'.$mm['mes'], $pagos, true);
        })->map(fn ($mm) => $mm + ['valor' => $servico->valor, 'servico_id' => $servico->id, 'rotulo' => Pagamento::MESES[$mm['mes']].' '.$mm['ano']])->values();
    }

    /** Alunos com mensalidades em atraso na escola activa. */
    public function emAtraso(int $anoLectivoId, ?Carbon $data = null): Collection
    {
        return Matricula::with('aluno.encarregado', 'classe', 'turma', 'anoLectivo')->where('ano_lectivo_id', $anoLectivoId)->where('estado', 'activa')->get()
            ->map(fn ($m) => ['matricula' => $m, 'meses' => $this->emDivida($m, $data)])
            ->filter(fn ($l) => $l['meses']->isNotEmpty())
            ->sortByDesc(fn ($l) => $l['meses']->count())->values();
    }

    /**
     * Regista um ou mais pagamentos num único recibo.
     * $itens: [['servico_id' => 1, 'mes' => 9, 'ano_mes' => 2026], ['servico_id' => 3]]
     */
    public function registar(Aluno $aluno, ?Matricula $matricula, array $itens, string $forma, ?string $referencia, Carbon $data, ?int $utilizadorId): Recibo
    {
        if (! $itens) {
            throw ValidationException::withMessages(['itens' => 'Seleccione pelo menos um serviço a pagar.']);
        }
        $anoLectivo = $matricula?->ano_lectivo_id ?? AnoLectivo::actual()?->id;

        return DB::transaction(function () use ($aluno, $matricula, $itens, $forma, $referencia, $data, $utilizadorId, $anoLectivo) {
            $linhas = [];
            foreach ($itens as $i) {
                $servico = ServicoPagavel::where('activo', true)->findOrFail($i['servico_id']);
                if ($servico->classe_id && $matricula && $servico->classe_id !== $matricula->classe_id) {
                    throw ValidationException::withMessages(['itens' => "O serviço \"{$servico->nome}\" não se aplica à classe do aluno."]);
                }
                $mes = $servico->tipo === 'mensalidade' ? (int) ($i['mes'] ?? 0) : null;
                $anoMes = $servico->tipo === 'mensalidade' ? (int) ($i['ano_mes'] ?? 0) : null;
                if ($servico->tipo === 'mensalidade') {
                    if ($mes < 1 || $mes > 12 || ! $anoMes) {
                        throw ValidationException::withMessages(['itens' => 'Indique o mês de cada mensalidade.']);
                    }
                    $jaPago = Pagamento::lockForUpdate()->where('aluno_id', $aluno->id)->where('ano_lectivo_id', $anoLectivo)
                        ->where('mes', $mes)->where('ano_mes', $anoMes)->where('estado', 'valido')
                        ->whereHas('servico', fn ($q) => $q->where('tipo', 'mensalidade'))->exists();
                    if ($jaPago) {
                        throw ValidationException::withMessages(['itens' => 'A mensalidade de '.Pagamento::MESES[$mes]." {$anoMes} já está paga."]);
                    }
                }
                $linhas[] = [$servico, $mes, $anoMes];
            }

            $anoNum = (int) $data->year;
            $seq = Sequencia::proximo($aluno->escola_id, 'recibo', $anoNum);
            $recibo = Recibo::create([
                'aluno_id' => $aluno->id,
                'numero' => 'RC '.$anoNum.'/'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                'ano' => $anoNum, 'sequencial' => $seq, 'data' => $data->toDateString(),
                'total' => array_sum(array_map(fn ($l) => $l[0]->valor, $linhas)),
                'forma_pagamento' => $forma, 'referencia' => $referencia, 'estado' => 'emitido', 'emitido_por' => $utilizadorId,
            ]);
            foreach ($linhas as [$servico, $mes, $anoMes]) {
                Pagamento::create([
                    'recibo_id' => $recibo->id, 'aluno_id' => $aluno->id, 'matricula_id' => $matricula?->id, 'servico_id' => $servico->id,
                    'ano_lectivo_id' => $anoLectivo, 'mes' => $mes, 'ano_mes' => $anoMes,
                    'descricao' => $servico->nome.($mes ? ' — '.Pagamento::MESES[$mes].' '.$anoMes : ''),
                    'valor' => $servico->valor, 'data_pagamento' => $data->toDateString(), 'forma_pagamento' => $forma,
                    'estado' => 'valido', 'registado_por' => $utilizadorId,
                ]);
            }

            return $recibo;
        });
    }

    public function anular(Recibo $recibo, string $motivo, ?int $utilizadorId): void
    {
        if ($recibo->estado === 'anulado') {
            throw ValidationException::withMessages(['motivo' => 'O recibo já está anulado.']);
        }
        DB::transaction(function () use ($recibo, $motivo, $utilizadorId) {
            $recibo->update(['estado' => 'anulado', 'anulado_por' => $utilizadorId, 'anulado_em' => now(), 'motivo_anulacao' => $motivo]);
            $recibo->pagamentos()->update(['estado' => 'anulado']);
            Auditoria::registar('anular', "Anulou o recibo {$recibo->numero} (".number_format($recibo->total, 2, ',', ' ').' Kz)', $recibo, ['motivo' => $motivo]);
        });
    }

    public static function kz(float $valor): string
    {
        return number_format($valor, 2, ',', ' ').' '.config('administrativo.pagamentos.moeda', 'Kz');
    }
}
