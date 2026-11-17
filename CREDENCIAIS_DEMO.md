# Credenciais de demonstração — SIGE Bengo

> **Ambiente de demonstração.** Todas as contas, escolas e restantes dados criados pelos seeders são fictícios
> e não constituem estatísticas oficiais da província do Bengo. **Não utilizar estas contas em produção.**

Endereço: <http://localhost/sg_escolar>

Senha de todas as contas: **`Bengo@2026`**

| Perfil | E-mail | Âmbito |
|---|---|---|
| Superutilizador | `admin@sige.demo.ao` | Global |
| Direcção Provincial | `provincial@sige.demo.ao` | Toda a província (consulta) |
| Direcção Municipal | `municipal.dande@sige.demo.ao` | Município do Dande (consulta) |
| Director / Administrativo | `director@sige.demo.ao` | Complexo Escolar n.º 205 — Mabubas |
| Professor | `professor@sige.demo.ao` | Complexo Escolar n.º 205 e Liceu n.º 310 |
| Secretaria / Tesouraria | `secretaria@sige.demo.ao` | Complexo Escolar n.º 205 — Mabubas |

## Dados académicos demonstrativos

- Ano lectivo actual: **2026/2027** (2025/2026 encerrado)
- 10 escolas com 39 turmas, cerca de 735 alunos com matrícula e turma, e 74 professores com atribuições
- Os nomes e números de identificação são fictícios e são gerados de forma reprodutível

O professor de demonstração (`professor@sige.demo.ao`) lecciona Matemática no Complexo Escolar n.º 205 e no Liceu n.º 310.
Só vê as turmas e os alunos que lhe estão atribuídos.

Os planos curriculares de referência são **simplificados** e devem ser validados face aos planos oficiais.

## Avaliações demonstrativas

- **2025/2026 (encerrado):** notas dos três trimestres e exames, resultados por disciplina e situação final calculados pelo motor de avaliação. Os quatro períodos estão fechados.
  - Alguns alunos ficaram retidos; hoje são repetentes ou desistentes.
- **2026/2027 (em curso):** AC1, AC2 e prova trimestral do I Trimestre lançadas. O período está aberto.

As configurações de avaliação partem do Decreto Executivo n.º 424/25 e são editáveis em *Configuração de Avaliações*, apenas pelo superutilizador.
Estão por validar:
- os parâmetros das regras de transição e de acesso a exames;
- a escala qualitativa do Pré-Escolar.

A configuração "exame combinado" é um exemplo em rascunho.

## Sequência de demonstração sugerida (secção 21 da especificação)

1. Entrar como `provincial@sige.demo.ao`, abrir o *Painel Provincial* e observar os indicadores consolidados.
2. No gráfico "Escolas por tipologia institucional", clicar numa barra para abrir as instituições dessa tipologia.
3. Filtrar o painel por município, nível de ensino ou natureza. Os cartões, os gráficos e a tabela são actualizados.
4. Na tabela "Instituições", consultar os níveis ministrados por cada escola.
5. Abrir o *Mapa da Rede*, que exige ligação à Internet para o fundo cartográfico.
6. Entrar como `director@sige.demo.ao` (ou `secretaria@…`) e cadastrar um aluno com *Guardar e matricular*. Escolher a classe e a turma.
7. Entrar como `professor@sige.demo.ao`, abrir *Lançamento de Notas* › 7.ª Classe A › Matemática e lançar avaliações. As médias são calculadas de imediato.
8. Entrar como `director@sige.demo.ao` e gerar a pauta, em PDF ou Excel, em *Pautas*.
9. Regressar ao painel provincial, onde o aluno novo já conta nos totais do ano em curso.

Os indicadores de **desempenho** usam o último ano com resultados finais (2025/2026), porque o ano em curso ainda não terminou.
A comparação entre anos usa a % de positivas por trimestre.

## Dados administrativos demonstrativos (2026/2027)

- **Serviços e preços por escola:**
  - escolas públicas: inscrição, declarações e cartão;
  - escola público-privada: comparticipação mensal;
  - escola privada: propina, mais elevada no I Ciclo.
