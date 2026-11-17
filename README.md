# SIGE Bengo — Sistema Integrado de Gestão Escolar

Plataforma web para a gestão, monitorização e acompanhamento das instituições de ensino da província do Bengo,
destinada à Direcção Provincial da Educação, às Direcções Municipais e às escolas. MVP desenvolvido por **Binary**.

- Arquitectura: [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md)
- Contas e roteiro de demonstração: [CREDENCIAIS_DEMO.md](CREDENCIAIS_DEMO.md)

## Módulos

| Área | Funcionalidades |
|---|---|
| Cadastros e Configurações | Municípios, escolas, disciplinas, níveis de ensino, classes e ciclos, anos lectivos e períodos de avaliação: cadastro, consulta, edição e activação/desactivação lógica, com pesquisa, filtros e paginação; permissões por perfil e jurisdição validadas no servidor |
| Rede escolar | Província → municípios → escolas; tipologias e níveis de ensino como catálogos independentes; natureza; coordenadas |
| Acessos | Autenticação, recuperação de senha, 6 perfis, matriz de permissões editável, âmbito por município e escola, histórico de actividades |
| Gestão académica | Cursos, planos curriculares (disciplinas por classe, obrigatórias ou opcionais), turnos; professores, alunos, encarregados, documentos, matrículas, turmas, atribuições |
| Avaliação | Motor de fórmulas seguro (sem `eval`), configurações versionadas (Decreto Executivo n.º 424/25), escalas, regras de transição com justificação, lançamento de notas, fecho e reabertura de períodos, pautas em PDF e Excel |
| Painéis | Provincial, municipal e da escola; filtros globais; gráficos interactivos; mapa da rede; relatórios em PDF e Excel |
| Administrativo | Faltas e justificativos (frequência integrada nas regras), serviços e preços, pagamentos, recibos sequenciais (anulação sem eliminação), declarações |
| Sistema | Cópias de segurança (base de dados e documentos), cabeçalhos de segurança, páginas de erro |

## Requisitos

- PHP 8.2 com as extensões `pdo_mysql`, `mbstring`, `openssl`, `gd`, `zip`, `intl` e `fileinfo`
- MySQL 5.7+ ou MariaDB 10.4+ (com `mysqldump`, para as cópias de segurança)
- Composer 2
- Apache com `mod_rewrite` (XAMPP)

## Instalação (XAMPP)

```bash
cd C:\xampp\htdocs\sg_escolar
composer install
copy .env.example .env
php artisan key:generate
```

1. Crie a base de dados:
   ```sql
   CREATE DATABASE sg_escolar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Confirme os dados `DB_*` no `.env`.
3. Crie as tabelas e carregue os dados de base e de demonstração (cerca de 1 minuto):
   ```bash
   php artisan migrate --seed
   ```
4. Abra <http://localhost/sg_escolar>.

O `.htaccess` da raiz encaminha os pedidos para `public/` e bloqueia o acesso a ficheiros internos (`.env`, `vendor/`, `storage/`…).

Para repor os dados de demonstração antes de uma apresentação: `php artisan migrate:fresh --seed`. **Esta operação apaga todos os dados.**

## Passagem a produção — lista de verificação

1. **Servidor**: `DocumentRoot` do Apache apontado para a pasta `public/`; HTTPS activo.
2. **`.env`**:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
   - `SESSION_SECURE_COOKIE=true` (com HTTPS)
   - `SIGE_MODO_DEMONSTRACAO=false` (retira a faixa e as marcas de "demonstração")
   - `MAIL_*` configurado (recuperação de senha por e-mail)
   - Utilizador de base de dados próprio, com senha, em vez de `root`
3. **Dados**: não carregar os dados demonstrativos. Executar apenas:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=PerfisPermissoesSeeder --force
   php artisan db:seed --class=RedeEscolarSeeder --force        # rever municípios e escolas antes
   php artisan db:seed --class=ConfiguracaoAcademicaSeeder --force
   php artisan db:seed --class=AvaliacaoSeeder --force
   ```
   Criar o primeiro superutilizador: `php artisan sige:criar-administrador`.
