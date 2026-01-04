# Cleanwatts Portal

## Descrição

O **Cleanwatts Portal** é uma plataforma web robusta concebida para a gestão integral do ciclo de vida de instalações fotovoltaicas (PV). A aplicação facilita o comissionamento, inspeções de site (Site Surveys), supervisão de campo e gestão de pendências (Punch Lists), servindo como um repositório central para documentação técnica e operacional.

## Funcionalidades Principais

### 1. Gestão de Comissionamento

- **Formulários Dinâmicos**: Registo detalhado de equipamentos (inversores, módulos, contadores).
- **Medições Elétricas**: Introdução de dados de strings, isolamento e resistência de terra.
- **Sistema de Autosave**: Proteção contra perda de dados através de gravação automática via AJAX.
- **Geração de Relatórios**: Exportação para PDF com suporte para assinaturas e fotos.

### 2. Site Survey (Inspeção de Site)

- **Avaliação Pré-Instalação**: Checklists detalhadas para avaliação técnica do local.
- **Gestão de Rascunhos**: Possibilidade de guardar e retomar inspeções incompletas.
- **Geolocalização e Fotos**: Suporte para registo de coordenadas e evidências fotográficas.

### 3. Supervisão de Campo e Punch Lists

- **Controlo de Pendências**: Registo de itens em falta ou defeitos com níveis de severidade.
- **Timeline de Atividade**: Histórico completo de intervenções e alterações por projeto.
- **Exportação Avançada**: Geração de PDFs de Punch Lists utilizando Puppeteer para alta fidelidade visual.

### 4. Administração e Segurança

- **Controlo de Acessos (RBAC)**: Níveis de permissão para Admin, Supervisor, Técnico e Operador.
- **Sistema de Auditoria**: Registo detalhado de todas as ações críticas realizadas no portal.
- **Gestão de Tabelas de Apoio**: Interface para gestão de marcas, modelos de equipamentos e entidades (EPCs).

## Estrutura do Projeto

```text
cleanwattsportal/
├── ajax/                     # Endpoints para operações dinâmicas (Autosave, CRUD, Dropdowns)
├── assets/                   # Recursos estáticos (CSS, JS com GSAP/jQuery, Imagens)
├── config/                   # Configurações de ambiente e base de dados
├── includes/                 # Lógica partilhada (Autenticação, Auditoria, UI Components)
├── node_scripts/             # Scripts Node.js para geração de PDFs via Puppeteer
├── reports/                  # Repositório de relatórios gerados (PDF/HTML)
├── sql_migrations/           # Histórico de evolução da base de dados (SQL/PHP)
├── tools/                    # Ferramentas de manutenção, debug e scripts utilitários
├── vendor/                   # Dependências PHP (Dompdf, etc.)
└── [ficheiros_raiz].php      # Módulos principais (comissionamento, site_survey, login)
```

## Tecnologias Utilizadas

- **Backend**: PHP 7.4+ (Arquitetura procedural com separação de lógica AJAX)
- **Base de Dados**: MySQL 5.7+ / MariaDB
- **Frontend**:
  - Bootstrap 5 (Layout)
  - jQuery (Manipulação DOM e AJAX)
  - GSAP (Animações de interface)
  - Toast UI Calendar (Gestão de agendamentos)
- **Geração de PDF**:
  - **Dompdf**: Para relatórios standard em PHP.
  - **Puppeteer (Node.js)**: Para relatórios complexos que requerem renderização de JavaScript.

## Instalação e Configuração

### Pré-requisitos

- Servidor Web (Apache recomendado via XAMPP)
- PHP 7.4 ou superior
- MySQL/MariaDB
- Node.js (necessário para exportação de Punch Lists)

### Passos de Configuração

1. **Clonar o Repositório**: Colocar os ficheiros na diretoria do servidor web.
2. **Base de Dados**:
   - Criar uma base de dados chamada `cleanwattsportal`.
   - Executar `setup_database.php` via browser para inicializar a estrutura.
   - As migrações adicionais podem ser aplicadas via `run_migrations.php`.
3. **Configuração de Ambiente**:
   - O ficheiro `config/database.php` gere as ligações.
   - Para produção, criar um ficheiro `.production` na pasta `config/` e configurar `production.php`.
4. **Utilizadores Iniciais**:
   - Executar `create_users_table.php` para criar a tabela de utilizadores e adicionar as contas iniciais.
5. **Dependências Node.js**:
   - Navegar até à pasta onde se encontram os scripts `.js` e instalar o Puppeteer:

     ```bash
     npm install puppeteer
     ```

## Manutenção e Ferramentas

O projeto inclui uma vasta gama de ferramentas na pasta `tools/` para:

- Corrigir problemas de `AUTO_INCREMENT`.
- Testar a geração de PDFs.
- Encriptação de credenciais.
- Limpeza de logs e timelines.

## Segurança

- **Sessões**: Gestão de sessões com timeout configurável.
- **Auditoria**: Todas as alterações em relatórios e projetos são registadas na tabela de auditoria.
- **Soft Delete**: Relatórios e surveys utilizam eliminação lógica, permitindo a recuperação em caso de erro.

3. Fazer login com credenciais válidas.

### Módulos Principais

- **Dashboard Principal**: Visão geral com acesso aos módulos e calendário de agendamentos.
- **Comissionamento**: Criar/editar relatórios de comissionamento com dados técnicos detalhados.
- **Inspeção de Site**: Avaliar localizações para instalações PV.
- **Supervisão de Campo**: Registar atividades de campo e auditorias.
- **Administração**: Gestão de utilizadores, manutenção de BD, etc.

### Geração de Relatórios

- Relatórios podem ser guardados como rascunhos (autosave automático).
- Exportação para PDF através de `server_generate_pdf.php` ou similares.
- Visualização em tempo real com JavaScript.

## Desenvolvimento

### Estrutura da Base de Dados

A aplicação utiliza múltiplas tabelas para gerir:

- Equipamentos (módulos PV, inversores, cabos, medidores, etc.)
- Empresas EPC e representantes
- Relatórios de comissionamento e inspeções
- Utilizadores e roles
- Agendamentos e atividades

Ver `setup_database.php` para esquema completo.

### AJAX e JavaScript

- Scripts em `ajax/` para operações assíncronas.
- Autosave implementado para formulários longos.
- Validação frontend com JavaScript.

### Segurança

- Autenticação obrigatória para todas as páginas.
- Sanitização de inputs.
- Logs de auditoria em `includes/audit.php`.

## Contribuição

Para contribuir:

1. Seguir padrões de codificação PHP (PSR-12 recomendado).
2. Testar alterações em ambiente local.
3. Documentar novas funcionalidades.
4. Usar migrações SQL para alterações na BD.

## Developer Notes / Recent Changes (Dec 2025)

Small operational notes for developers and maintainers following recent updates:

- **AUTO_INCREMENT fixes:** Several tables were found without `AUTO_INCREMENT` on their `id` columns. Migration scripts have been added to `sql_migrations/` to fix these and `tools/fix_auto_increment_all.php` is available for local repair. If you encounter `Duplicate entry '0' for key 'PRIMARY'` errors, run the migration or the tool to repair IDs and add AUTO_INCREMENT.

- **Site Survey enhancements:**
  - Added a Photos Repository field (`photos_link`) saved into `site_survey_items` (item_type = 'Survey - Photos Link').
  - Replaced blocking `alert()` notifications with non-blocking toasts (`showNotice`) to avoid interrupting users when autosave or manual save runs.
  - Generator controls now disable/clear `Mode` and `Feeds` radios when "Generator present?" is set to "No".
  - An **Editing Survey** banner appears when editing an existing survey (shows SUR-xxxxx, project and date), matching the commissioning banner style.

- **Testing utilities:** Automated test scripts added under `tools/` to create full test reports for Site Survey and Commissioning:
  - `tools/test_create_report.php` (site survey minimal)
  - `tools/test_create_full_report.php` (site survey full payload)
  - `tools/test_create_commissioning_full.php` (commissioning full payload + fetch generated HTML)

- **PDF generation:** The project supports two approaches for PDF generation:
  - **PHP (Dompdf)**: Use `server_generate_pdf.php` after installing Dompdf with Composer (`composer require dompdf/dompdf`).
  - **Headless (Puppeteer)**: Use `server_generate_survey_pdf_headless.php` which calls `node_scripts/render_survey_pdf.js`. Requires Node.js and Puppeteer (`npm i puppeteer`).

- **Health checks & fixes:** Added `tools/check_auto_increment.php` to detect tables missing AUTO_INCREMENT and `sql_migrations/db_migrate_fix_missing_auto_increment.sql` for manual application.

If you'd like, I can add a small startup check that logs warnings when critical tables lack `AUTO_INCREMENT` or when Dompdf/Node are missing.

## Suporte e Manutenção

- Ver documentação em `documentation/` para guias detalhados.
- Scripts de backup e restauração disponíveis.
- Logs de erro em `logs/` (se configurado).

## Licença

[Especificar licença se aplicável]

---

**Desenvolvido por Cleanwatts** - Portal de Gestão de Instalações Fotovoltaicas</content>
<parameter name="filePath">c:\xampp\htdocs\cleanwattsportal\README.md
