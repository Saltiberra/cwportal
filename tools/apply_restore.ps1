<#
PowerShell helper to run the restore_pv_modules.sql script on local XAMPP or remote MySQL
Usage: Open PowerShell and run as administrator, then:
  .\apply_restore.ps1 -Host localhost -Port 3306 -User root -Database cleanwattsportal

This script: prompts for password, runs the SQL file and prints progress.
#>

param(
    [string] $Host = 'localhost',
    [int] $Port = 3306,
    [string] $User = 'root',
    [string] $Database = 'cleanwattsportal',
    [string] $SqlFile = "tools\restore_pv_modules.sql",
    [string] $MySqlClientPath = 'C:\\xampp\\mysql\\bin\\mysql.exe' # Adjust if mysql is elsewhere
)

if (!(Test-Path $SqlFile)) {
    Write-Host "SQL file not found at $SqlFile" -ForegroundColor Red
    exit 1
}

# Prompt for password securely
$pw = Read-Host -AsSecureString "Enter MySQL password for $User@$Host"
$plainPw = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($pw))

# Build command
$cmd = "`"$MySqlClientPath`" -h $Host -P $Port -u $User -p$plainPw $Database < `"$SqlFile`""

Write-Host "About to run SQL file with the following settings:" -ForegroundColor Cyan
Write-Host "  Host: $Host" -ForegroundColor Yellow
Write-Host "  Port: $Port" -ForegroundColor Yellow
Write-Host "  User: $User" -ForegroundColor Yellow
Write-Host "  Database: $Database" -ForegroundColor Yellow
Write-Host "  SQL file: $SqlFile`n" -ForegroundColor Yellow

# Confirm
$confirmed = Read-Host "Proceed? (y/N)"
if ($confirmed -ne 'y' -and $confirmed -ne 'Y') {
    Write-Host "Cancelled by user" -ForegroundColor Yellow
    exit 0
}

# Execute via PowerShell's Start-Process (shell redirect) - requires correct quoting
try {
    $psCmd = "& `"$MySqlClientPath`" -h $Host -P $Port -u $User -p$plainPw $Database < `"$SqlFile`""
    Write-Host "Executing: $psCmd" -ForegroundColor Cyan
    cmd.exe /c $psCmd
    Write-Host " restore script executed. Check console output for errors." -ForegroundColor Green
}
catch {
    Write-Host "Error running command: $_" -ForegroundColor Red
}

# Clean up credential in memory
$plainPw = ""
[GC]::Collect(); [GC]::WaitForPendingFinalizers();

Write-Host "Done." -ForegroundColor Green


# End of script
