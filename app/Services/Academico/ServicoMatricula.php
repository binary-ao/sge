<?php

namespace App\Services\Academico;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Curso;
use App\Models\Escola;
use App\Models\Matricula;
use App\Models\Turma;
use App\Support\Sequencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras de negócio das matrículas e da distribuição de alunos por turmas.
 * Todas as verificações são feitas no servidor.
 */
class ServicoMatricula
{
    /**
     * Verifica a coerência dos dados de uma matrícula e devolve a lista de erros (campo => mensagem).
     */
    public function verificar(Escola $escola, Aluno $aluno, array $dados, ?Matricula $actual = null): array
    {
        $erros = [];
        $classe = Classe::with('nivelEnsino')->find($dados['classe_id']);

        if ($aluno->estado !== 'activo' && ! $actual) {
            $erros['aluno_id'] = 'Só é possível matricular alunos com a situação "Activo".';
        }

        // Não há matrículas duplicadas no mesmo ano lectivo (também garantido por índice único).
        $duplicada = Matricula::withoutGlobalScope('escola')
            ->where('aluno_id', $aluno->id)->where('ano_lectivo_id', $dados['ano_lectivo_id'])
            ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))->exists();
        if ($duplicada) {
            $erros['ano_lectivo_id'] = 'O aluno já tem uma matrícula neste ano lectivo.';
        }

        if ($classe && ! $escola->niveisEnsino()->whereKey($classe->nivel_ensino_id)->exists()) {
            $erros['classe_id'] = 'A escola não ministra o nível de ensino desta classe.';
        }

        if ($classe?->nivelEnsino?->exige_curso && empty($dados['curso_id'])) {
            $erros['curso_id'] = 'Esta classe exige a indicação do curso.';
        }
        if (! empty($dados['curso_id'])) {
            $curso = Curso::withoutGlobalScope('escola')->whereKey($dados['curso_id'])
                ->where(fn ($q) => $q->whereNull('escola_id')->orWhere('escola_id', $escola->id))->first();
            if (! $curso || ($classe && $curso->nivel_ensino_id !== $classe->nivel_ensino_id)) {
                $erros['curso_id'] = 'O curso seleccionado não corresponde ao nível de ensino da classe.';
            }
        }

        if (! empty($dados['turma_id'])) {
            $turma = Turma::withoutGlobalScope('escola')->where('escola_id', $escola->id)->find($dados['turma_id']);
            if ($erro = $this->erroTurma($turma, $dados, $actual)) {
                $erros['turma_id'] = $erro;
            }
        }

        return $erros;
    }

    /** Valida se a turma é compatível com a matrícula e tem lugares disponíveis. */
    public function erroTurma(?Turma $turma, array $dados, ?Matricula $actual = null): ?string
    {
        if (! $turma) {
            return 'Turma inválida.';
        }
        if (! $turma->activo) {
            return 'A turma está inactiva.';
        }
        if ((int) $turma->ano_lectivo_id !== (int) $dados['ano_lectivo_id']
            || (int) $turma->classe_id !== (int) $dados['classe_id']
            || (int) $turma->curso_id !== (int) ($dados['curso_id'] ?? 0)) {
            return 'A turma não corresponde ao ano lectivo, classe e curso da matrícula.';
        }
        $ocupados = $turma->matriculasActivas()->withoutGlobalScope('escola')
            ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))->count();
        if ($ocupados >= $turma->capacidade) {
            return "A turma {$turma->designacao} está completa ({$turma->capacidade} lugares).";
        }

        return null;
    }

    public function criar(Escola $escola, Aluno $aluno, array $dados, ?int $utilizadorId): Matricula
    {
        if ($erros = $this->verificar($escola, $aluno, $dados)) {
            throw ValidationException::withMessages($erros);
        }

        return DB::transaction(function () use ($escola, $aluno, $dados, $utilizadorId) {
            $ano = AnoLectivo::findOrFail($dados['ano_lectivo_id']);
            $anoNum = (int) $ano->data_inicio->format('Y');
            $turma = ! empty($dados['turma_id']) ? Turma::find($dados['turma_id']) : null;

            return Matricula::create([
                'aluno_id' => $aluno->id,
                'ano_lectivo_id' => $ano->id,
                'classe_id' => $dados['classe_id'],
                'curso_id' => $dados['curso_id'] ?? null,
                'turma_id' => $turma?->id,
                'turno_id' => $turma?->turno_id ?? $dados['turno_id'],
                'numero' => Sequencia::formatar($anoNum, Sequencia::proximo($escola->id, 'matricula', $anoNum)),
                'tipo' => $dados['tipo'],
                'data_matricula' => $dados['data_matricula'],
                'estado' => 'activa',
                'observacoes' => $dados['observacoes'] ?? null,
                'registado_por' => $utilizadorId,
            ]);
        });
    }

    /** Coloca uma matrícula numa turma (distribuição), validando compatibilidade e capacidade. */
    public function colocarNaTurma(Matricula $matricula, Turma $turma): void
    {
        if ($matricula->estado !== 'activa') {
            throw ValidationException::withMessages(['matriculas' => "A matrícula {$matricula->numero} não está activa."]);
        }
        $erro = $this->erroTurma($turma, $matricula->only('ano_lectivo_id', 'classe_id', 'curso_id'), $matricula);
        if ($erro) {
            throw ValidationException::withMessages(['matriculas' => $erro]);
        }
        $matricula->update(['turma_id' => $turma->id, 'turno_id' => $turma->turno_id]);
    }
}
