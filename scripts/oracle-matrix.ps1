<#
.SYNOPSIS
  Runs the core oracle suite against several MySQL versions on Windows, without Docker.

.DESCRIPTION
  For every MySQL build found (e.g. Laragon's bin\mysql\mysql-8.0.28-winx64), the script:
    1. initialises a throwaway data directory under %TEMP%,
    2. starts that mysqld as a second server on -Port (default 3307), ignoring any my.ini,
    3. runs `pest --group=oracle` in packages/core against it,
    4. shuts it down and deletes the data directory.
  The MySQL server you normally use (port 3306) is not touched.

.EXAMPLE
  scripts\oracle-matrix.ps1
  scripts\oracle-matrix.ps1 -MysqlRoot D:\laragon\bin\mysql -Versions 8.0.28,8.4.11
#>
param(
    [string]$MysqlRoot = $(if ($env:PREFLIGHT_MYSQL_ROOT) { $env:PREFLIGHT_MYSQL_ROOT } else { 'D:\laragon\bin\mysql' }),
    [string[]]$Versions = @(),
    [int]$Port = 3307
)

# Native tools write progress to stderr; don't treat that as a failure.
$ErrorActionPreference = 'Continue'
$core = Join-Path (Split-Path -Parent $PSScriptRoot) 'packages\core'

$builds = Get-ChildItem -Path $MysqlRoot -Directory |
    Where-Object { $_.Name -match '^mysql-(\d+\.\d+\.\d+)' -and (Test-Path (Join-Path $_.FullName 'bin\mysqld.exe')) } |
    ForEach-Object { [pscustomobject]@{ Version = [version]($_.Name -replace '^mysql-(\d+\.\d+\.\d+).*$', '$1'); Path = $_.FullName } } |
    Where-Object { $_.Version -ge [version]'8.0.0' } |
    Sort-Object Version

if ($Versions.Count -gt 0) {
    $builds = $builds | Where-Object { $Versions -contains $_.Version.ToString() }
}
if (-not $builds) {
    throw "No MySQL 8+ builds found in $MysqlRoot."
}

function Test-MySql([int]$port) {
    & php -r "try { new PDO('mysql:host=127.0.0.1;port=$port', 'root', ''); exit(0); } catch (Throwable `$e) { exit(1); }" 2>$null
    return $LASTEXITCODE -eq 0
}

if (Test-MySql $Port) {
    throw "Something is already listening on port $Port. Pass -Port to use another one."
}

$results = [ordered]@{}

foreach ($build in $builds) {
    $version = $build.Version.ToString()
    $mysqld = Join-Path $build.Path 'bin\mysqld.exe'
    $data = Join-Path $env:TEMP "preflight-oracle-$version"
    Write-Host "`n=== MySQL $version ===" -ForegroundColor Cyan

    if (Test-Path $data) { Remove-Item -Recurse -Force $data }

    & $mysqld --no-defaults "--basedir=$($build.Path)" "--datadir=$data" --initialize-insecure 2>$null
    if ($LASTEXITCODE -ne 0) { $results[$version] = 'could not initialise'; continue }

    $server = Start-Process -FilePath $mysqld -PassThru -WindowStyle Hidden -ArgumentList @(
        '--no-defaults', "--basedir=`"$($build.Path)`"", "--datadir=`"$data`"", "--port=$Port",
        '--bind-address=127.0.0.1', '--mysqlx=OFF', "--log-error=`"$data\error.log`""
    )

    $ready = $false
    for ($i = 0; $i -lt 60; $i++) {
        if (Test-MySql $Port) { $ready = $true; break }
        if ($server.HasExited) { break }
        Start-Sleep -Seconds 1
    }

    if (-not $ready) {
        $results[$version] = 'did not start (see error.log)'
        if (-not $server.HasExited) { Stop-Process -Id $server.Id -Force }
        Get-Content "$data\error.log" -Tail 5 -ErrorAction SilentlyContinue
        continue
    }

    & php -r "(new PDO('mysql:host=127.0.0.1;port=$Port', 'root', ''))->exec('create database preflight_oracle');"

    Push-Location $core
    $env:PREFLIGHT_ORACLE_DSN = "mysql:host=127.0.0.1;port=$Port;dbname=preflight_oracle"
    & php vendor/bin/pest --group=oracle
    $results[$version] = if ($LASTEXITCODE -eq 0) { 'pass' } else { 'FAIL' }
    Remove-Item Env:\PREFLIGHT_ORACLE_DSN
    Pop-Location

    & (Join-Path $build.Path 'bin\mysqladmin.exe') --no-defaults --user=root --host=127.0.0.1 "--port=$Port" shutdown 2>$null
    if (-not $server.WaitForExit(30000)) { Stop-Process -Id $server.Id -Force }
    Remove-Item -Recurse -Force $data -ErrorAction SilentlyContinue
}

Write-Host "`n=== Summary ===" -ForegroundColor Cyan
$failed = $false
foreach ($entry in $results.GetEnumerator()) {
    $color = if ($entry.Value -eq 'pass') { 'Green' } else { 'Red'; $failed = $true }
    Write-Host ("  MySQL {0,-8} {1}" -f $entry.Key, $entry.Value) -ForegroundColor $color
}
if ($failed) { exit 1 }
