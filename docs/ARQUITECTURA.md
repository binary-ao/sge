# SIGE Bengo — Arquitectura de Implementação

Sistema Integrado de Gestão Escolar — Direcção Provincial da Educação do Bengo (MVP).
Desenvolvimento: Binary.

> Todos os dados incluídos pelos seeders são **demonstrativos** e não constituem estatísticas oficiais.

---

## 1. Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.2, Laravel 12 (MVC, Eloquent, Middleware, Form Requests) |
| Base de dados | MySQL / MariaDB 10.4 (XAMPP), base `sg_escolar`, `utf8mb4` |
| Frontend | Blade, Bootstrap 5.3.2, Bootstrap Icons, Chart.js — **ficheiros locais** em `public/libs` |
| Documentos | barryvdh/laravel-dompdf (PDF), maatwebsite/excel (XLSX) |
| Mapa (Fase 5) | Leaflet (local); os mosaicos OpenStreetMap exigem ligação à Internet |

Sem React/Vue nem Vite em produção: CSS/JS próprios em `public/css/app.css` e `public/js/app.js`, para funcionar directamente no Apache do XAMPP.

---

## 2. Organização do código

```
app/
  Http/
    Controllers/
      Auth/                    Login, recuperação de senha
      Admin/                   Utilizadores, Perfis, Permissões, Logs
      Rede/                    Municípios, Tipologias, Níveis, Escolas
      Academico/               Anos lectivos, Classes, Disciplinas, Turmas…
      Pessoas/                 Professores, Alunos, Matrículas
      Avaliacao/               Sistemas, Fórmulas, Escalas, Notas, Pautas
      Dashboard/               Provincial, Municipal, Escola
      Financeiro/, Documentos/, Relatorios/
    Middleware/
      VerificarPermissao       permissao:alunos.gerir
      ResolverContextoEscola   define a escola activa em sessão
    Requests/                  Validação no servidor (Form Requests)
  Models/                      Eloquent (nomes em português)
  Models/Concerns/
    PertenceAEscola            Global scope + escola_id automático
  Policies/                    Autorização por registo (âmbito)
  Services/
    Ambito/                    ContextoEscola: o que o utilizador pode ver
    Avaliacao/
      Formula/                 Tokenizer, Parser, Avaliador (sem eval())
      MotorCalculo             Aplica fórmulas versionadas às notas
      MotorRegras              Transição / retenção com justificação
    Documentos/                Numeração sequencial por escola
    Indicadores/               Consultas agregadas dos dashboards
  Support/Auditoria            Registo de actividades
resources/views/
  layouts/app.blade.php        Sidebar recolhível + cabeçalho + breadcrumbs
  components/                  <x-card-stat>, <x-page-header>, <x-filtros>…
```

---

## 3. Hierarquia, âmbito e isolamento multi-escola

**Província → Município → Escola**, numa única base de dados.

Cada registo operacional (aluno, professor, turma, matrícula, nota, falta, pagamento, recibo, declaração…) tem `escola_id` com chave estrangeira e índice.

### Âmbito por perfil

| Perfil | Âmbito | Escrita operacional |
|---|---|---|
| Superutilizador | Global | Sim (em qualquer escola, escolhendo a escola activa) |
| Direcção Provincial | Província | **Não** (consulta). Gere os cadastros: municípios, escolas, tipologias, níveis, disciplinas de referência, classes, ciclos, anos lectivos e períodos |
| Direcção Municipal | Município do utilizador | **Não** (consulta). Gere o cadastro das escolas do seu município |
| Director/Administrativo | Escola(s) atribuídas | Sim |
| Professor | Escola(s) + turmas/disciplinas atribuídas | Notas e faltas das suas atribuições |
| Secretaria/Tesouraria | Escola(s) atribuídas | Alunos, matrículas, pagamentos |

### Mecanismo de isolamento (backend, em três camadas)

1. **Global scope `PertenceAEscola`**: todas as consultas Eloquent aos modelos operacionais são filtradas automaticamente.
   - Utilizador de escola: `escola_id = escola activa` (validada contra `utilizador_escola`).
   - Utilizador municipal: `escola_id IN (escolas do município)`.
   - Provincial ou superutilizador: sem filtro, ou a escola activa quando entra numa escola.
2. **Route model binding**: como o scope é global, um ID de outra escola resolve para **404**. Não é possível ler, alterar ou eliminar registos alheios escrevendo o ID no URL.
3. **Permissões e Policies**: as acções de escrita exigem uma permissão (`alunos.gerir`, …). Os perfis provincial e municipal não possuem permissões de escrita operacional.

