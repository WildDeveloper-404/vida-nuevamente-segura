$ErrorActionPreference = 'Stop'
$projectPath = $PSScriptRoot
$phpPath = Join-Path $projectPath '.tools\php\php.exe'
if (-not (Test-Path -LiteralPath $phpPath)) { $phpPath = (Get-Command php -ErrorAction Stop).Source }
Set-Location -LiteralPath $projectPath
if (-not (Test-Path -LiteralPath '.env')) { throw 'Configure .env siguiendo README.md antes de iniciar.' }
$clusterPath = Join-Path $projectPath '.tools\pgdata'
$pgControl = 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe'
if ((Test-Path -LiteralPath $clusterPath) -and (Test-Path -LiteralPath $pgControl)) {
    & $pgControl -D $clusterPath status *> $null
    if ($LASTEXITCODE -ne 0) { & $pgControl -D $clusterPath -l (Join-Path $projectPath '.tools\postgres.log') -o '-p 55432 -h 127.0.0.1' start }
}
Write-Host 'Clínica disponible en http://127.0.0.1:8000. Ctrl+C para detener el servidor web.'
& $phpPath -S '127.0.0.1:8000' -t public
