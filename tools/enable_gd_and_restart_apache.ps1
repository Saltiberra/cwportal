# Run this script AS ADMINISTRATOR
# It will:
# 1) backup php.ini
# 2) uncomment extension=gd / extension=gd2 if present, or append extension=gd
# 3) try to restart Apache (service or httpd.exe)
# 4) check gd extension status for CLI php

$phpIni = 'C:\xampp\php\php.ini'
$backup = "$phpIni.bak.$((Get-Date).ToString('yyyyMMddHHmmss'))"
if (-not (Test-Path $phpIni)) {
    Write-Error "php.ini not found at $phpIni. Verify XAMPP path and edit the script if needed."; exit 1
}
Write-Output "Backing up php.ini -> $backup"
Copy-Item $phpIni $backup -Force

# Read and uncomment extension lines for gd/gd2
$content = Get-Content $phpIni -Raw
$new = $content -replace "(?mi)^\s*;\s*(extension\s*=\s*(gd2?\.dll|gd2?|php_gd2))", "$1"
# If neither gd or gd2 exist uncommented, ensure extension=gd exists
if ($new -notmatch "(?mi)^\s*extension\s*=\s*gd2?" ) {
    $new = $new + "`r`nextension=gd"
    Write-Output "Added extension=gd to php.ini"
}
Set-Content $phpIni $new -Encoding UTF8
Write-Output "php.ini updated (a backup was created)."

# Try to restart Apache. Prefer service if present
$svcNames = 'Apache2.4', 'apache2.4', 'Apache', 'httpd'
$svc = $null
foreach ($n in $svcNames) {
    try { $s = Get-Service -Name $n -ErrorAction SilentlyContinue; if ($s) { $svc = $s; break } } catch { }
}
if ($svc) {
    Write-Output "Restarting service $($svc.Name) ..."
    try {
        Restart-Service -Name $svc.Name -Force -ErrorAction Stop
        Write-Output "Service $($svc.Name) restarted."
    }
    catch {
        Write-Warning "Failed to restart service $($svc.Name): $_. Exception.Message"
    }
}
else {
    $httpd = 'C:\xampp\apache\bin\httpd.exe'
    if (Test-Path $httpd) {
        Write-Output "Attempting httpd.exe -k restart ..."
        try {
            & $httpd -k restart
            Write-Output "Called httpd.exe -k restart"
        }
        catch {
            Write-Warning "httpd.exe restart failed: $_"
            Write-Output "If apache is controlled by the XAMPP Control Panel, please restart it there (run XAMPP as Administrator)."
        }
    }
    else {
        Write-Warning "No Apache service found and httpd.exe not found at $httpd. Restart Apache using XAMPP Control Panel as Administrator."
    }
}

# Check CLI PHP for GD
$phpExe = 'C:\xampp\php\php.exe'
if (Test-Path $phpExe) {
    Write-Output "Verifying GD in CLI php.exe..."
    try {
        # Use single-quoted PHP snippet to avoid PowerShell parsing issues
        $res = & $phpExe -r 'echo extension_loaded("gd") ? "GD OK" : "GD MISSING";'
    }
    catch {
        $res = "ERROR: $($_.Exception.Message)"
    }
    Write-Output "Result: $res"
    if ($res -eq 'GD OK') {
        Write-Output "Success: GD is enabled for CLI PHP. You can now re-run the Dompdf tests."
    }
    else {
        Write-Warning "GD is still missing for CLI PHP. Ensure the php.ini used by C:\xampp\php\php.exe was edited and that you are running this script as Administrator."
    }
}
else {
    Write-Warning "php.exe not found at $phpExe. Verify XAMPP path." 
}

Write-Output "Done. If GD is OK, tell me and I will run the Dompdf image test and regenerate the Open Punch Lists PDF."