Na criação, `escola_id` é preenchido a partir do contexto; o valor enviado no formulário é ignorado.

---

## 4. Perfis e permissões (RBAC próprio)

Tabelas: `perfis`, `permissoes`, `perfil_permissao`, `utilizadores` (com `perfil_id` e `municipio_id`), `utilizador_escola`.

- As permissões têm o formato `modulo.accao` (ex.: `escolas.ver`, `escolas.gerir`, `notas.lancar`, `pagamentos.anular`).
- Cada permissão é registada como Gate; o superutilizador passa por `Gate::before`.
- O middleware `permissao:x` protege as rotas, `@can` controla a interface e as Policies verificam o registo concreto. Exemplo: um professor só lança notas numa turma e disciplina atribuídas.
- O superutilizador pode editar a matriz perfil × permissão na interface.

### 4.1 Cadastros e Configurações

Módulo central dos dados estruturais (menu *Cadastros e Configurações*; rotas em `routes/cadastros.php`;
controllers em `App\Http\Controllers\Cadastros` e `App\Http\Controllers\Rede`). Reutiliza as tabelas existentes.

| Submenu | Tabelas | Consultar | Gerir |
|---|---|---|---|
| Municípios | `municipios` (→ `provincias`) | `municipios.ver` | `municipios.gerir` |
| Escolas (+ Tipologias) | `escolas`, `escola_nivel_ensino`, `tipologias_escola` | `escolas.ver` | `escolas.gerir` (Direcção Municipal: só o seu município); tipologias com `rede.catalogos` |
| Disciplinas | `disciplinas`, `disciplina_nivel_ensino` | `cadastros.ver` | referência: `academico.global`; da escola: `academico.configurar` na escola activa |
| Níveis de Ensino | `niveis_ensino` | `cadastros.ver` | `rede.catalogos` |
| Classes e Ciclos | `classes`, `ciclos`; disciplinas por classe em `planos_curriculares` + `plano_curricular_disciplina` | `cadastros.ver` | `academico.global` |
| Anos Lectivos | `anos_lectivos` | `cadastros.ver` | `academico.global` |
| Períodos de Avaliação | `trimestres` (+ consulta de `periodos_lancamento`) | `cadastros.ver` | `academico.global` |

Atribuição por perfil:

| Perfil | O que pode fazer |
|---|---|
| Superutilizador | Tudo |
| Direcção Provincial | Gerir municípios, escolas, tipologias, níveis, disciplinas de referência, classes, ciclos, anos lectivos e períodos. Não altera registos operacionais das escolas nem a administração do sistema |
| Direcção Municipal | Consultar tudo no seu âmbito; cadastrar, editar, activar e desactivar **apenas** escolas do seu município (não pode criar nem transferir escolas para outro município) |
| Director / Administrativo | Consultar; gerir as disciplinas próprias da sua escola e os planos curriculares da escola |
| Professor | Consultar apenas as disciplinas e as classes das suas atribuições; anos lectivos e períodos |
| Secretaria / Tesouraria | Consultar disciplinas, níveis, classes, anos lectivos e períodos |

Regras de integridade:

- Não há eliminação física: municípios, escolas, disciplinas, níveis, classes e ciclos são **desactivados** (`activo = false`); anos lectivos e períodos nunca se eliminam.
- Um município com escolas activas não pode ser desactivado; uma escola só é reactivada se o município estiver activo.
- Um nível com classes activas, ou um ciclo com classes activas, não pode ser desactivado; uma classe com turmas no ano lectivo actual também não.
- Escola desactivada: o histórico mantém-se, mas sai dos indicadores, dos painéis e dos filtros, e não pode ser escolhida como escola activa. O pessoal dessa escola deixa de ter acesso operacional.
- Duplicações bloqueadas: município (nome por província; código), escola (código institucional), disciplina (nome e código por âmbito; uma disciplina da escola não pode repetir uma de referência do mesmo nível), nível (nome; código), classe (nome por nível), ciclo (nome por nível), ano lectivo (designação; datas sem sobreposição).
- Um único ano lectivo activo (`actual`) e no máximo um ano "em curso". As datas dos trimestres ficam dentro do ano, sem sobreposição e pela ordem I → II → III.
- A desactivação de uma escola é registada no histórico de actividades, tal como todas as alterações aos cadastros.

---

## 5. Modelo de dados (resumo)

Os nomes das tabelas estão em português e no plural. Todas as chaves estrangeiras são reais e as colunas de filtragem estão indexadas.

