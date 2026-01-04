# 🚨 LEIA-ME PRIMEIRO - SITE SURVEY REPORT BACKUP

## 📌 RESUMO RÁPIDO

Este backup contém **TODOS** os ficheiros do módulo **Site Survey Report** (Relatório de Inspeção de Site).

**Total de Ficheiros:** 41 ficheiros  
**Tamanho Total:** ~450 KB  
**Localização:** `C:\xampp\htdocs\cleanwattsportal\BACKUP_SITE_SURVEY_REPORT\`

---

## ⚡ RESTAURAÇÃO RÁPIDA (3 Métodos)

### Método 1: Script Automático (RECOMENDADO) ⭐

```powershell
cd C:\xampp\htdocs\cleanwattsportal\BACKUP_SITE_SURVEY_REPORT
.\RESTAURAR.ps1
```

### Método 2: Manual Rápido (Copy-Paste)

1. **Copiar ficheiros principais:**

   ```powershell
   Copy-Item BACKUP_SITE_SURVEY_REPORT\*.php C:\xampp\htdocs\cleanwattsportal\
   ```

2. **Copiar AJAX:**

   ```powershell
   Copy-Item BACKUP_SITE_SURVEY_REPORT\ajax\*.php C:\xampp\htdocs\cleanwattsportal\ajax\
   ```

3. **Copiar JavaScript:**

   ```powershell
   Copy-Item BACKUP_SITE_SURVEY_REPORT\assets\js\*.js C:\xampp\htdocs\cleanwattsportal\assets\js\
   ```

4. **Executar migração SQL:**
   - Aceder: `http://localhost/cleanwattsportal/setup_database.php`
   - **OU** importar manualmente: `db_migrate_site_survey_complete.sql`

### Método 3: Apenas Ficheiros (sem SQL)

Se a base de dados já existe, copie apenas os ficheiros PHP/JS sem executar a migração SQL.

---

## 🗂️ ESTRUTURA DO BACKUP

```
BACKUP_SITE_SURVEY_REPORT/
│
├─ 📄 site_survey.php                          (121.5 KB) - Interface principal
├─ 📄 save_site_survey.php                     (17.8 KB)  - Guarda relatório
├─ 📄 generate_survey_report.php               (22.1 KB)  - Gera relatório (OLD)
├─ 📄 generate_survey_report_new.php           (58.3 KB)  - Gera relatório (NEW)
├─ 📄 server_generate_survey_pdf.php           (2.1 KB)   - PDF server-side
├─ 📄 server_generate_survey_pdf_headless.php  (1.9 KB)   - PDF headless
├─ 📄 survey_index.php                         (8.5 KB)   - Lista relatórios
├─ 📄 test_survey_id.php                       (0.8 KB)   - Teste ID
│
├─ ajax/
│  ├─ 📄 autosave_site_survey_draft.php        (8.2 KB)   - Autosave
│  ├─ 📄 load_site_survey_draft.php            (2.3 KB)   - Carrega rascunho
│  ├─ 📄 add_site_survey_responsible.php       (1.1 KB)   - Adiciona responsável
│  └─ 📄 get_site_survey_responsibles.php      (0.5 KB)   - Lista responsáveis
│
├─ assets/js/
│  └─ 📄 autosave_site_survey.js               (12.4 KB)  - Autosave frontend
│
├─ node_scripts/
│  └─ 📄 render_survey_pdf.js                  (1.2 KB)   - Render PDF Node.js
│
├─ tests/
│  ├─ 📄 site_survey_page.html                 - Teste interface
│  ├─ 📄 get_first_survey_id.php               - Obtém primeiro ID
│  └─ 📄 survey_*.html (25 ficheiros)          - Testes de layout
│
├─ 📄 db_migrate_site_survey_complete.sql      (13.2 KB)  - Migração SQL completa
│
└─ documentation/
   ├─ 📄 LEIA-ME_PRIMEIRO.md (este ficheiro)
   ├─ 📄 README_BACKUP.md (manual completo)
   ├─ 📄 INVENTARIO.md (checklist detalhado)
   ├─ 📄 INDEX.md (índice de ficheiros)
   ├─ 📄 RESUMO.txt (resumo executivo)
   └─ 📄 RESTAURAR.ps1 (script automático)
```

---

## ✅ VERIFICAÇÃO RÁPIDA

Executar após restauração:

```powershell
# Verificar ficheiros copiados
Test-Path "site_survey.php"
Test-Path "ajax\autosave_site_survey_draft.php"
Test-Path "assets\js\autosave_site_survey.js"

# Testar acesso à página
Start-Process "http://localhost/cleanwattsportal/site_survey.php"
```

---

## 🔗 DEPENDÊNCIAS

O Site Survey Report **depende** destes ficheiros do sistema principal (NÃO incluídos no backup):

- `config/database.php` - Configuração da base de dados
- `includes/auth.php` - Sistema de autenticação
- `includes/header.php` - Cabeçalho comum
- `includes/footer.php` - Rodapé comum
- `includes/audit.php` - Sistema de auditoria
- Base de dados MySQL/MariaDB ativa

⚠️ **IMPORTANTE:** Certifique-se que estes ficheiros existem antes de restaurar!

---

## 📊 TABELAS DE BASE DE DADOS

O Site Survey Report usa **8 tabelas**:

1. `site_survey_responsibles` - Responsáveis pela inspeção
2. `site_survey_reports` - Relatório principal (39 campos)
3. `site_survey_buildings` - Edifícios (múltiplos por relatório)
4. `site_survey_roofs` - Telhados (múltiplos por edifício)
5. `site_survey_shading` - Sombreamento (por edifício)
6. `site_survey_shading_objects` - Objetos de sombra
7. `site_survey_items` - Checklist/fotos/links
8. `site_survey_drafts` - Rascunhos (autosave)

---

## 🆘 RESOLUÇÃO RÁPIDA DE PROBLEMAS

### Problema: Página em branco

**Solução:** Verificar logs PHP (`C:\xampp\apache\logs\error.log`)

### Problema: "Table doesn't exist"

**Solução:** Executar `db_migrate_site_survey_complete.sql`

### Problema: Autosave não funciona

**Solução:** Verificar `ajax\autosave_site_survey_draft.php` e `assets\js\autosave_site_survey.js`

### Problema: PDF não gera

**Solução:** Verificar `server_generate_survey_pdf.php` ou `server_generate_survey_pdf_headless.php`

---

## 📞 DOCUMENTAÇÃO COMPLETA

Para instruções detalhadas, consultar:

- **Manual Completo:** `README_BACKUP.md`
- **Checklist Detalhado:** `INVENTARIO.md`
- **Índice de Ficheiros:** `INDEX.md`
- **Resumo Executivo:** `RESUMO.txt`

---

## 📅 INFORMAÇÕES DO BACKUP

- **Criado em:** 5 de Dezembro de 2025
- **Versão Portal:** CleanWatts Portal v2.0
- **Módulo:** Site Survey Report
- **Backup Criado Por:** GitHub Copilot + Usuário

---

**✅ BACKUP VERIFICADO E PRONTO PARA RESTAURAÇÃO!**
