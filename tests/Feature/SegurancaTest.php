<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SegurancaTest extends TestCase
{
    public function test_cabecalhos_de_seguranca(): void
    {
        $this->get(route('login'))->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->entrarComo('director@sige.demo.ao');
        $this->assertStringContainsString('no-store', $this->get(route('inicio'))->headers->get('Cache-Control'));
    }

    public function test_conteudo_introduzido_e_escapado_xss(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $this->post(route('alunos.store'), [
            'nome' => '<script>alert("x")</script> Teste', 'genero' => 'F', 'data_nascimento' => '2000-01-01', 'nacionalidade' => 'Angolana',
        ])->assertRedirect();
        $aluno = Aluno::where('nome', 'like', '%Teste')->latest('id')->first();

        $html = $this->get(route('alunos.show', $aluno))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_pesquisas_resistem_a_injeccao_sql(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $total = Aluno::count();
        foreach (["' OR '1'='1", "1; DROP TABLE alunos; --", '%" OR 1=1 #'] as $termo) {
            $this->get(route('alunos.index', ['q' => $termo]))->assertOk();
            $this->get(route('escolas.index', ['q' => $termo, 'municipio_id' => $termo]))->assertOk();
        }
        $this->assertSame($total, Aluno::count());
    }

    public function test_campos_protegidos_nao_sao_atribuidos_em_massa(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $u = User::where('email', 'professor@sige.demo.ao')->first();
        // Na conta pessoal não é possível alterar o perfil ou o estado
        $this->entrarComo('professor@sige.demo.ao');
        $this->put(route('conta.update'), ['nome' => 'Professor Alterado', 'perfil_id' => 1, 'activo' => 0, 'email' => 'x@x.ao'])->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertSame('Professor Alterado', $u->nome);
        $this->assertNotSame(1, $u->perfil_id);
        $this->assertTrue($u->activo);
        $this->assertSame('professor@sige.demo.ao', $u->email);
    }

    public function test_senhas_guardadas_com_hash(): void
    {
        foreach (User::all() as $u) {
            $this->assertStringStartsWith('$2y$', $u->getAuthPassword());
        }
    }

    public function test_copias_de_seguranca_so_para_superutilizador_e_sem_path_traversal(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $this->get(route('copias.index'))->assertForbidden();

        $this->entrarComo('admin@sige.demo.ao');
        $this->get(route('copias.index'))->assertOk();
        $this->get(route('copias.descarregar', '..%2F..%2F.env'))->assertNotFound();
        $this->get(route('copias.descarregar', 'sige_20990101_000000.zip'))->assertNotFound();

        // Cópia real (mysqldump) da base de testes
        $antes = count(File::glob(storage_path('app/private/backups/sige_*.zip')));
        $this->post(route('copias.criar'))->assertSessionHas('sucesso');
        $ficheiros = File::glob(storage_path('app/private/backups/sige_*.zip'));
        $this->assertGreaterThanOrEqual($antes, count($ficheiros));
        $ultimo = collect($ficheiros)->sortDesc()->first();
        $this->get(route('copias.descarregar', basename($ultimo)))->assertOk();
        $this->assertDatabaseHas('logs_actividade', ['accao' => 'backup']);
        File::delete($ultimo);
    }

    public function test_paginas_de_erro_em_portugues(): void
    {
        $this->entrarComo('professor@sige.demo.ao');
        $this->get(route('utilizadores.index'))->assertForbidden()->assertSee('Não tem permissão');
        $this->get('/pagina-que-nao-existe')->assertNotFound()->assertSee('não encontrado');
    }
}
