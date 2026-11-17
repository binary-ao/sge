<?php

namespace Database\Seeders;

use App\Models\AnoLectivo;
use App\Models\AtribuicaoProfessor;
use App\Models\Aluno;
use App\Models\Classe;
use App\Models\Curso;
use App\Models\Encarregado;
use App\Models\Escola;
use App\Models\Matricula;
use App\Models\PlanoCurricular;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\User;
use App\Support\Sequencia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dados académicos DEMONSTRATIVOS (fictícios): professores, alunos, encarregados,
 * turmas, matrículas e atribuições de professores, para o ano lectivo actual.
 * A geração é determinística (semente fixa) para que a demonstração seja reprodutível.
 */
class DemoAcademicoSeeder extends Seeder
{
    private const NOMES_M = ['António', 'Manuel', 'João', 'Domingos', 'Francisco', 'Pedro', 'Paulo', 'José', 'Miguel', 'Afonso',
        'Adão', 'Bernardo', 'Celestino', 'Daniel', 'Edson', 'Fernando', 'Gaspar', 'Hélder', 'Isaías', 'Jacinto', 'Kiala', 'Lukeny',
        'Mateus', 'Nelson', 'Osvaldo', 'Quintino', 'Rui', 'Sebastião', 'Tomás', 'Valdemar', 'Wilson', 'Zeferino', 'Ngola', 'Kanda'];

    private const NOMES_F = ['Maria', 'Ana', 'Joana', 'Teresa', 'Esperança', 'Luísa', 'Rosa', 'Isabel', 'Joaquina', 'Domingas',
        'Albertina', 'Beatriz', 'Conceição', 'Delfina', 'Eunice', 'Fátima', 'Graça', 'Helena', 'Inês', 'Josefa', 'Kiesse', 'Lurdes',
        'Madalena', 'Nzinga', 'Olga', 'Paulina', 'Rita', 'Sara', 'Telma', 'Victória', 'Wanda', 'Yolanda', 'Ndalu', 'Nsimba'];

    private const APELIDOS = ['Bento', 'Mateus', 'Kiala', 'Neto', 'Sebastião', 'Manuel', 'Domingos', 'Afonso', 'Cassule', 'Lukamba',
        'Tchipa', 'Quiala', 'Mbala', 'Panzo', 'Nzau', 'Kalunga', 'Fernandes', 'João', 'Pedro', 'Miguel', 'Francisco', 'Cardoso',
        'Lopes', 'Gaspar', 'Mendes', 'Simão', 'Paxe', 'Massala', 'Dembo', 'Cabanga', 'Muxito', 'Vunge', 'Quissanga', 'Ngonga'];

    private const LOCALIDADES = ['Caxito', 'Ambriz', 'Muxaluando', 'Quibaxe', 'Pango Aluquém', 'Bula Atumba', 'Luanda', 'Uíge', 'Mabubas', 'Úcua'];

    private AnoLectivo $ano;
    private array $turnos;

    public function run(): void
    {
        mt_srand(2026);
        $this->ano = AnoLectivo::where('actual', true)->firstOrFail();
        $this->turnos = Turno::pluck('id', 'nome')->all();

        DB::transaction(function () {
            foreach (Escola::with('niveisEnsino', 'tipologia')->orderBy('codigo')->get() as $escola) {
                $this->escola($escola);
            }
        });
    }

    private function escola(Escola $escola): void
    {
        $turmas = $this->criarTurmas($escola);
        if (! $turmas) {
            return;
        }

        // Disciplinas necessárias segundo os planos curriculares das turmas.
        $planos = [];
        $disciplinas = [];
        foreach ($turmas as $turma) {
            $plano = PlanoCurricular::resolver($escola->id, $this->ano->id, $turma->classe_id, $turma->curso_id);
            $planos[$turma->id] = $plano;
            foreach ($plano?->disciplinas ?? [] as $d) {
                $disciplinas[$d->id] = $d;
            }
        }

        $responsavel = $this->criarProfessores($escola, $disciplinas);

        foreach ($turmas as $turma) {
            $directorTurma = null;
            foreach ($planos[$turma->id]?->disciplinas ?? [] as $d) {
                $professorId = $responsavel[$d->id];
                $this->novo(new AtribuicaoProfessor(['professor_id' => $professorId, 'turma_id' => $turma->id, 'disciplina_id' => $d->id]), $escola);
                $directorTurma ??= $professorId;
            }
            $turma->forceFill(['director_turma_id' => $directorTurma])->saveQuietly();
            $this->criarAlunos($escola, $turma, mt_rand(14, 24));
        }
    }

