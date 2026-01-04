<#
.SYNOPSIS
    Script de Restauração Automática - Site Survey Report Backup

.DESCRIPTION
    Este script restaura automaticamente todos os ficheiros do módulo Site Survey Report
    do backup para a localização principal do CleanWatts Portal.

    Operações realizadas:
    1. Verifica pré-requisitos
    2. Cria backup de segurança dos ficheiros existentes
    3. Copia todos os ficheiros do backup
    4. Executa migração SQL
    5. Verifica integridade da instalação
    6. Gera log detalhado

.NOTES
    Versão:        1.0
    Autor:         CleanWatts Portal Backup System
    Data:          5 de Dezembro de 2025
    Módulo:        Site Survey Report

.EXAMPLE
    .\RESTAURAR.ps1
    
    Executa restauração completa com todas as verificações
#>

# ============================================================================
# CONFIGURAÇÃO
# ============================================================================

$ErrorActionPreference = "Continue"
$VerbosePreference = "Continue"

# Cores para output
$ColorSuccess = "Green"
$ColorWarning = "Yellow"
$ColorError = "Red"
$ColorInfo = "Cyan"
$ColorTitle = "Magenta"

# Caminhos
$BackupRoot = $PSScriptRoot | Split-Path -Parent
$PortalRoot = $BackupRoot | Split-Path -Parent
$LogFile = Join-Path $BackupRoot "RESTAURACAO_$(Get-Date -Format 'yyyyMMdd_HHmmss').log"
$SafetyBackupPath = Join-Path $PortalRoot "BACKUP_BEFORE_RESTORE_$(Get-Date -Format 'yyyyMMdd_HHmmss')"

# ============================================================================
# FUNÇÕES AUXILIARES
# ============================================================================

function Write-Log {
    param(
        [string]$Message,
        [string]$Level = "INFO",
        [string]$Color = "White"
    )
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] [$Level] $Message"
    
    Write-Host $logMessage -ForegroundColor $Color
    Add-Content -Path $LogFile -Value $logMessage
}

function Write-Title {
    param([string]$Title)
    
    Write-Host ""
    Write-Host "=" * 80 -ForegroundColor $ColorTitle
    Write-Host "  $Title" -ForegroundColor $ColorTitle
    Write-Host "=" * 80 -ForegroundColor $ColorTitle
    Write-Host ""
    
    Write-Log -Message $Title -Level "TITLE" -Color $ColorTitle
}

function Write-Success {
    param([string]$Message)
    Write-Log -Message "✅ $Message" -Level "SUCCESS" -Color $ColorSuccess
}

function Write-Warning {
    param([string]$Message)
    Write-Log -Message "⚠️  $Message" -Level "WARNING" -Color $ColorWarning
}

function Write-Error-Custom {
    param([string]$Message)
    Write-Log -Message "❌ $Message" -Level "ERROR" -Color $ColorError
}

function Write-Info {
    param([string]$Message)
    Write-Log -Message "ℹ️  $Message" -Level "INFO" -Color $ColorInfo
}

function Test-Prerequisites {
    Write-Title "VERIFICAÇÃO DE PRÉ-REQUISITOS"
    
    $allOk = $true
    
    # Verificar se XAMPP está rodando
    Write-Info "Verificando XAMPP..."
    $apacheRunning = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
    $mysqlRunning = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
    
    if ($apacheRunning) {
        Write-Success "Apache está rodando"
    }
    else {
        Write-Warning "Apache NÃO está rodando - inicie o XAMPP"
        $allOk = $false
    }
    
    if ($mysqlRunning) {
        Write-Success "MySQL está rodando"
    }
    else {
        Write-Warning "MySQL NÃO está rodando - inicie o XAMPP"
        $allOk = $false
    }
    
    # Verificar pastas
    Write-Info "Verificando estrutura de pastas..."
    
    $requiredFolders = @(
        $PortalRoot,
        (Join-Path $PortalRoot "ajax"),
        (Join-Path $PortalRoot "assets\js"),
        (Join-Path $PortalRoot "config"),
        (Join-Path $PortalRoot "includes")
    )
    
    foreach ($folder in $requiredFolders) {
        if (Test-Path $folder) {
            Write-Success "Pasta existe: $folder"
        }
        else {
            Write-Error-Custom "Pasta NÃO existe: $folder"
            $allOk = $false
        }
    }
    
    # Verificar ficheiros de dependência
    Write-Info "Verificando ficheiros de dependência..."
    
    $requiredFiles = @(
        (Join-Path $PortalRoot "config\database.php"),
        (Join-Path $PortalRoot "includes\auth.php"),
        (Join-Path $PortalRoot "includes\header.php"),
        (Join-Path $PortalRoot "includes\footer.php")
    )
    
    foreach ($file in $requiredFiles) {
        if (Test-Path $file) {
            Write-Success "Ficheiro existe: $(Split-Path $file -Leaf)"
        }
        else {
            Write-Warning "Ficheiro NÃO existe: $file"
        }
    }
    
    return $allOk
}

