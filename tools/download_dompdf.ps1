Set-Location -LiteralPath 'C:\xampp\htdocs\cleanwattsportal'
$zip = 'vendor\dompdf-1.2.2.zip'
$dest = 'vendor\dompdf'
if (-Not (Test-Path 'vendor')) { New-Item -ItemType Directory -Path 'vendor' | Out-Null }
Write-Output "Downloading dompdf..."
Invoke-WebRequest -Uri 'https://github.com/dompdf/dompdf/releases/download/v1.2.2/dompdf-1.2.2.zip' -OutFile $zip -UseBasicParsing
if (Test-Path $dest) { Remove-Item -Recurse -Force $dest }
Expand-Archive -LiteralPath $zip -DestinationPath $dest
$extracted = Join-Path $dest 'dompdf-1.2.2'
$target = Join-Path $dest 'dompdf'
if (Test-Path $extracted) {
    if (Test-Path $target) { Remove-Item -Recurse -Force $target }
    Move-Item -LiteralPath $extracted -Destination $target
}
Remove-Item -LiteralPath $zip -Force
Write-Output 'Download and extract complete.'