**Estrutura administrativa**
`provincias`, `municipios`, `escolas` (com `activo`: desactivação lógica do registo), `tipologias_escola`, `niveis_ensino`, `escola_nivel_ensino` (N:N), `disciplina_nivel_ensino` (N:N).
`escolas` inclui: `nome`, `codigo` (único), `municipio_id`, `tipologia_id`, `natureza` (publica / publico_privada / privada), `endereco`, `comuna`, `latitude`, `longitude`, `coordenadas_demo` (bool), `telefone`, `email`, `director_nome`, `estado_funcionamento`, `ano_inicio`, `dados_extra` (JSON para campos futuros).

**Utilizadores**
`utilizadores`, `perfis`, `permissoes`, `perfil_permissao`, `utilizador_escola`, `logs_actividade`, `password_reset_tokens`, `sessions`.

**Configuração académica**
- Globais: `anos_lectivos`, `trimestres`, `ciclos` (por nível), `classes` (por nível/ciclo, com `tipo_avaliacao` = transição / exame nacional / terminal), `turnos`.
- Globais ou da escola (`escola_id` opcional): `cursos`, `disciplinas`.
- Da escola: `planos_curriculares` + `plano_curricular_disciplina`, `turmas`, `fechos_periodo` (abertura e fecho de trimestres por escola).

**Pessoas**
`professores`, `atribuicoes_professor` (professor × turma × disciplina × ano), `alunos`, `encarregados`, `aluno_documentos`, `matriculas`.
Restrição de unicidade: `UNIQUE(aluno_id, ano_lectivo_id, escola_id)`.

**Avaliações**
`sistemas_avaliacao`, `configuracoes_avaliacao`, `componentes_avaliacao`, `formulas_avaliacao` (versionadas), `escalas_classificacao`, `intervalos_classificacao`, `regras_aprovacao`, `regras_transicao`, `regras_frequencia`, `avaliacoes` (provas e instrumentos), `notas`, `resultados_avaliacao` (com `formula_versao_id`), `historico_configuracao`.

**Faltas**: `faltas`, `justificativos_falta`.
**Financeiro**: `servicos_pagaveis`, `pagamentos`, `recibos`, `sequencias_documento`.
**Documentos**: `declaracoes`.

---

## 6. Motor de avaliação (Fase 4)

### 6.1 Fórmulas seguras (sem `eval()`)

Pipeline próprio: **Tokenizer → Parser recursivo descendente → AST → Avaliador**.

- Operadores: `+ - * / ^`, parênteses, comparações e números com vírgula ou ponto decimal (`0,6`).
- Funções em lista branca: `MEDIA()`, `SOMA()`, `CONTAR()`, `MIN()`, `MAX()`, `ARRED()`, `SE()`.
- Variáveis apenas a partir dos componentes declarados, que podem ser escalares (`NPT`) ou listas (`AC` = avaliações contínuas).
- A fórmula é validada ao guardar: sintaxe e variáveis conhecidas.

### 6.2 Configuração

```
SistemaAvaliacao          ex.: Ensino Secundário Geral — DE 424/25
  └─ ConfiguracaoAvaliacao  ano lectivo + nível + ciclo + classe(s) + tipo
       ├─ Componentes        AC (lista), NPT, NEE, NEO, NEP, EN…
       ├─ Fórmulas            MAC, MT, MFD, MEC, MFED… (versionadas)
       ├─ Escala              intervalos quantitativos e qualitativos
       └─ Regras              aprovação / transição / frequência (JSON parametrizado)
```

- Seeders com os modelos do Anexo III, associados aos níveis e às classes correctas. As classes de transição e as classes de Exame Nacional (6.ª, 9.ª, 12.ª) usam fórmulas distintas.
- Cada alteração cria uma nova versão em `historico_configuracao`, com o utilizador, a data, o motivo e um snapshot JSON.
- O endpoint de **pré-visualização** usa o mesmo motor PHP, garantindo que o resultado de teste é exactamente igual ao real.
- **Duplicação** de configurações de um ano lectivo para outro.

### 6.3 Regras de transição

As regras são parametrizáveis e avaliadas por disciplina, classificação e frequência:

- nota mínima por disciplina;
- limite de disciplinas em intervalo condicional (ex.: até 2 entre 7 e 9);
- disciplinas nucleares (Língua Portuguesa, Matemática, específicas) que impedem a transição condicional;
- frequência mínima (2/3);
- acesso a exames de recurso, extraordinário, equivalência e melhoria.

O resultado inclui o estado (Aprovado, Transita condicionalmente, Retido por faltas, …) e a **lista de justificações**.

### 6.4 Integridade

- Fecho de trimestre por escola: as notas ficam bloqueadas e a reabertura exige permissão e motivo.
- `resultados_avaliacao` regista a versão da fórmula usada; o recálculo é explícito e fica registado.