4. **Validação regulamentar**: rever os planos curriculares de referência, os parâmetros das regras de transição e de exames, e as escalas, que estão assinalados como "a validar".
5. **Optimização**: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. **Cópias de segurança**: activar o agendador (ver abaixo) e guardar periodicamente cópias num suporte externo.

## Publicação: local → GitHub → servidor

O servidor de produção (`https://sge.binary.ao`, conta cPanel `sgebinary`) obtém o código do GitHub
(`binary-ao/sge`, ramo `main`). Para publicar uma alteração basta:

```bash
git add -A && git commit -m "Descrição da alteração"
git push
```

No servidor, o cron da conta executa [deploy/deploy.sh](deploy/deploy.sh) a cada 2 minutos. Quando há commits
novos, o script coloca o site em manutenção, faz o `git merge --ff-only`, reinstala as dependências se o
`composer.lock` mudou, faz uma cópia de segurança e aplica as migrações se houver migrações novas, refaz as
caches e repõe o site. O registo fica em `~/logs/deploy.log`.

| No servidor | Caminho |
|---|---|
| Código (clone do GitHub) | `/home/sgebinary/sge` |
| Pasta pública (DocumentRoot) | `/home/sgebinary/sge/public` |
| Configuração (fora do Git) | `/home/sgebinary/sge/.env` |
| Versão do PHP (fora do Git) | `/home/sgebinary/.htaccess` (ea-php82) |
| Registo das publicações | `/home/sgebinary/logs/deploy.log` |

Publicação imediata ou reposição das caches, como root:
`su -s /bin/bash sgebinary -c "bash ~/sge/deploy/deploy.sh --forcar"`.

Regras: não editar ficheiros directamente no servidor (o `merge --ff-only` falharia); o `.env`, a base de dados
e os ficheiros carregados (`storage/`) nunca passam pelo GitHub.

## Cópias de segurança

```bash
php artisan sige:backup            # cria storage/app/private/backups/sige_AAAAMMDD_HHMMSS.zip
```

- Cada cópia contém `base_de_dados.sql` (mysqldump completo), os documentos carregados (`documentos/`) e um `LEIA-ME.txt`.
- São mantidas as 14 cópias mais recentes (`SIGE_BACKUPS_MANTER`). O caminho do `mysqldump` é configurável em `SIGE_MYSQLDUMP`.
- O superutilizador pode criar e descarregar cópias em *Sistema › Cópias de Segurança*.
- **Agendamento diário** (01:00, `SIGE_BACKUP_HORA`). No Windows, crie no Agendador de Tarefas uma tarefa que execute a cada minuto:
  `C:\xampp\php\php.exe C:\xampp\htdocs\sg_escolar\artisan schedule:run`

**Restauro** (feito pelo técnico, nunca pela interface):
1. Extrair o ZIP.
2. `mysql -u UTILIZADOR -p sg_escolar < base_de_dados.sql`
3. Copiar `documentos/` para `storage/app/private/escolas/`.

O restauro foi testado: uma cópia completa reposta numa base de dados vazia reproduz exactamente o número de registos de todas as tabelas principais.

## Testes

Os testes usam a base de dados `sg_escolar_test`, que deve ser criada previamente:

```bash
php artisan test
```

Incluem: motor de fórmulas (com tentativas de injecção), regras de transição, cálculo de médias, isolamento entre escolas
e municípios, matriz de permissões de todos os perfis, a sequência completa de demonstração, segurança (XSS, injecção SQL,
atribuição em massa, cabeçalhos), as cópias de segurança e o módulo de Cadastros e Configurações (cadastro, edição,
desactivação, integridade e jurisdição de cada perfil).

## Bibliotecas locais

As bibliotecas de frontend estão em `public/libs/` (sem CDN): Bootstrap 5.3.2, Bootstrap Icons 1.11.3, Chart.js 4.4.1 e Leaflet 1.9.4.
O fundo cartográfico do mapa (OpenStreetMap) é o único recurso que exige ligação à Internet.

## Fora do MVP (preparado para fases futuras)

Portal de alunos e encarregados, aplicação móvel, notificações (SMS, WhatsApp, e-mail), horários, biblioteca, transportes,
cantina, inventário, contabilidade e folha salarial, funcionamento offline, integração com sistemas nacionais e importação
de alunos por Excel.
