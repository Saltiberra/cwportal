<#
.SYNOPSIS
    Verifica se o diretório deploy contém os ficheiros essenciais antes do upload.
.DESCRIPTION
    Valida a presença dos ficheiros: production.php, .production, database.php e que não há ficheiros indesejados como .git.
#>
param(
    [string]$DeployPath = (Join-Path (Get-Location).Path "$((Get-Item -Path (Get-Location).Path).Name)_DEPLOY")
)

Write-Host "Verificação da pasta de deploy: $DeployPath" -ForegroundColor Cyan

if(-not (Test-Path $DeployPath)) {
    Write-Error "Pasta de deploy não encontrada: $DeployPath"
    exit 1
}

$checks = @()
$checks += @{ 'name' = 'production.php'; 'path' = Join-Path $DeployPath 'config\production.php' }
$checks += @{ 'name' = '.production flag'; 'path' = Join-Path $DeployPath 'config\.production' }
$checks += @{ 'name' = 'database.php'; 'path' = Join-Path $DeployPath 'config\database.php' }
$checks += @{ 'name' = 'index.php'; 'path' = Join-Path $DeployPath 'index.php' }

$ok = $true
foreach ($c in $checks) {
    if (-not (Test-Path $c.path)) {
        Write-Host "[FAIL] Não encontrado: $($c.name) - $($c.path)" -ForegroundColor Red
        $ok = $false
    } else {
        Write-Host "[OK] Encontrado: $($c.name)" -ForegroundColor Green
    }
}

# Check for disallowed items:
$disallowed = @('.git', '.vscode', 'node_modules', 'tests', 'documentation')
foreach ($d in $disallowed) {
    $found = Get-ChildItem -Path (Join-Path $DeployPath '*') -Include $d -Recurse -Force -ErrorAction SilentlyContinue
    if ($found) {
        Write-Host "[WARN] Elemento de desenvolvimento detectado: $d" -ForegroundColor Yellow
    }
}

if($ok) {
    Write-Host "Pasta de deploy pronta para transferência via FTP." -ForegroundColor Cyan
    exit 0
} else {
    Write-Error "Corrige os itens em falta e re-executa a verificação." -ForegroundColor Red
    exit 1
}
