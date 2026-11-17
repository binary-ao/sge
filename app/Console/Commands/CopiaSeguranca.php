<?php

namespace App\Console\Commands;

use App\Support\Auditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Cópia de segurança completa: base de dados (mysqldump) + documentos carregados.
 * Gera storage/app/private/backups/sige_AAAAMMDD_HHMMSS.zip e mantém as N cópias mais recentes.
 *
 * Restauro (manual, controlado): extrair o ZIP, importar base_de_dados.sql com o cliente mysql
 * e repor a pasta documentos/ em storage/app/private/escolas.
 */
class CopiaSeguranca extends Command
{
    protected $signature = 'sige:backup {--manter= : Número de cópias a manter (por omissão, config sige.backups.manter)}';

    protected $description = 'Cria uma cópia de segurança da base de dados e dos documentos carregados';

    public function handle(): int
    {
        $pasta = self::pasta();
        File::ensureDirectoryExists($pasta);
        $nome = 'sige_'.now()->format('Ymd_His');
        $sql = $pasta.DIRECTORY_SEPARATOR.$nome.'.sql';
        $zip = $pasta.DIRECTORY_SEPARATOR.$nome.'.zip';

        try {
            $this->info('A exportar a base de dados…');
            $this->exportarBaseDados($sql);

            $this->info('A comprimir com os documentos carregados…');
            $arquivo = new ZipArchive;
            if ($arquivo->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Não foi possível criar o ficheiro ZIP.');
            }
            $arquivo->addFile($sql, 'base_de_dados.sql');
            $documentos = storage_path('app/private/escolas');
            if (is_dir($documentos)) {
                foreach (File::allFiles($documentos) as $f) {
                    $arquivo->addFile($f->getPathname(), 'documentos/'.str_replace('\\', '/', $f->getRelativePathname()));
                }
            }
            $arquivo->addFromString('LEIA-ME.txt', implode("\n", [
                config('sige.nome_completo').' — cópia de segurança de '.now()->format('d/m/Y H:i'),
                'Base de dados: '.config('database.connections.mysql.database'),
                '',
                'Restauro:',
                '1. mysql -u UTILIZADOR -p NOME_DA_BASE < base_de_dados.sql',
                '2. Copiar a pasta documentos/ para storage/app/private/escolas/',
            ]));
            $arquivo->close();
        } catch (\Throwable $e) {
            File::delete([$sql, $zip]);
            $this->error('Falha na cópia de segurança: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        } finally {
            File::delete($sql);
        }

        $removidas = $this->aplicarRetencao((int) ($this->option('manter') ?? config('sige.backups.manter', 14)));
        Auditoria::registar('backup', 'Cópia de segurança criada: '.basename($zip), null, ['tamanho' => filesize($zip), 'removidas' => $removidas]);
        $this->info('Cópia criada: '.$zip.' ('.round(filesize($zip) / 1024 / 1024, 2).' MB)'.($removidas ? "; {$removidas} cópia(s) antiga(s) removida(s)." : '.'));

        return self::SUCCESS;
    }

    public static function pasta(): string
    {
        return storage_path('app/private/backups');
    }

    private function exportarBaseDados(string $destino): void
    {
        $c = config('database.connections.'.config('database.default'));
        $mysqldump = config('sige.backups.mysqldump');

        // Sem shell: argumentos passados um a um; a senha vai por variável de ambiente (não aparece na linha de comandos).
        $processo = new Process([
            $mysqldump, '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
            '--single-transaction', '--routines', '--default-character-set=utf8mb4', '--result-file='.$destino, $c['database'],
        ], null, ['MYSQL_PWD' => (string) $c['password']]);
        $processo->setTimeout(600)->run();

        if (! $processo->isSuccessful() || ! is_file($destino) || filesize($destino) < 100) {
            throw new RuntimeException(trim($processo->getErrorOutput()) ?: 'mysqldump não produziu resultado. Verifique SIGE_MYSQLDUMP no .env.');
        }
    }

    private function aplicarRetencao(int $manter): int
    {
        $copias = collect(File::glob(self::pasta().DIRECTORY_SEPARATOR.'sige_*.zip'))->sortDesc()->values();
        $antigas = $copias->slice(max(1, $manter));
        File::delete($antigas->all());

        return $antigas->count();
    }
}