- **Recibos:** mais de 1100, com numeração sequencial por escola. Alguns estão anulados com motivo e há mensalidades de Novembro em atraso.
- **Faltas:** cerca de 3000 registadas nas turmas do ano em curso, com justificativos aprovados, pendentes e rejeitados. Em cada turma há um aluno com muitas faltas, para demonstrar o alerta de frequência no *Painel da Escola*.
- **Declarações:** algumas já emitidas, com código de verificação.

Em 2025/2026 não foram registadas faltas, por isso a frequência desse ano aparece como "não verificada", e não como 100%.

## Demonstração complementar: gestão administrativa

1. Como `professor@sige.demo.ao`, abrir *Faltas* › Registar faltas: escolher a turma, a disciplina e a data, e marcar os ausentes.
2. Como `director@sige.demo.ao`, abrir *Faltas* e filtrar por "Justificativo pendente". Analisar (aprovar ou rejeitar): a frequência do aluno é recalculada.
3. Como `secretaria@sige.demo.ao`, abrir *Pagamentos* › Registar pagamento, escolher o aluno, os meses e a forma de pagamento, e descarregar o recibo em PDF.
4. Como `admin@sige.demo.ao`, entrar no *Colégio Privado Horizonte* e abrir *Pagamentos* › Em atraso para ver os alunos e os valores em dívida.
5. Abrir *Declarações* › Emitir, escolher um aluno e o tipo (matrícula, frequência ou notas) e descarregar o PDF.

## Demonstração complementar: avaliação

1. Entrar como `professor@sige.demo.ao` e abrir *Lançamento de Notas* › 7.ª Classe A › Matemática.
2. Alterar notas e gravar: o MAC e o MT são recalculados de imediato.
3. Entrar como `director@sige.demo.ao` e abrir *Pautas*, escolhendo o ano 2025/2026 › 9.ª Classe A › Pauta final.
4. Exportar a pauta em PDF ou Excel.
5. Abrir a ficha de um aluno para ver o boletim individual com a justificação do resultado.
6. Entrar como `admin@sige.demo.ao` e abrir *Configuração de Avaliações*. Editar uma configuração e testar o cálculo na pré-visualização antes de guardar.

## Demonstração complementar: Cadastros e Configurações

1. Como `provincial@sige.demo.ao`, abrir *Cadastros e Configurações › Municípios* (escolas e alunos activos por município) e criar um município.
2. Em *Escolas*, cadastrar uma escola com município, código, natureza, tipologia, níveis e coordenadas; a seguir, desactivá-la e confirmar que sai do *Painel Provincial*.
3. Em *Disciplinas*, criar uma disciplina de referência e associá-la a níveis de ensino; abrir *Classes e Ciclos* › 7.ª Classe para ver e configurar as disciplinas da classe (obrigatórias ou opcionais).
4. Em *Anos Lectivos* e *Períodos de Avaliação*, consultar o ano activo e ajustar as datas dos trimestres (as datas fora do ano ou sobrepostas são rejeitadas).
5. Como `municipal.dande@sige.demo.ao`, cadastrar uma escola no Dande: as escolas de outros municípios não aparecem e o município não pode ser alterado.

## Recuperação de senha

No MVP, o envio de e-mail está configurado com `MAIL_MAILER=log`. A ligação de recuperação fica registada em
`storage/logs/laravel.log`. Para enviar e-mails reais, configure as variáveis `MAIL_*` no ficheiro `.env`.

## Antes da apresentação — lista de verificação

1. **Repor os dados demonstrativos** (cerca de 1 minuto; apaga todas as alterações feitas em ensaios):
   ```bash
   php artisan migrate:fresh --seed
   ```
2. Confirmar que o Apache e o MySQL do XAMPP estão activos e abrir <http://localhost/sg_escolar>.
3. Ligar à Internet, apenas para o fundo do mapa. Todo o resto funciona sem ligação.
4. Entrar uma vez com cada conta da tabela acima. As 6 contas usam a mesma senha.
5. Abrir uma janela de navegação privada por perfil, ou terminar a sessão entre perfis.
6. Para ecrãs pequenos ou projectores, o menu lateral pode ser recolhido pelo botão ☰ do cabeçalho.

O sistema identifica-se como **ambiente de demonstração** por três sinais:
- uma faixa amarela no topo;
- uma marca nos PDF;
- dados fictícios.

Para retirar estas marcas numa instalação real, definir `SIGE_MODO_DEMONSTRACAO=false` no `.env`.