    /** @return Turma[] */
    private function criarTurmas(Escola $escola): array
    {
        $niveis = $escola->niveisEnsino->pluck('codigo')->all();
        $classe = fn (string $nivel, string|int $chave) => Classe::whereHas('nivelEnsino', fn ($q) => $q->where('codigo', $nivel))
            ->where(is_int($chave) ? 'numero' : 'nome', $chave)->first();
        $curso = fn (string $codigo) => Curso::whereNull('escola_id')->where('codigo', $codigo)->first();

        $definicoes = [];
        foreach ($niveis as $nivel) {
            $definicoes = array_merge($definicoes, match ($nivel) {
                'PRE' => [[$classe('PRE', 'Iniciação'), null, 'Manhã']],
                'PRIM' => [[$classe('PRIM', 1), null, 'Manhã'], [$classe('PRIM', 4), null, 'Manhã'], [$classe('PRIM', 6), null, 'Tarde']],
                'ESG1' => [[$classe('ESG1', 7), null, 'Tarde'], [$classe('ESG1', 9), null, 'Tarde']],
                'ESG2' => [[$classe('ESG2', 10), $curso('CFB'), 'Manhã'], [$classe('ESG2', 12), $curso('CEJ'), 'Manhã']],
                'ETP' => $escola->tipologia->sigla === 'EFP'
                    ? [[$classe('ETP', 10), $curso('FPEP'), 'Manhã'], [$classe('ETP', 12), $curso('FPEP'), 'Manhã']]
                    : [[$classe('ETP', 10), $curso('INF'), 'Manhã'], [$classe('ETP', 12), $curso('CG'), 'Tarde']],
                'ADUL' => [[$classe('ADUL', 'Alfabetização — Módulo 1'), null, 'Noite'], [$classe('ADUL', 'Pós-Alfabetização'), null, 'Noite']],
                default => [],
            });
        }

        $turmas = [];
        foreach ($definicoes as $i => [$cl, $cu, $turno]) {
            $turmas[] = $this->novo(new Turma([
                'ano_lectivo_id' => $this->ano->id, 'classe_id' => $cl->id, 'curso_id' => $cu?->id,
                'turno_id' => $this->turnos[$turno], 'nome' => 'A', 'sala' => 'Sala '.($i + 1),
                'capacidade' => $cl->nivelEnsino->codigo === 'PRE' ? 30 : 40, 'activo' => true,
            ]), $escola);
        }

        return $turmas;
    }

