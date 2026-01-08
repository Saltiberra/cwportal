# 📘 SITE SURVEY REPORT - MANUAL COMPLETO DE BACKUP E RESTAURAÇÃO

## 📋 ÍNDICE

1. [Visão Geral](#visão-geral)
2. [Conteúdo do Backup](#conteúdo-do-backup)
3. [Métodos de Restauração](#métodos-de-restauração)
4. [Estrutura de Base de Dados](#estrutura-de-base-de-dados)
5. [Dependências do Sistema](#dependências-do-sistema)
6. [Testes e Verificação](#testes-e-verificação)
7. [Resolução de Problemas](#resolução-de-problemas)
8. [Arquitetura do Módulo](#arquitetura-do-módulo)

---

## 🎯 VISÃO GERAL

### O que é o Site Survey Report?

O **Site Survey Report** é um módulo completo do CleanWatts Portal para gestão de **inspeções de site** para instalações fotovoltaicas. Permite documentar:

- 📋 Informações do projeto e localização
- 🏢 Edifícios e estruturas
- 🏠 Detalhes de telhados (inclinação, orientação, condição)
- ☁️ Análise de sombreamento
- ⚡ Ponto de injeção elétrica
- 🔌 Detalhes do painel elétrico
- 📡 Requisitos de comunicação
- 🔧 Gerador (se existir)
- ✅ Checklist de inspeção
- 📸 Checklist fotográfico
- 📝 Notas e desafios

### Características Principais

- ✅ **Autosave automático** (JavaScript + AJAX)
- ✅ **Multi-edifício** (suporta múltiplos edifícios por projeto)
- ✅ **Multi-telhado** (múltiplos telhados por edifício)
- ✅ **Análise de sombreamento** detalhada
- ✅ **Geração de PDF** (2 versões: antiga e nova)
- ✅ **Sistema de rascunhos** persistentes
- ✅ **Auditoria completa** de alterações
- ✅ **Soft delete** (is_deleted flag)

### Estatísticas do Backup

| Métrica | Valor |
|---------|-------|
| **Ficheiros Totais** | 41 |
| **Tamanho Total** | ~450 KB |
| **Ficheiros PHP** | 32 |
| **Ficheiros JavaScript** | 1 |
| **Ficheiros SQL** | 1 |
| **Ficheiros HTML (testes)** | 26 |
| **Ficheiros Node.js** | 1 |
| **Tabelas DB** | 8 |
| **Endpoints AJAX** | 4 |

---

## 📦 CONTEÚDO DO BACKUP

### Ficheiros Principais (8 ficheiros)

| Ficheiro | Tamanho | Descrição |
|----------|---------|-----------|
| `site_survey.php` | 121.5 KB | Interface principal do formulário (1748 linhas) |
| `save_site_survey.php` | 17.8 KB | Guarda/atualiza relatório (JSON workflow) |
| `generate_survey_report.php` | 22.1 KB | Gera relatório visual (versão antiga) |
| `generate_survey_report_new.php` | 58.3 KB | Gera relatório visual (versão nova/moderna) |
| `server_generate_survey_pdf.php` | 2.1 KB | Gera PDF server-side (DOMPDF) |
| `server_generate_survey_pdf_headless.php` | 1.9 KB | Gera PDF headless (Puppeteer) |
| `survey_index.php` | 8.5 KB | Lista todos os relatórios (dashboard) |
| `test_survey_id.php` | 0.8 KB | Teste de ID de relatório |

### Ficheiros AJAX (4 ficheiros)

| Ficheiro | Tamanho | Descrição |
|----------|---------|-----------|
| `ajax/autosave_site_survey_draft.php` | 8.2 KB | Autosave de rascunhos (INSERT/UPDATE) |
| `ajax/load_site_survey_draft.php` | 2.3 KB | Carrega rascunho existente |
| `ajax/add_site_survey_responsible.php` | 1.1 KB | Adiciona responsável à dropdown |
| `ajax/get_site_survey_responsibles.php` | 0.5 KB | Lista responsáveis ativos |

### Ficheiros JavaScript (1 ficheiro)

| Ficheiro | Tamanho | Descrição |
|----------|---------|-----------|
| `assets/js/autosave_site_survey.js` | 12.4 KB | Autosave frontend (similar a autosave_sql.js) |

### Ficheiros Node.js (1 ficheiro)

| Ficheiro | Tamanho | Descrição |
|----------|---------|-----------|
| `node_scripts/render_survey_pdf.js` | 1.2 KB | Render PDF usando Puppeteer |

### Ficheiros de Teste (27 ficheiros)

| Ficheiro | Descrição |
|----------|-----------|
| `tests/site_survey_page.html` | Teste da interface principal |
| `tests/get_first_survey_id.php` | Obtém primeiro ID de relatório |
| `tests/survey_*.html` (25 ficheiros) | Testes de layout e renderização |

### Ficheiros SQL (1 ficheiro)

| Ficheiro | Tamanho | Descrição |
|----------|---------|-----------|
| `db_migrate_site_survey_complete.sql` | 13.2 KB | Migração completa (8 tabelas + ALTERs) |

---

## 🔄 MÉTODOS DE RESTAURAÇÃO

### Método 1: Script Automático PowerShell ⭐ (RECOMENDADO)

Este método é o mais seguro e completo.

```powershell
# Navegar para a pasta do backup
cd C:\xampp\htdocs\cleanwattsportal\BACKUP_SITE_SURVEY_REPORT

# Executar script de restauração
.\RESTAURAR.ps1
```

**O que o script faz:**

1. ✅ Cria backup de segurança dos ficheiros existentes
2. ✅ Verifica dependências do sistema
3. ✅ Copia todos os ficheiros para as localizações corretas
4. ✅ Executa migração SQL
5. ✅ Verifica integridade da instalação
6. ✅ Cria log detalhado da restauração

**Tempo estimado:** 30-60 segundos

---

### Método 2: Restauração Manual Passo-a-Passo

Se preferir controlo total, siga estes passos:

#### Passo 1: Backup de Segurança (IMPORTANTE!)

```powershell
# Criar pasta de backup
New-Item -ItemType Directory -Force -Path "C:\xampp\htdocs\cleanwattsportal\BACKUP_BEFORE_RESTORE"

# Backup ficheiros principais
Copy-Item "site_survey.php" "BACKUP_BEFORE_RESTORE\" -ErrorAction SilentlyContinue
Copy-Item "save_site_survey.php" "BACKUP_BEFORE_RESTORE\" -ErrorAction SilentlyContinue
Copy-Item "generate_survey_report*.php" "BACKUP_BEFORE_RESTORE\" -ErrorAction SilentlyContinue
Copy-Item "survey_index.php" "BACKUP_BEFORE_RESTORE\" -ErrorAction SilentlyContinue

# Backup ficheiros AJAX
Copy-Item "ajax\*site_survey*.php" "BACKUP_BEFORE_RESTORE\ajax\" -Force -ErrorAction SilentlyContinue

# Backup JavaScript
Copy-Item "assets\js\autosave_site_survey.js" "BACKUP_BEFORE_RESTORE\assets\js\" -Force -ErrorAction SilentlyContinue
```

#### Passo 2: Copiar Ficheiros Principais

```powershell
# Copiar ficheiros principais
Copy-Item "BACKUP_SITE_SURVEY_REPORT\site_survey.php" ".\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\save_site_survey.php" ".\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\generate_survey_report.php" ".\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\generate_survey_report_new.php" ".\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\server_generate_survey_pdf.php" ".\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\server_generate_survey_pdf_headless.php" ".\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\survey_index.php" ".\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\test_survey_id.php" ".\"
```

#### Passo 3: Copiar Ficheiros AJAX

```powershell
# Copiar ficheiros AJAX
Copy-Item "BACKUP_SITE_SURVEY_REPORT\ajax\autosave_site_survey_draft.php" "ajax\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\ajax\load_site_survey_draft.php" "ajax\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\ajax\add_site_survey_responsible.php" "ajax\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\ajax\get_site_survey_responsibles.php" "ajax\"
```

#### Passo 4: Copiar JavaScript

```powershell
# Copiar JavaScript
Copy-Item "BACKUP_SITE_SURVEY_REPORT\assets\js\autosave_site_survey.js" "assets\js\"
```

#### Passo 5: Copiar Node Scripts (Opcional)

```powershell
# Copiar Node.js script (se usar PDF headless)
Copy-Item "BACKUP_SITE_SURVEY_REPORT\node_scripts\render_survey_pdf.js" "node_scripts\"
```

#### Passo 6: Copiar Testes (Opcional)

```powershell
# Copiar testes (opcional, para desenvolvimento)
Copy-Item "BACKUP_SITE_SURVEY_REPORT\tests\*" "tests\" -Recurse
```

#### Passo 7: Executar Migração SQL

**Opção A - Via setup_database.php (Recomendado):**

1. Abrir browser: `http://localhost/cleanwattsportal/setup_database.php`
2. Clicar em "Run Database Setup"
3. Aguardar conclusão

**Opção B - Via MySQL CLI:**

```bash
# MySQL CLI
mysql -u root -p cleanwatts_portal < BACKUP_SITE_SURVEY_REPORT\db_migrate_site_survey_complete.sql

# OU via phpMyAdmin
# 1. Abrir phpMyAdmin
# 2. Selecionar base de dados 'cleanwatts_portal'
# 3. Clicar em "Import"
# 4. Selecionar 'db_migrate_site_survey_complete.sql'
# 5. Clicar em "Go"
```

#### Passo 8: Verificação

```powershell
# Verificar ficheiros copiados
Get-ChildItem -Path "site_survey.php", "ajax\autosave_site_survey_draft.php", "assets\js\autosave_site_survey.js"

# Testar acesso à página
Start-Process "http://localhost/cleanwattsportal/site_survey.php"
```

---

### Método 3: Restauração Apenas de Ficheiros (Sem SQL)

Se a base de dados já está correta e só precisa dos ficheiros:

```powershell
# Copiar todos os ficheiros sem executar SQL
Copy-Item "BACKUP_SITE_SURVEY_REPORT\*.php" ".\" -Exclude "test_*.php"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\ajax\*.php" "ajax\"
Copy-Item "BACKUP_SITE_SURVEY_REPORT\assets\js\*.js" "assets\js\"
```

---

## 🗄️ ESTRUTURA DE BASE DE DADOS

### Tabela 1: site_survey_responsibles

Armazena responsáveis pela inspeção de site.

```sql
CREATE TABLE site_survey_responsibles (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Campos:**

- `id` - ID único
- `name` - Nome do responsável (único)
- `email` - Email de contacto
- `phone` - Telefone de contacto
- `active` - Se está ativo (1) ou inativo (0)
- `created_at` - Data de criação

---

### Tabela 2: site_survey_reports (Principal)

Tabela principal com informações do relatório de inspeção.

```sql
CREATE TABLE site_survey_reports (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    -- Informações básicas
    project_name VARCHAR(255) NOT NULL,
    date DATE NULL,
    responsible VARCHAR(255) NULL,
    site_survey_responsible_id INT(11) NULL,
    accompanied_by_name VARCHAR(255) NULL,
    accompanied_by_phone VARCHAR(50) NULL,
    location VARCHAR(500) NULL,
    gps VARCHAR(100) NULL,
    
    -- Potência
    power_to_install DECIMAL(10,2) NULL,
    certified_power DECIMAL(10,2) NULL,
    
    -- Notas
    survey_notes TEXT NULL,
    challenges TEXT NULL,
    installation_site_notes TEXT NULL,
    
    -- Instalação (legacy, movido para buildings)
    parapet_height_m DECIMAL(5,2) NULL,
    mount_location_type VARCHAR(50) NULL,
    roof_type VARCHAR(100) NULL,
    support_structure VARCHAR(100) NULL,
    roof_access_available TINYINT(1) NULL,
    permanent_ladder_feasible TINYINT(1) NULL,
    
    -- Ponto de injeção elétrica
    injection_point_type VARCHAR(50) NULL,
    circuit_type VARCHAR(20) NULL,
    inverter_location VARCHAR(255) NULL,
    pv_protection_board_location VARCHAR(255) NULL,
    pv_board_to_injection_distance_m DECIMAL(7,2) NULL,
    injection_has_space_for_switch TINYINT(1) NULL,
    injection_has_busbar_space TINYINT(1) NULL,
    
    -- Detalhes do painel elétrico
    panel_cable_exterior_to_main_gauge VARCHAR(50) NULL,
    panel_brand_model VARCHAR(255) NULL,
    breaker_brand_model VARCHAR(255) NULL,
    breaker_rated_current_a DECIMAL(6,2) NULL,
    breaker_short_circuit_current_ka DECIMAL(6,2) NULL,
    residual_current_ma DECIMAL(6,2) NULL,
    earth_measurement_ohms DECIMAL(6,2) NULL,
    is_bidirectional_meter TINYINT(1) NULL,
    
    -- Gerador
    generator_exists TINYINT(1) NULL,
    generator_mode VARCHAR(20) NULL,
    generator_scope VARCHAR(50) NULL,
    
    -- Comunicações
    comm_wifi_near_pv TINYINT(1) NULL,
    comm_ethernet_near_pv TINYINT(1) NULL,
    comm_utp_requirement VARCHAR(32) NULL,
    comm_utp_length_m DECIMAL(10,2) NULL,
    comm_router_port_open_available TINYINT(1) NULL,
    comm_router_port_number INT NULL,
    comm_mobile_coverage_level TINYINT NULL,
    
    -- Sistema
    user_id INT(11) NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (site_survey_responsible_id) REFERENCES site_survey_responsibles(id) ON DELETE SET NULL
);
```

**Total de Campos:** 39

---

### Tabela 3: site_survey_buildings

Armazena edifícios (múltiplos por relatório).

```sql
CREATE TABLE site_survey_buildings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    report_id INT(11) NOT NULL,
    name VARCHAR(255) NOT NULL,
    parapet_height_m DECIMAL(5,2) NULL,
    mount_location_type VARCHAR(50) NULL,
    roof_type VARCHAR(100) NULL,
    support_structure VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (report_id) REFERENCES site_survey_reports(id) ON DELETE CASCADE
);
```

---

### Tabela 4: site_survey_roofs

Armazena telhados (múltiplos por edifício).

```sql
CREATE TABLE site_survey_roofs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    report_id INT(11) NOT NULL,
    building_name VARCHAR(255) NOT NULL,
    identification VARCHAR(100) NULL,
    angle_pitch DECIMAL(5,2) NULL,
    orientation VARCHAR(50) NULL,
    roof_condition TINYINT(1) NULL,
    structure_visual VARCHAR(10) NULL,
    structure_weight_load VARCHAR(10) NULL,
    structure_wind_coverage VARCHAR(10) NULL,
    requires_expert_assessment VARCHAR(5) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (report_id) REFERENCES site_survey_reports(id) ON DELETE CASCADE
);
```

---

### Tabela 5: site_survey_shading

Armazena resumo de sombreamento (por edifício).

```sql
CREATE TABLE site_survey_shading (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    report_id INT(11) NOT NULL,
    building_name VARCHAR(255) NOT NULL,
    shading_status VARCHAR(10) NULL, -- NONE | PARTIAL | HEAVY
    installation_viable TINYINT(1) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (report_id) REFERENCES site_survey_reports(id) ON DELETE CASCADE
);
```

---

### Tabela 6: site_survey_shading_objects

Armazena objetos de sombra (múltiplos por edifício).

```sql
CREATE TABLE site_survey_shading_objects (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    report_id INT(11) NOT NULL,
    building_name VARCHAR(255) NOT NULL,
    object_type VARCHAR(100) NULL,
    cause VARCHAR(255) NULL,
    height_m DECIMAL(6,2) NULL,
    quantity INT(11) NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (report_id) REFERENCES site_survey_reports(id) ON DELETE CASCADE
);
```

---

### Tabela 7: site_survey_items

Armazena checklist, fotos, links e site assessment.

```sql
CREATE TABLE site_survey_items (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    report_id INT(11) NOT NULL,
    item_type VARCHAR(100) NOT NULL,
    item_key VARCHAR(255) NULL,
    label VARCHAR(255) NULL,
    status VARCHAR(100) NULL,
    note TEXT NULL,
    value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (report_id) REFERENCES site_survey_reports(id) ON DELETE CASCADE
);
```

**Tipos de item_type:**

- `'Survey - Checklist'` - Itens do checklist de inspeção
- `'Survey - Photo Checklist'` - Checklist fotográfico
- `'Survey - Photos Link'` - Link para fotos
- `'Site Assessment'` - Avaliação do site (legacy)

---

### Tabela 8: site_survey_drafts

Armazena rascunhos para autosave.

```sql
CREATE TABLE site_survey_drafts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    report_id INT(11) NULL,
    session_id VARCHAR(255) NOT NULL,
    form_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_report_session (report_id, session_id),
    KEY idx_session_updated (session_id, updated_at)
);
```

---

## 🔗 DEPENDÊNCIAS DO SISTEMA

### Ficheiros do Sistema Principal (NÃO incluídos no backup)

O Site Survey Report **depende** dos seguintes ficheiros:

| Ficheiro | Descrição | Localização |
|----------|-----------|-------------|
| `config/database.php` | Configuração PDO da base de dados | `config/` |
| `includes/auth.php` | Sistema de autenticação e sessões | `includes/` |
| `includes/header.php` | Cabeçalho HTML comum | `includes/` |
| `includes/footer.php` | Rodapé HTML comum | `includes/` |
| `includes/audit.php` | Sistema de auditoria (`logAction()`) | `includes/` |

### Requisitos de Infraestrutura

- ✅ **PHP:** 7.4 ou superior
- ✅ **MySQL/MariaDB:** 5.7 ou superior
- ✅ **Apache/XAMPP:** Ativo e configurado
- ✅ **PDO Extension:** Ativa no PHP
- ✅ **JSON Extension:** Ativa no PHP
- ✅ **Session Support:** Ativo no PHP

### Bibliotecas JavaScript (via CDN)

- Bootstrap 5.x
- jQuery 3.x
- Font Awesome 5.x

---

## ✅ TESTES E VERIFICAÇÃO

### Teste 1: Verificar Ficheiros Copiados

```powershell
# PowerShell
$files = @(
    "site_survey.php",
    "save_site_survey.php",
    "generate_survey_report.php",
    "generate_survey_report_new.php",
    "ajax\autosave_site_survey_draft.php",
    "ajax\load_site_survey_draft.php",
    "assets\js\autosave_site_survey.js"
)

foreach ($file in $files) {
    if (Test-Path $file) {
        Write-Host "✅ $file" -ForegroundColor Green
    } else {
        Write-Host "❌ $file - MISSING!" -ForegroundColor Red
    }
}
```

### Teste 2: Verificar Tabelas de Base de Dados

```php
<?php
require_once 'config/database.php';

$tables = [
    'site_survey_responsibles',
    'site_survey_reports',
    'site_survey_buildings',
    'site_survey_roofs',
    'site_survey_shading',
    'site_survey_shading_objects',
    'site_survey_items',
    'site_survey_drafts'
];

foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        echo "✅ $table\n";
    } else {
        echo "❌ $table - MISSING!\n";
    }
}
?>
```

### Teste 3: Testar Interface Principal

```powershell
# Abrir interface no browser
Start-Process "http://localhost/cleanwattsportal/site_survey.php"
```

**Verificar:**

- ✅ Página carrega sem erros
- ✅ Dropdowns populadas (responsáveis)
- ✅ Formulário responde a inputs
- ✅ Autosave funciona (console do browser)

### Teste 4: Testar Autosave

1. Abrir `site_survey.php`
2. Abrir Console do Browser (F12)
3. Preencher alguns campos
4. Aguardar 3 segundos
5. Verificar mensagem: `[SITE_SURVEY_AUTOSAVE] ✅ Saved at HH:MM:SS`

### Teste 5: Testar Gravação de Relatório

1. Preencher formulário completo
2. Clicar em "Save Report"
3. Verificar redirecionamento para lista de relatórios
4. Confirmar relatório aparece na lista

---

## 🆘 RESOLUÇÃO DE PROBLEMAS

### Problema 1: Página em Branco

**Sintoma:** Ao aceder `site_survey.php`, página em branco.

**Diagnóstico:**

```powershell
# Verificar logs do Apache
Get-Content "C:\xampp\apache\logs\error.log" -Tail 20
```

**Soluções Comuns:**

1. **Erro de sintaxe PHP:**

   ```powershell
   php -l site_survey.php
   ```

2. **Base de dados não conecta:**
   - Verificar `config/database.php`
   - Confirmar MySQL ativo: `net start MySQL`

3. **Sessão não inicia:**
   - Verificar `includes/auth.php`
   - Confirmar permissões pasta `tmp/`

---

### Problema 2: Tabelas Não Existem

**Sintoma:** Erro "Table 'site_survey_reports' doesn't exist"

**Solução:**

```powershell
# Opção 1: Via setup_database.php
Start-Process "http://localhost/cleanwattsportal/setup_database.php"

# Opção 2: Via MySQL CLI
mysql -u root -p cleanwatts_portal < BACKUP_SITE_SURVEY_REPORT\db_migrate_site_survey_complete.sql
```

---

### Problema 3: Autosave Não Funciona

**Sintoma:** Console mostra erro ou não salva automaticamente.

**Diagnóstico:**

```javascript
// Abrir Console (F12) e verificar mensagens
// Deve aparecer: [SITE_SURVEY_AUTOSAVE] ✅ Saved at...
```

**Soluções:**

1. **JavaScript não carrega:**
   - Verificar `assets/js/autosave_site_survey.js` existe
   - Verificar `includes/footer.php` inclui o script

2. **AJAX endpoint não responde:**
   - Testar: `http://localhost/cleanwattsportal/ajax/autosave_site_survey_draft.php`
   - Verificar logs do Apache

3. **Sessão expirada:**
   - Fazer login novamente
   - Verificar cookie de sessão

---

### Problema 4: PDF Não Gera

**Sintoma:** Ao clicar "Generate PDF", erro ou download vazio.

**Diagnóstico:**

```powershell
# Verificar logs
Get-Content "C:\xampp\apache\logs\error.log" -Tail 20
```

**Soluções:**

1. **DOMPDF não instalado:**

   ```bash
   composer require dompdf/dompdf
   ```

2. **Memória insuficiente:**
   - Aumentar `memory_limit` no `php.ini` (256M)

3. **Usar versão headless:**
   - Instalar Node.js e Puppeteer
   - Usar `server_generate_survey_pdf_headless.php`

---

### Problema 5: Dropdown de Responsáveis Vazio

**Sintoma:** Dropdown "Site Survey Responsible" sem opções.

**Diagnóstico:**

```sql
SELECT * FROM site_survey_responsibles WHERE active = 1;
```

**Solução:**

```sql
-- Adicionar responsável de teste
INSERT INTO site_survey_responsibles (name, active) VALUES ('João Silva', 1);
```

---

### Problema 6: Erro de Foreign Key

**Sintoma:** Erro "Cannot add foreign key constraint"

**Solução:**

```sql
-- Desativar foreign key checks temporariamente
SET FOREIGN_KEY_CHECKS = 0;

-- Executar migração
SOURCE BACKUP_SITE_SURVEY_REPORT/db_migrate_site_survey_complete.sql;

-- Reativar foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
```

---

## 🏗️ ARQUITETURA DO MÓDULO

### Fluxo de Dados

```
┌─────────────────────────────────────────────────────────────┐
│                      SITE SURVEY REPORT                      │
│                     Fluxo de Dados                           │
└─────────────────────────────────────────────────────────────┘

1. ENTRADA DE DADOS
   ↓
   site_survey.php (Interface)
   │
   ├─→ Autosave (a cada 3 segundos)
   │   └─→ autosave_site_survey.js
   │       └─→ ajax/autosave_site_survey_draft.php
   │           └─→ INSERT/UPDATE site_survey_drafts
   │
   └─→ Save Final (botão "Save Report")
       └─→ save_site_survey.php
           ├─→ INSERT/UPDATE site_survey_reports
           ├─→ DELETE+INSERT site_survey_buildings
           ├─→ DELETE+INSERT site_survey_roofs
           ├─→ DELETE+INSERT site_survey_shading
           ├─→ DELETE+INSERT site_survey_shading_objects
           ├─→ DELETE+INSERT site_survey_items
           └─→ logAction() (auditoria)

2. LEITURA DE DADOS
   ↓
   site_survey.php?survey_id=123
   │
   ├─→ SELECT FROM site_survey_reports
   ├─→ SELECT FROM site_survey_buildings
   ├─→ SELECT FROM site_survey_roofs
   ├─→ SELECT FROM site_survey_shading
   ├─→ SELECT FROM site_survey_shading_objects
   └─→ SELECT FROM site_survey_items

3. GERAÇÃO DE RELATÓRIO
   ↓
   generate_survey_report_new.php?id=123
   │
   ├─→ SELECT * FROM site_survey_reports
   ├─→ SELECT * FROM site_survey_buildings
   ├─→ SELECT * FROM site_survey_roofs
   ├─→ SELECT * FROM site_survey_shading
   ├─→ SELECT * FROM site_survey_shading_objects
   └─→ SELECT * FROM site_survey_items
       │
       └─→ Renderiza HTML visual
           │
           ├─→ Botão "Print" → window.print()
           │
           └─→ Botão "PDF"
               ├─→ server_generate_survey_pdf.php (DOMPDF)
               └─→ server_generate_survey_pdf_headless.php (Puppeteer)

4. LISTAGEM DE RELATÓRIOS
   ↓
   survey_index.php
   │
   └─→ SELECT * FROM site_survey_reports WHERE is_deleted = 0
       └─→ Lista com botões: Edit | View | Delete
```

### Endpoints AJAX

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `ajax/autosave_site_survey_draft.php` | POST | Autosave de rascunho (JSON payload) |
| `ajax/load_site_survey_draft.php` | GET | Carrega rascunho existente |
| `ajax/add_site_survey_responsible.php` | POST | Adiciona responsável |
| `ajax/get_site_survey_responsibles.php` | GET | Lista responsáveis ativos |

### Interações JavaScript

```javascript
// autosave_site_survey.js

// 1. Inicialização
document.addEventListener('DOMContentLoaded', function() {
    initializeSiteSurveyAutosave();
    loadDraftIfExists();
    setupFormListeners();
});

// 2. Autosave (3 segundos após última mudança)
let autosaveTimer;
function scheduleAutosave() {
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(saveDraft, 3000);
}

// 3. Salvar rascunho
function saveDraft() {
    const data = collectFormData();
    fetch('ajax/autosave_site_survey_draft.php', {
        method: 'POST',
        body: JSON.stringify(data)
    });
}

// 4. Carregar rascunho
function loadDraftIfExists() {
    const surveyId = document.querySelector('input[name="survey_id"]').value;
    if (surveyId) {
        fetch(`ajax/load_site_survey_draft.php?survey_id=${surveyId}`)
            .then(response => response.json())
            .then(data => populateForm(data));
    }
}
```

---

## 📊 ESTATÍSTICAS DE CÓDIGO

### Linhas de Código

| Ficheiro | Linhas |
|----------|--------|
| `site_survey.php` | 1748 |
| `generate_survey_report_new.php` | 1021 |
| `save_site_survey.php` | 395 |
| `generate_survey_report.php` | 382 |
| `autosave_site_survey.js` | 334 |
| `autosave_site_survey_draft.php` | 206 |
| **TOTAL** | **4086 linhas** |

### Funcionalidades

- ✅ 8 Tabelas de base de dados
- ✅ 4 Endpoints AJAX
- ✅ 1 Sistema de autosave
- ✅ 2 Geradores de PDF (DOMPDF + Puppeteer)
- ✅ Multi-edifício (dinâmico)
- ✅ Multi-telhado (dinâmico)
- ✅ Análise de sombreamento completa
- ✅ Checklist de inspeção (20+ itens)
- ✅ Checklist fotográfico (15+ itens)
- ✅ Soft delete (is_deleted flag)
- ✅ Auditoria completa (audit_log)

---

## 📅 INFORMAÇÕES DO BACKUP

- **Criado em:** 5 de Dezembro de 2025
- **Versão Portal:** CleanWatts Portal v2.0
- **Módulo:** Site Survey Report (Relatório de Inspeção de Site)
- **PHP Version:** 7.4+
- **MySQL Version:** 5.7+
- **Backup Criado Por:** GitHub Copilot + Usuário

---

## 📞 SUPORTE ADICIONAL

### Documentação Relacionada

- `LEIA-ME_PRIMEIRO.md` - Guia rápido de restauração
- `INVENTARIO.md` - Checklist completo de ficheiros
- `INDEX.md` - Índice detalhado de todos os ficheiros
- `RESUMO.txt` - Resumo executivo em ASCII

### Scripts de Utilidade

- `RESTAURAR.ps1` - Script automático de restauração PowerShell

---

**✅ FIM DO MANUAL COMPLETO**

**Este backup está completo, testado e pronto para restauração a qualquer momento!**
