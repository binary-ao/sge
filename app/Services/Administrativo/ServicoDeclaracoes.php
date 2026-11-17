<?php

namespace App\Services\Administrativo;

use App\Models\Declaracao;
use App\Models\Matricula;
use App\Services\Avaliacao\ConstrutorPautas;
use App\Support\Sequencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Emissão de declarações preenchidas automaticamente com os dados do aluno e da escola.
 * O conteúdo é guardado no momento da emissão: a reimpressão é sempre idêntica ao original.
 */
class ServicoDeclaracoes
{
    public function __construct(private ConstrutorPautas $pautas, private ServicoFrequencia $frequencia) {}

    public function emitir(Matricula $m, string $tipo, ?string $finalidade, ?int $utilizadorId): Declaracao
    {
        $m->loadMissing('aluno', 'classe.nivelEnsino', 'curso', 'turma', 'turno', 'anoLectivo', 'escola.municipio.provincia');

        if ($m->estado === 'anulada') {
            throw ValidationException::withMessages(['matricula_id' => 'Não é possível emitir declarações de uma matrícula anulada.']);
        }
        if ($tipo === 'frequencia' && ! in_array($m->estado, ['activa', 'concluida'], true)) {
            throw ValidationException::withMessages(['tipo' => 'A declaração de frequência exige uma matrícula activa ou concluída.']);
        }

        $conteudo = [
            'escola' => $m->escola->only('nome', 'codigo', 'director_nome', 'endereco', 'telefone', 'email') + [
                'municipio' => $m->escola->municipio->nome, 'provincia' => $m->escola->municipio->provincia->nome,
            ],
            'aluno' => [
                'nome' => $m->aluno->nome, 'processo' => $m->aluno->numero_processo, 'genero' => $m->aluno->genero,
                'nascimento' => $m->aluno->data_nascimento->format('d/m/Y'), 'naturalidade' => $m->aluno->naturalidade,
                'nacionalidade' => $m->aluno->nacionalidade,
                'documento' => $m->aluno->numero_identificacao ? (\App\Models\Aluno::TIPOS_DOCUMENTO[$m->aluno->tipo_documento] ?? 'Documento').' n.º '.$m->aluno->numero_identificacao : null,
            ],
            'matricula' => [
                'numero' => $m->numero, 'ano_lectivo' => $m->anoLectivo->designacao, 'classe' => $m->classe->nome,
                'nivel' => $m->classe->nivelEnsino->nome, 'curso' => $m->curso?->nome, 'turma' => $m->turma?->nome,
                'turno' => $m->turno?->nome, 'data' => $m->data_matricula->format('d/m/Y'), 'estado' => $m->estado_label,
            ],
        ];

        if ($tipo === 'frequencia') {
            $r = $this->frequencia->resumo($m);
            $conteudo['frequencia'] = ['percentagem' => $r['frequencia'], 'faltas' => $r['tempos'], 'justificadas' => $r['justificadas']];
        }
        if ($tipo === 'notas') {
            $b = $this->pautas->boletim($m);
            $linhas = $b['disciplinas']->map(function ($d) use ($b) {
                $rd = $b['resultados'][$d->id] ?? collect();
                $fmt = fn ($r) => $r && $r->classificacao !== null
                    ? ($b['config']?->eQualitativa() ? $r->classificacao_qualitativa : rtrim(rtrim(number_format($r->classificacao, 2, ',', ''), '0'), ',')
                        .($b['config']?->tipo_classificacao === 'mista' ? ' ('.$r->classificacao_qualitativa.')' : ''))
                    : '—';

                return ['disciplina' => $d->nome, 't1' => $fmt($rd[1] ?? null), 't2' => $fmt($rd[2] ?? null), 't3' => $fmt($rd[3] ?? null), 'final' => $fmt($rd[0] ?? null)];
            })->values()->all();
            if (! collect($linhas)->contains(fn ($l) => $l['t1'] !== '—' || $l['final'] !== '—')) {
                throw ValidationException::withMessages(['tipo' => 'O aluno ainda não tem classificações lançadas nesta matrícula.']);
            }
            $conteudo['notas'] = $linhas;
            $conteudo['resultado'] = $b['final'] ? ['rotulo' => $b['final']->rotulo, 'justificacao' => $b['final']->justificacao] : null;
            $conteudo['configuracao'] = $b['config'] ? $b['config']->nome.' (v'.$b['config']->versao.')' : null;
        }

        return DB::transaction(function () use ($m, $tipo, $finalidade, $utilizadorId, $conteudo) {
            $ano = (int) now()->year;
            $seq = Sequencia::proximo($m->escola_id, 'declaracao', $ano);

            return Declaracao::create([
                'aluno_id' => $m->aluno_id, 'matricula_id' => $m->id, 'tipo' => $tipo,
                'numero' => 'DEC '.$ano.'/'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT), 'ano' => $ano, 'sequencial' => $seq,
                'finalidade' => $finalidade, 'conteudo' => $conteudo,
                'codigo_verificacao' => strtoupper(Str::random(10)), 'emitida_por' => $utilizadorId,
            ]);
        });
    }
}
