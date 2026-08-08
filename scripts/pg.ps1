# PostgreSQL control for the local (scoop) native stack.
#   Usage:  ./scripts/pg.ps1 start | stop | status
# Postgres is not registered as a Windows service, so start it after each reboot.
param([ValidateSet('start','stop','status')] [string]$action = 'status')

$bin  = Join-Path $env:USERPROFILE 'scoop\apps\postgresql\current\bin'
$data = Join-Path $env:USERPROFILE 'scoop\apps\postgresql\current\data'
$log  = Join-Path $data 'server.log'
$pgctl = Join-Path $bin 'pg_ctl.exe'

switch ($action) {
    'start'  { & $pgctl -D $data -l $log start }
    'stop'   { & $pgctl -D $data stop -m fast }
    'status' { & $pgctl -D $data status }
}