function Create-SafetyBackup {
    Write-Title "CRIAÇÃO DE BACKUP DE SEGURANÇA"
    
    try {
        New-Item -ItemType Directory -Force -Path $SafetyBackupPath | Out-Null
        Write-Success "Pasta de backup criada: $SafetyBackupPath"
        
        # Backup ficheiros principais
        $filesToBackup = @(
            "site_survey.php",
            "save_site_survey.php",
            "generate_survey_report.php",
            "generate_survey_report_new.php",
            "server_generate_survey_pdf.php",
            "server_generate_survey_pdf_headless.php",
            "survey_index.php",
            "test_survey_id.php"
        )
        
        $backedUpCount = 0
        foreach ($file in $filesToBackup) {
            $sourcePath = Join-Path $PortalRoot $file
            if (Test-Path $sourcePath) {
                Copy-Item $sourcePath $SafetyBackupPath -ErrorAction SilentlyContinue
                $backedUpCount++
            }
        }
        
        # Backup ficheiros AJAX
        $ajaxBackupPath = Join-Path $SafetyBackupPath "ajax"
        New-Item -ItemType Directory -Force -Path $ajaxBackupPath | Out-Null
        
        $ajaxFiles = Get-ChildItem (Join-Path $PortalRoot "ajax\*site_survey*.php") -ErrorAction SilentlyContinue
        foreach ($file in $ajaxFiles) {
            Copy-Item $file.FullName $ajaxBackupPath -ErrorAction SilentlyContinue
            $backedUpCount++
        }
        
        # Backup JavaScript
        $jsBackupPath = Join-Path $SafetyBackupPath "assets\js"
        New-Item -ItemType Directory -Force -Path $jsBackupPath | Out-Null
        
        $jsFile = Join-Path $PortalRoot "assets\js\autosave_site_survey.js"
        if (Test-Path $jsFile) {
            Copy-Item $jsFile $jsBackupPath -ErrorAction SilentlyContinue
            $backedUpCount++
        }
        
        Write-Success "Backup de segurança criado: $backedUpCount ficheiros"
        Write-Info "Localização: $SafetyBackupPath"
        
        return $true
    }
    catch {
        Write-Error-Custom "Erro ao criar backup de segurança: $_"
        return $false
    }
}

function Restore-Files {
    Write-Title "RESTAURAÇÃO DE FICHEIROS"
    
    $restoredCount = 0
    $errorCount = 0
    
    try {
        # Copiar ficheiros principais
        Write-Info "Copiando ficheiros principais..."
        
        $mainFiles = @(
            "site_survey.php",
            "save_site_survey.php",
            "generate_survey_report.php",
            "generate_survey_report_new.php",
            "server_generate_survey_pdf.php",
            "server_generate_survey_pdf_headless.php",
            "survey_index.php",
            "test_survey_id.php"
        )
        
        foreach ($file in $mainFiles) {
            $sourcePath = Join-Path $BackupRoot $file
            $destPath = Join-Path $PortalRoot $file
            
            if (Test-Path $sourcePath) {
                Copy-Item $sourcePath $destPath -Force
                Write-Success "Copiado: $file"
                $restoredCount++
            }
            else {
                Write-Warning "Não encontrado no backup: $file"
                $errorCount++
            }
        }
        
        # Copiar ficheiros AJAX
        Write-Info "Copiando ficheiros AJAX..."
        
        $ajaxFiles = @(
            "autosave_site_survey_draft.php",
            "load_site_survey_draft.php",
            "add_site_survey_responsible.php",
            "get_site_survey_responsibles.php"
        )
        
        foreach ($file in $ajaxFiles) {
            $sourcePath = Join-Path $BackupRoot "ajax\$file"
            $destPath = Join-Path $PortalRoot "ajax\$file"
            
            if (Test-Path $sourcePath) {
                Copy-Item $sourcePath $destPath -Force
                Write-Success "Copiado: ajax\$file"
                $restoredCount++
            }
            else {
                Write-Warning "Não encontrado: ajax\$file"
                $errorCount++
            }
        }
        
        # Copiar JavaScript
        Write-Info "Copiando JavaScript..."
        
        $jsSource = Join-Path $BackupRoot "assets\js\autosave_site_survey.js"
        $jsDest = Join-Path $PortalRoot "assets\js\autosave_site_survey.js"
        
        if (Test-Path $jsSource) {
            Copy-Item $jsSource $jsDest -Force
            Write-Success "Copiado: assets\js\autosave_site_survey.js"
            $restoredCount++
        }
        else {
            Write-Warning "Não encontrado: assets\js\autosave_site_survey.js"
            $errorCount++
        }
        
        # Copiar Node scripts (opcional)
        Write-Info "Copiando Node scripts (opcional)..."
        
        $nodeSource = Join-Path $BackupRoot "node_scripts\render_survey_pdf.js"
        $nodeDest = Join-Path $PortalRoot "node_scripts\render_survey_pdf.js"
        
        if (Test-Path $nodeSource) {
            $nodeScriptsDir = Join-Path $PortalRoot "node_scripts"
            if (-not (Test-Path $nodeScriptsDir)) {
                New-Item -ItemType Directory -Force -Path $nodeScriptsDir | Out-Null
            }
            Copy-Item $nodeSource $nodeDest -Force
            Write-Success "Copiado: node_scripts\render_survey_pdf.js"
            $restoredCount++
        }
        
        Write-Host ""
        Write-Success "FICHEIROS RESTAURADOS: $restoredCount"
        if ($errorCount -gt 0) {
            Write-Warning "ERROS/AVISOS: $errorCount"
        }
        
        return ($errorCount -eq 0)
    }
    catch {
        Write-Error-Custom "Erro durante restauração de ficheiros: $_"
        return $false
    }
}