    /**
     * Cria o corpo docente e devolve o professor responsável por cada disciplina.
     * Nas escolas do professor de demonstração, a Matemática fica a seu cargo.
     */
    private function criarProfessores(Escola $escola, array $disciplinas): array
    {
        $responsavel = [];
        $demo = User::where('email', 'professor@sige.demo.ao')->first();

        if ($demo && $demo->escolas()->whereKey($escola->id)->exists()) {
            $mat = collect($disciplinas)->firstWhere('sigla', 'MAT');
            $prof = $this->novo(new Professor([
                'utilizador_id' => $demo->id, 'nome' => $demo->nome, 'genero' => 'M',
                'data_nascimento' => '1985-03-14', 'numero_identificacao' => '004581234LA041',
                'telefone' => '+244 923 000 101', 'email' => $demo->email, 'habilitacoes' => 'licenciatura',
                'especialidade' => 'Matemática', 'situacao_profissional' => 'efectivo', 'data_admissao' => '2012-02-01', 'activo' => true,
            ]), $escola);
            if ($mat) {
                $responsavel[$mat->id] = $prof->id;
                unset($disciplinas[$mat->id]);
            }
        }

        foreach (array_chunk($disciplinas, 2, true) as $grupo) {
            $genero = mt_rand(0, 1) ? 'M' : 'F';
            $prof = $this->novo(new Professor([
                'nome' => $this->nome($genero),
                'genero' => $genero,
                'data_nascimento' => Carbon::create(mt_rand(1965, 1997), mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                'numero_identificacao' => $this->bi(),
                'telefone' => $this->telefone(),
                'habilitacoes' => ['licenciatura', 'licenciatura', 'bacharelato', 'mestrado', 'ensino_medio'][mt_rand(0, 4)],
                'especialidade' => collect($grupo)->pluck('nome')->join(' / '),
                'situacao_profissional' => ['efectivo', 'efectivo', 'efectivo', 'contratado', 'colaborador'][mt_rand(0, 4)],
                'data_admissao' => Carbon::create(mt_rand(2000, 2025), mt_rand(1, 12), 1)->toDateString(),
                'activo' => true,
            ]), $escola);
            foreach (array_keys($grupo) as $disciplinaId) {
                $responsavel[$disciplinaId] = $prof->id;
            }
        }

        return $responsavel;
    }

    private function criarAlunos(Escola $escola, Turma $turma, int $quantidade): void
    {
        $classe = $turma->classe;
        $adultos = $classe->nivelEnsino->codigo === 'ADUL';
        $idadeBase = match ($classe->nivelEnsino->codigo) {
            'PRE' => 5,
            'ADUL' => 18,
            default => 5 + (int) $classe->numero,
        };

        for ($i = 0; $i < $quantidade; $i++) {
            $genero = mt_rand(0, 100) < 51 ? 'F' : 'M';
            $apelido = self::APELIDOS[mt_rand(0, count(self::APELIDOS) - 1)];
            $idade = $adultos ? mt_rand(18, 45) : $idadeBase + (mt_rand(0, 10) > 7 ? mt_rand(1, 2) : 0);
            $nascimento = Carbon::create((int) $this->ano->data_inicio->format('Y') - $idade, mt_rand(1, 12), mt_rand(1, 28));

            $encarregado = null;
            if (! $adultos) {
                $generoEnc = mt_rand(0, 1) ? 'M' : 'F';
                $encarregado = $this->novo(new Encarregado([
                    'nome' => $this->nome($generoEnc, $apelido),
                    'parentesco' => $generoEnc === 'M' ? 'Pai' : 'Mãe',
                    'telefone' => $this->telefone(),
                    'profissao' => ['Agricultor(a)', 'Comerciante', 'Funcionário(a) público(a)', 'Professor(a)', 'Enfermeiro(a)', 'Motorista'][mt_rand(0, 5)],
                    'endereco' => $escola->comuna.', '.$escola->municipio->nome,
                ]), $escola);
            }

            $aluno = new Aluno([
                'encarregado_id' => $encarregado?->id,
                'nome' => $this->nome($genero, $apelido),
                'genero' => $genero,
                'data_nascimento' => $nascimento->toDateString(),
                'naturalidade' => self::LOCALIDADES[mt_rand(0, count(self::LOCALIDADES) - 1)],
                'nacionalidade' => 'Angolana',
                'tipo_documento' => $idade >= 6 ? 'bi' : 'cedula',
                'numero_identificacao' => $idade >= 6 ? $this->bi() : null,
                'endereco' => ($escola->bairro ?? 'Centro').', '.$escola->comuna,
                'telefone' => $adultos ? $this->telefone() : null,
            ]);
            $aluno->numero_processo = Sequencia::formatar(0, Sequencia::proximo($escola->id, 'aluno'), 6);
            $aluno->estado = 'activo';
            $this->novo($aluno, $escola);

            $anoNum = (int) $this->ano->data_inicio->format('Y');
            $this->novo(new Matricula([
                'aluno_id' => $aluno->id, 'ano_lectivo_id' => $this->ano->id, 'classe_id' => $turma->classe_id,
                'curso_id' => $turma->curso_id, 'turma_id' => $turma->id, 'turno_id' => $turma->turno_id,
                'numero' => Sequencia::formatar($anoNum, Sequencia::proximo($escola->id, 'matricula', $anoNum)),
                'tipo' => in_array($classe->numero, [null, 1, 7, 10], true) ? 'nova' : 'confirmacao',
                'data_matricula' => $this->ano->data_inicio->copy()->subDays(mt_rand(5, 40))->toDateString(),
                'estado' => 'activa',
            ]), $escola);
        }
    }

    private function novo($modelo, Escola $escola)
    {
        $modelo->escola_id = $escola->id;
        $modelo->save();

        return $modelo;
    }

    private function nome(string $genero, ?string $apelido = null): string
    {
        $nomes = $genero === 'M' ? self::NOMES_M : self::NOMES_F;
        $primeiro = $nomes[mt_rand(0, count($nomes) - 1)];
        $meio = self::APELIDOS[mt_rand(0, count(self::APELIDOS) - 1)];

        return $primeiro.' '.$meio.' '.($apelido ?? self::APELIDOS[mt_rand(0, count(self::APELIDOS) - 1)]);
    }

    /** Número de BI no formato angolano (fictício): 9 dígitos + 2 letras + 3 dígitos. */
    private function bi(): string
    {
        return str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT).['LA', 'BO', 'UE', 'ZE'][mt_rand(0, 3)].str_pad((string) mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    }

    private function telefone(): string
    {
        return '+244 9'.[2, 3, 4, 9][mt_rand(0, 3)].mt_rand(1, 9).' '.mt_rand(100, 999).' '.mt_rand(100, 999);
    }
}
