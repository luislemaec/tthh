param(
    [string]$Servidor = '127.0.0.1',
    [int]$Puerto = 5432,
    [string]$Usuario = 'postgres',
    [string]$BaseDatos = 'BDD_RRHH',
    [string]$PgDump = 'C:\Program Files\PostgreSQL\17\bin\pg_dump.exe',
    [string]$Destino,
    [switch]$MostrarComando
)

$ErrorActionPreference = 'Stop'
$raizProyecto = Split-Path -Parent $PSScriptRoot
$lista = Join-Path $raizProyecto 'docs\tablas-datos-base.txt'
$tablas = @(Get-Content -LiteralPath $lista | ForEach-Object { $_.Trim() } | Where-Object { $_ -and -not $_.StartsWith('#') })
if ($tablas.Count -eq 0) { throw 'La lista de tablas esta vacia.' }
if (@($tablas | Sort-Object -Unique).Count -ne $tablas.Count) { throw 'La lista contiene tablas duplicadas.' }
foreach ($tabla in $tablas) {
    if ($tabla -notmatch '^(dbo|adq)\.[a-z][a-z0-9_]*$') { throw "Nombre de tabla no permitido: $tabla" }
}

if (-not $Destino) {
    $Destino = Join-Path ([Environment]::GetFolderPath('MyDocuments')) ('datos_base_BDD_RRHH_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '.sql')
}
$Destino = [IO.Path]::GetFullPath($Destino)
if (Test-Path -LiteralPath $Destino) { throw "El archivo ya existe; no se sobrescribira: $Destino" }
if (-not (Test-Path -LiteralPath (Split-Path -Parent $Destino) -PathType Container)) { throw 'La carpeta de destino no existe.' }

$argumentos = @('-h', $Servidor, '-p', "$Puerto", '-U', $Usuario, '-d', $BaseDatos,
    '--data-only', '--column-inserts', '--no-owner', '--no-privileges', '--strict-names', '-f', $Destino)
foreach ($tabla in $tablas) { $argumentos += @('--table', $tabla) }

Write-Host "Tablas seleccionadas: $($tablas.Count)"
Write-Host "Archivo de destino: $Destino"
if ($MostrarComando) {
    Write-Output ('& "' + $PgDump + '" ' + (($argumentos | ForEach-Object { "'" + $_.Replace("'", "''") + "'" }) -join ' '))
    return
}
if (-not (Test-Path -LiteralPath $PgDump -PathType Leaf)) { throw "No existe pg_dump: $PgDump" }

# pg_dump solo lee la BD. Si necesita la contrasena, la solicita directamente.
# No se lee .env ni se escribe una contrasena en el comando o en el archivo.
& $PgDump @argumentos
if ($LASTEXITCODE -ne 0) {
    throw "pg_dump fallo con codigo $LASTEXITCODE. El archivo puede estar incompleto; no utilizarlo para importar."
}
Write-Host 'Exportacion completada. Revisar parametros sensibles antes de compartir o versionar.'