function Restore-Database {
    Write-Title "RESTAURAÇÃO DE BASE DE DADOS"
    
    Write-Info "Para restaurar a base de dados, escolha uma das opções:"
    Write-Host ""
    Write-Host "  OPÇÃO 1 (Recomendado): Via setup_database.php" -ForegroundColor $ColorInfo
    Write-Host "    1. Abrir: http://localhost/cleanwattsportal/setup_database.php" -ForegroundColor White
    Write-Host "    2. Clicar em 'Run Database Setup'" -ForegroundColor White
    Write-Host ""
    Write-Host "  OPÇÃO 2: Via MySQL CLI" -ForegroundColor $ColorInfo
    Write-Host "    mysql -u root -p cleanwatts_portal < '$BackupRoot\db_migrate_site_survey_complete.sql'" -ForegroundColor White
    Write-Host ""
    Write-Host "  OPÇÃO 3: Via phpMyAdmin" -ForegroundColor $ColorInfo
    Write-Host "    1. Abrir phpMyAdmin" -ForegroundColor White
    Write-Host "    2. Selecionar base de dados 'cleanwatts_portal'" -ForegroundColor White
    Write-Host "    3. Importar: db_migrate_site_survey_complete.sql" -ForegroundColor White
    Write-Host ""
    
    $response = Read-Host "Deseja abrir setup_database.php automaticamente? (S/N)"
    
    if ($response -eq "S" -or $response -eq "s") {
        Start-Process "http://localhost/cleanwattsportal/setup_database.php"
        Write-Success "Página setup_database.php aberta no navegador"
        Write-Info "Aguarde a conclusão antes de continuar..."
        Read-Host "Pressione ENTER após executar o setup..."
        return $true
    }
    else {
        Write-Warning "Base de dados NÃO foi restaurada automaticamente"
        Write-Info "Lembre-se de executar a migração SQL manualmente!"
        return $false
    }
}

function Test-Installation {
    Write-Title "VERIFICAÇÃO DA INSTALAÇÃO"
    
    $allOk = $true
    
    # Verificar ficheiros copiados
    Write-Info "Verificando ficheiros copiados..."
    
    $criticalFiles = @(
        "site_survey.php",
        "save_site_survey.php",
        "ajax\autosave_site_survey_draft.php",
        "assets\js\autosave_site_survey.js"
    )
    
    foreach ($file in $criticalFiles) {
        $filePath = Join-Path $PortalRoot $file
        if (Test-Path $filePath) {
            $fileSize = (Get-Item $filePath).Length
            Write-Success "OK: $file ($([math]::Round($fileSize/1KB, 1)) KB)"
        }
        else {
            Write-Error-Custom "FALTA: $file"
            $allOk = $false
        }
    }
    
    # Verificar acessibilidade da página
    Write-Info "Testando acessibilidade da página..."
    
    try {
        $response = Invoke-WebRequest -Uri "http://localhost/cleanwattsportal/site_survey.php" -Method Head -TimeoutSec 5 -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            Write-Success "Página site_survey.php está acessível (HTTP 200)"
        }
        else {
            Write-Warning "Página retornou HTTP $($response.StatusCode)"
        }
    }
    catch {
        Write-Warning "Não foi possível testar acessibilidade: $_"
    }
    
    Write-Host ""
    if ($allOk) {
        Write-Success "INSTALAÇÃO VERIFICADA COM SUCESSO!"
    }
    else {
        Write-Warning "Instalação tem problemas - verifique os erros acima"
    }
    
    return $allOk
}

