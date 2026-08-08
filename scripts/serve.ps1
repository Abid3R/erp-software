# Run the ERP dev server on the local (scoop) native stack.
#   Usage:  ./scripts/serve.ps1 [port]
# Ensures scoop shims are on PATH and forces a single built-in-server worker —
# `php artisan serve` otherwise fails on Windows ("Failed to listen ... reason: ?")
# because PHP's built-in server cannot fork workers on Windows.
param([int]$Port = 8000)

$env:PATH = (Join-Path $env:USERPROFILE 'scoop\shims') + ';' + $env:PATH
$env:PHP_CLI_SERVER_WORKERS = '1'

Set-Location (Split-Path $PSScriptRoot -Parent)
Write-Host "ERP dev server -> http://127.0.0.1:$Port  (admin panel: /admin)" -ForegroundColor Green
php artisan serve --host=127.0.0.1 --port=$Port
