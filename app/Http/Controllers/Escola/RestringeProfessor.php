<?php

namespace App\Http\Controllers\Escola;

use App\Models\Turma;
use App\Support\ContextoEscola;
use Illuminate\Support\Collection;

/**
 * O perfil Professor só consulta as turmas que lhe estão atribuídas
 * (como docente de uma disciplina ou director de turma) e os respectivos alunos.
 */
trait RestringeProfessor
{
    /** IDs das turmas visíveis; null = sem restrição. */
    protected function turmasPermitidas(): ?Collection
    {
        $ctx = app(ContextoEscola::class);
        if (! $ctx->restritoAsAtribuicoes()) {
            return null;
        }
        $professor = $ctx->professorActual();

        return $professor ? Turma::doProfessor($professor)->pluck('id') : collect();
    }

    /** O professor só acede a alunos matriculados nas suas turmas. */
    protected function exigirAlunoPermitido(\App\Models\Aluno $aluno): void
    {
        $turmas = $this->turmasPermitidas();
        if ($turmas !== null) {
            abort_unless(\App\Models\Matricula::where('aluno_id', $aluno->id)->whereIn('turma_id', $turmas)->exists(), 404);
        }
    }

    protected function exigirTurmaPermitida(int $turmaId): void
    {
        $ids = $this->turmasPermitidas();
        abort_if($ids !== null && ! $ids->contains($turmaId), 404);
    }
}