function Show-Summary {
    param(
        [bool]$Success,
        [datetime]$StartTime
    )
    
    Write-Title "RESUMO DA RESTAURAÇÃO"
    
    $duration = (Get-Date) - $StartTime
    
    Write-Host ""
    Write-Host "  Data/Hora Início: " -NoNewline
    Write-Host $StartTime.ToString("dd/MM/yyyy HH:mm:ss") -ForegroundColor White
    
    Write-Host "  Data/Hora Fim:    " -NoNewline
    Write-Host (Get-Date).ToString("dd/MM/yyyy HH:mm:ss") -ForegroundColor White
    
    Write-Host "  Duração:          " -NoNewline
    Write-Host "$([math]::Round($duration.TotalSeconds, 1)) segundos" -ForegroundColor White
    
    Write-Host ""
    Write-Host "  Backup Segurança: " -NoNewline
    Write-Host $SafetyBackupPath -ForegroundColor White
    
    Write-Host "  Log Restauração:  " -NoNewline
    Write-Host $LogFile -ForegroundColor White
    
    Write-Host ""
    
    if ($Success) {
        Write-Success "RESTAURAÇÃO CONCLUÍDA COM SUCESSO!"
        Write-Host ""
        Write-Info "Próximos passos:"
        Write-Host "  1. Testar acesso: http://localhost/cleanwattsportal/site_survey.php" -ForegroundColor White
        Write-Host "  2. Verificar autosave (abrir console do browser - F12)" -ForegroundColor White
        Write-Host "  3. Criar um relatório de teste" -ForegroundColor White
        Write-Host ""
    }
    else {
        Write-Warning "RESTAURAÇÃO TEVE PROBLEMAS - Verifique o log!"
        Write-Host ""
        Write-Info "Consultar documentação:"
        Write-Host "  - LEIA-ME_PRIMEIRO.md" -ForegroundColor White
        Write-Host "  - README_BACKUP.md (secção 'Resolução de Problemas')" -ForegroundColor White
        Write-Host ""
    }
}

# ============================================================================
# SCRIPT PRINCIPAL
# ============================================================================

function Main {
    $startTime = Get-Date
    
    # Banner
    Clear-Host
    Write-Host ""
    Write-Host "╔═══════════════════════════════════════════════════════════╗" -ForegroundColor $ColorTitle
    Write-Host "║                                                           ║" -ForegroundColor $ColorTitle
    Write-Host "║  SITE SURVEY REPORT - RESTAURAÇÃO AUTOMÁTICA             ║" -ForegroundColor $ColorTitle
    Write-Host "║                                                           ║" -ForegroundColor $ColorTitle
    Write-Host "║  CleanWatts Portal v2.0                                   ║" -ForegroundColor $ColorTitle
    Write-Host "║  Data: 5 de Dezembro de 2025                              ║" -ForegroundColor $ColorTitle
    Write-Host "║                                                           ║" -ForegroundColor $ColorTitle
    Write-Host "╚═══════════════════════════════════════════════════════════╝" -ForegroundColor $ColorTitle
    Write-Host ""
    
    Write-Log -Message "===== INÍCIO DA RESTAURAÇÃO =====" -Level "START"
    Write-Info "Log: $LogFile"
    Write-Host ""
    
    # Pré-requisitos
    $prereqOk = Test-Prerequisites
    if (-not $prereqOk) {
        Write-Warning "Alguns pré-requisitos falharam. Deseja continuar? (S/N)"
        $continue = Read-Host
        if ($continue -ne "S" -and $continue -ne "s") {
            Write-Warning "Restauração cancelada pelo utilizador"
            return
        }
    }
    
    # Backup de segurança
    $backupOk = Create-SafetyBackup
    if (-not $backupOk) {
        Write-Error-Custom "Falha ao criar backup de segurança - ABORTANDO"
        return
    }
    
    # Restaurar ficheiros
    $filesOk = Restore-Files
    if (-not $filesOk) {
        Write-Warning "Alguns ficheiros não foram restaurados corretamente"
    }
    
    # Restaurar base de dados
    $dbOk = Restore-Database
    
    # Verificar instalação
    $testOk = Test-Installation
    
    # Resumo final
    $overallSuccess = $filesOk -and $testOk
    Show-Summary -Success $overallSuccess -StartTime $startTime
    
    Write-Log -Message "===== FIM DA RESTAURAÇÃO =====" -Level "END"
    
    # Pausa final
    Write-Host ""
    Read-Host "Pressione ENTER para sair..."
}

# Executar
Main