---

## 7. Interface

- Design próprio, institucional:
  - azul-escuro `#0B2A4A` como cor primária, fundo branco e superfícies `#F3F5F8`;
  - semântica Bootstrap (info, success, danger, warning) com tons sóbrios;
  - sem gradientes nem sombras pesadas.
- Sidebar fixa e recolhível (estado guardado em `localStorage`), menu filtrado por permissões, cabeçalho com utilizador, perfil e escola activa, breadcrumbs.
- Componentes Blade reutilizáveis: cartões estatísticos, tabelas com pesquisa, filtros e paginação, modais de confirmação, *toasts* de sucesso e erro.
- Todas as funcionalidades ainda não implementadas são marcadas como **"Em desenvolvimento"**, sem simular funcionamento.

---

## 8. Segurança

- Senhas com bcrypt e protecção CSRF (Blade `@csrf`).
- Validação com Form Requests e Eloquent / Query Builder (consultas parametrizadas, sem SQL concatenado).
- Limite de tentativas de login e utilizadores inactivos bloqueados.
- Middleware `auth` + `permissao` + global scopes + Policies.
- `logs_actividade` regista login, logout e operações de escrita (quem, o quê, IP, data).
- Recibos sem eliminação: apenas anulação, com motivo e utilizador.
- Cópias de segurança: comando `php artisan sige:backup` (mysqldump + documentos num ZIP), agendado diariamente, com retenção configurável e página de descarga reservada ao superutilizador. Restauro verificado.
- Cabeçalhos HTTP de segurança (X-Frame-Options, nosniff, Referrer-Policy, HSTS em HTTPS, no-store em páginas autenticadas) e páginas de erro em português.

---

## 9. Plano por fases

| Fase | Conteúdo | Estado |
|---|---|---|
| 1 | Laravel, BD, layout, autenticação, perfis, permissões, utilizadores, logs | Concluída |
| 2 | Municípios, tipologias, níveis de ensino, escolas, natureza | Concluída |
| 3 | Configuração académica, professores, alunos, matrículas, turmas | Concluída |
| 4 | Motor de avaliação, fórmulas, escalas, notas, médias, regras, pautas | Concluída |
| 5 | Dashboards provincial, municipal e escolar, gráficos, filtros, mapa, relatórios | Concluída |
| 6 | Faltas, pagamentos, recibos, declarações | Concluída |
| 7 | Testes (fluxos, permissões, cálculos), backup, contas de demonstração | Concluída |
| Correcção 1.1 | Módulo Cadastros e Configurações; permissões de gestão para as Direcções Provincial e Municipal; logótipo Binary | Concluída |

Cada fase termina com migrations e seeders a correr do zero (`migrate:fresh --seed`) e com testes automáticos (PHPUnit) da funcionalidade crítica.

---

## 10. Estado final do MVP

Todas as fases estão concluídas. A garantia de qualidade assenta em testes automáticos (PHPUnit) executados sobre os dados de demonstração:

| Área | O que é verificado |
|---|---|
| Motor de fórmulas | Fórmulas do Anexo III, precedência, arredondamento, valores em falta, rejeição de código arbitrário |
| Avaliação | Médias calculadas na grelha, coerência de todos os resultados semeados com o motor, versões sem efeito retroactivo, recálculo controlado, fecho e reabertura |
| Regras | Transição, transição condicional, disciplinas nucleares, recurso, extraordinário, melhoria, retenção por faltas, progressão automática |
| Isolamento | Escola ↔ escola, município ↔ município, professor ↔ atribuições, 404 em registos fora do âmbito |
| Permissões | Matriz completa perfil × funcionalidade (6 perfis × 37 funcionalidades) |
| Cadastros | Cadastro, edição, desactivação lógica, duplicações, integridade referencial e jurisdição de cada perfil |
| Fluxo | Sequência de demonstração da secção 21 de ponta a ponta |
| Segurança | XSS, injecção SQL, atribuição em massa, hash de senhas, cabeçalhos, cópias de segurança sem path traversal |
| Administrativo | Chamada, justificativos, frequência, recibos sequenciais, anulação sem eliminação, mensalidades em atraso, declarações imutáveis |

### Pontos a validar antes da utilização real

- Parâmetros regulamentares assinalados "a validar": regras de recurso, extraordinário e melhoria, escala qualitativa do Pré-Escolar, disciplinas com exame combinado e planos curriculares de referência.
- Municípios da província (divisão administrativa em vigor) e dados reais das escolas.
- Preços dos serviços e política de mensalidades por escola.
- Estimativa de aulas previstas para a frequência (12 semanas por trimestre), até existir um módulo de horários.
