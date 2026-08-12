# Run the ERP dev server on the LAN so other PCs (e.g. your office PC on the same
# network) can reach it. Binds to 0.0.0.0 instead of 127.0.0.1.
#
#   Usage:  ./scripts/serve-network.ps1 [port]
#
# ONE-TIME firewall step (run once in an *elevated* PowerShell — "Run as Administrator"):
#   New-NetFirewallRule -DisplayName "ERP dev server 8000" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow
#
# NOTE: this is a development server over plain HTTP on a trusted LAN — do not expose
# it to the public internet. PostgreSQL stays local to this machine.
param([int]$Port = 8000)

$env:PATH = (Join-Path $env:USERPROFILE 'scoop\shims') + ';' + $env:PATH
$env:PHP_CLI_SERVER_WORKERS = '1'

Set-Location (Split-Path $PSScriptRoot -Parent)

$ip = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
    Select-Object -First 1 -ExpandProperty IPAddress)

Write-Host "ERP dev server (LAN) is starting..." -ForegroundColor Green
Write-Host "  This PC:     http://127.0.0.1:$Port/admin" -ForegroundColor Cyan
if ($ip) {
    Write-Host "  Office PC:   http://$($ip):$Port/admin   <-- open this on the other machine" -ForegroundColor Yellow
}
Write-Host "  (If the office PC can't connect, run the one-time firewall command in the header of this script.)" -ForegroundColor DarkGray

php artisan serve --host=0.0.0.0 --port=$Port
