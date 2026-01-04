<#
Add two brands and two models per brand to pv_module_brands / pv_module_models.
Interactive: asks for DB credentials and brand/model names.
Usage: From PowerShell in workspace root run:
  .\tools\add_brands_models.ps1

This script creates a temporary SQL file and executes it using mysql CLI.
#>

param(
    [string] $Host = 'localhost',
    [int] $Port = 3306,
    [string] $User = 'root',
    [string] $Database = 'cleanwattsportal',
    [string] $MySqlClientPath = 'C:\\xampp\\mysql\\bin\\mysql.exe' # adjust if necessary
)

Write-Host 'Script para adicionar 2 marcas e 2 modelos por marca' -ForegroundColor Cyan

# Prompt for brand0, models
$brand1 = Read-Host 'Nome da primeira marca (ex: JA Solar)'
if ([string]::IsNullOrWhiteSpace($brand1)) { $brand1 = 'JA Solar' }
$model1A = Read-Host "Modelo 1 para $brand1 (ex: JAM72D40-450-MB)"
if ([string]::IsNullOrWhiteSpace($model1A)) { $model1A = 'teste1' }
$model1B = Read-Host "Modelo 2 para $brand1 (ex: JAM72S30-395-GR)"
if ([string]::IsNullOrWhiteSpace($model1B)) { $model1B = 'teste2' }

$brand2 = Read-Host 'Nome da segunda marca (ex: Canadian Solar)'
if ([string]::IsNullOrWhiteSpace($brand2)) { $brand2 = 'Canadian Solar' }
$model2A = Read-Host "Modelo 1 para $brand2 (ex: CS3K-XXX)"
if ([string]::IsNullOrWhiteSpace($model2A)) { $model2A = 'cs-test1' }
$model2B = Read-Host "Modelo 2 para $brand2 (ex: CS5P-XXX)"
if ([string]::IsNullOrWhiteSpace($model2B)) { $model2B = 'cs-test2' }

# Prompt for DB password securely
$securePw = Read-Host -AsSecureString "Password MySQL para $User@$Host"
$plainPw = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePw))

# Build SQL content
$sqlContent = @"
-- Add two brands and two models per brand (idempotent)
START TRANSACTION;

-- Insert brand 1
INSERT INTO pv_module_brands (brand_name)
SELECT '$brand1' WHERE NOT EXISTS (SELECT 1 FROM pv_module_brands WHERE brand_name = '$brand1');

-- Set brand 1 id
SET @b1 = (SELECT id FROM pv_module_brands WHERE brand_name = '$brand1' LIMIT 1);

-- Insert models for brand 1
INSERT INTO pv_module_models (brand_id, model_name, characteristics, power_options, created_at)
SELECT @b1, '$model1A', 'Auto-created model', '100', NOW()
WHERE @b1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM pv_module_models WHERE brand_id = @b1 AND model_name = '$model1A');

INSERT INTO pv_module_models (brand_id, model_name, characteristics, power_options, created_at)
SELECT @b1, '$model1B', 'Auto-created model', '100', NOW()
WHERE @b1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM pv_module_models WHERE brand_id = @b1 AND model_name = '$model1B');

-- Insert brand 2
INSERT INTO pv_module_brands (brand_name)
SELECT '$brand2' WHERE NOT EXISTS (SELECT 1 FROM pv_module_brands WHERE brand_name = '$brand2');

-- Set brand 2 id
SET @b2 = (SELECT id FROM pv_module_brands WHERE brand_name = '$brand2' LIMIT 1);

-- Insert models for brand 2
INSERT INTO pv_module_models (brand_id, model_name, characteristics, power_options, created_at)
SELECT @b2, '$model2A', 'Auto-created model', '100', NOW()
WHERE @b2 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM pv_module_models WHERE brand_id = @b2 AND model_name = '$model2A');

INSERT INTO pv_module_models (brand_id, model_name, characteristics, power_options, created_at)
SELECT @b2, '$model2B', 'Auto-created model', '100', NOW()
WHERE @b2 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM pv_module_models WHERE brand_id = @b2 AND model_name = '$model2B');

COMMIT;

-- Verification
SELECT 'BRANDS' AS type, id, brand_name FROM pv_module_brands WHERE brand_name IN ('$brand1', '$brand2');
SELECT 'MODELS' AS type, id, brand_id, model_name FROM pv_module_models WHERE model_name IN ('$model1A', '$model1B', '$model2A', '$model2B');
"@

# Temporary SQL file
$tempFile = Join-Path $PSScriptRoot "temp_add_brands_models.sql"
Set-Content -Path $tempFile -Value $sqlContent -Encoding UTF8

Write-Host "SQL file written to $tempFile" -ForegroundColor Green

# Show a preview of the SQL (first 30 lines)
Write-Host "--- PREVIEW (first 30 linhas) ---" -ForegroundColor Yellow
Get-Content $tempFile -TotalCount 30 | ForEach-Object { Write-Host $_ }
Write-Host "-------------------------------" -ForegroundColor Yellow

# Confirm run
$ans = Read-Host "Executar o script no mysql (Y/N)?"
if ($ans -ne 'Y' -and $ans -ne 'y') { Write-Host 'Cancelado pelo utilizador'; Remove-Item $tempFile -ErrorAction SilentlyContinue; exit 0 }

# Build command using cmd redirection (mysql CLI)
if (-not (Test-Path $MySqlClientPath)) {
    Write-Host "mysql.exe não encontrado em $MySqlClientPath. Ajusta a variável MySqlClientPath na linha 1 do script." -ForegroundColor Red
    Remove-Item $tempFile -ErrorAction SilentlyContinue
    exit 1
}

# Assemble cmd string
$cmd = "& `"$MySqlClientPath`" -h $Host -P $Port -u $User -p$plainPw $Database < `"$tempFile`""

Write-Host "A executar: $MySqlClientPath -h $Host -P $Port -u $User $Database" -ForegroundColor Cyan

# Run via cmd
$startInfo = "cmd.exe /c `"$MySqlClientPath`" -h $Host -P $Port -u $User -p$plainPw $Database < `"$tempFile`""

try {
    cmd.exe /c $startInfo
    Write-Host "Execução terminada. Verifica a saída no terminal acima." -ForegroundColor Green
}
catch {
    Write-Host "Erro ao executar mysql CLI: $_" -ForegroundColor Red
}

# Cleanup sensitive data
$plainPw = ''
[GC]::Collect(); [GC]::WaitForPendingFinalizers();

# Temp file kept for review - remove if you want
Write-Host "O ficheiro SQL temporário está em: $tempFile" -ForegroundColor Yellow
Write-Host "Se quiseres apagar: Remove-Item $tempFile" -ForegroundColor Yellow

Write-Host "Pronto." -ForegroundColor Green
