[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression

$projectRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $MyInvocation.MyCommand.Path))
$distPath = Join-Path $projectRoot 'dist'
$timestamp = Get-Date -Format 'yyyyMMdd-HHmm'
$appZipPath = Join-Path $distPath "organizadorweb-app-$timestamp.zip"
$publicZipPath = Join-Path $distPath "organizadorweb-public-$timestamp.zip"

# El despliegue actual no evidencia que cPanel ejecute Composer. Se incluye el
# vendor local para que el paquete de aplicación sea autosuficiente.
$includeVendor = $true

$requiredDirectories = @(
    'app',
    'bootstrap',
    'config',
    'database',
    'resources',
    'routes',
    'public'
)

$requiredFiles = @(
    'artisan',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'vite.config.js',
    'public/index.php',
    'public/build/manifest.json'
)

if ($includeVendor) {
    $requiredDirectories += 'vendor'
    $requiredFiles += 'vendor/autoload.php'
}

$missing = [System.Collections.Generic.List[string]]::new()

foreach ($relativePath in $requiredDirectories) {
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot $relativePath) -PathType Container)) {
        $missing.Add("Carpeta: $relativePath")
    }
}

foreach ($relativePath in $requiredFiles) {
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot $relativePath) -PathType Leaf)) {
        $missing.Add("Archivo: $relativePath")
    }
}

if ($missing.Count -gt 0) {
    $details = $missing | ForEach-Object { " - $_" }
    throw "No se puede crear el release porque faltan elementos esenciales:`n$($details -join "`n")"
}

if ((Test-Path -LiteralPath $appZipPath) -or (Test-Path -LiteralPath $publicZipPath)) {
    throw "Ya existe un ZIP para la marca de tiempo $timestamp. No se sobrescribió ningún archivo. Espere al siguiente minuto o mueva el release existente."
}

function Get-NormalizedRelativePath {
    param(
        [Parameter(Mandatory)]
        [string] $BasePath,

        [Parameter(Mandatory)]
        [string] $FullPath
    )

    $normalizedBase = [System.IO.Path]::GetFullPath($BasePath).TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar
    $normalizedFull = [System.IO.Path]::GetFullPath($FullPath)

    if (-not $normalizedFull.StartsWith($normalizedBase, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "La ruta '$normalizedFull' está fuera de '$normalizedBase'."
    }

    return $normalizedFull.Substring($normalizedBase.Length).Replace('\', '/')
}

function Test-IsExcluded {
    param(
        [Parameter(Mandatory)]
        [string] $ProjectRelativePath
    )

    $path = $ProjectRelativePath.Replace('\', '/').TrimStart('/').ToLowerInvariant()
    $leaf = [System.IO.Path]::GetFileName($path)

    if ($path -match '(^|/)(\.git|\.github|\.codex|\.agents|node_modules|tests|dist|dumps|backups)(/|$)') {
        return $true
    }

    if ($path -match '(^|/)\.env($|\.)') {
        return $true
    }

    if ($path.StartsWith('storage/logs/') -or
        $path.StartsWith('storage/framework/') -or
        $path.StartsWith('storage/app/') -or
        $path.StartsWith('bootstrap/cache/') -or
        $path.StartsWith('public/storage/')) {
        return $true
    }

    if ($path -eq 'public/hot' -or
        $path -eq '.phpunit.result.cache' -or
        $path -eq 'phpunit.xml' -or
        $leaf -eq 'auth.json' -or
        $leaf -eq '.ds_store' -or
        $leaf -eq 'thumbs.db' -or
        $leaf -eq 'desktop.ini') {
        return $true
    }

    $sensitiveOrGeneratedExtensions = @(
        '.sql', '.log', '.tmp', '.bak', '.dump',
        '.sqlite', '.sqlite3'
    )

    foreach ($extension in $sensitiveOrGeneratedExtensions) {
        if ($path.EndsWith($extension, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $true
        }
    }

    if (-not $path.StartsWith('vendor/')) {
        foreach ($extension in @('.pem', '.key', '.p12', '.pfx')) {
            if ($path.EndsWith($extension, [System.StringComparison]::OrdinalIgnoreCase)) {
                return $true
            }
        }
    }

    return $false
}

function Get-ReleaseFiles {
    param(
        [Parameter(Mandatory)]
        [string[]] $Directories,

        [Parameter(Mandatory)]
        [string[]] $Files
    )

    $selected = [System.Collections.Generic.List[System.IO.FileInfo]]::new()

    foreach ($relativeDirectory in $Directories) {
        $directoryPath = Join-Path $projectRoot $relativeDirectory

        Get-ChildItem -LiteralPath $directoryPath -File -Recurse -Force | ForEach-Object {
            $projectRelativePath = Get-NormalizedRelativePath -BasePath $projectRoot -FullPath $_.FullName

            if (-not (Test-IsExcluded -ProjectRelativePath $projectRelativePath)) {
                $selected.Add($_)
            }
        }
    }

    foreach ($relativeFile in $Files) {
        $file = Get-Item -LiteralPath (Join-Path $projectRoot $relativeFile) -Force
        $projectRelativePath = Get-NormalizedRelativePath -BasePath $projectRoot -FullPath $file.FullName

        if (-not (Test-IsExcluded -ProjectRelativePath $projectRelativePath)) {
            $selected.Add($file)
        }
    }

    return @($selected | Sort-Object FullName -Unique)
}

function New-ReleaseZip {
    param(
        [Parameter(Mandatory)]
        [System.IO.FileInfo[]] $Files,

        [Parameter(Mandatory)]
        [string] $EntryBasePath,

        [Parameter(Mandatory)]
        [string] $DestinationPath
    )

    $fileStream = $null
    $archive = $null
    $createdDestination = $false

    try {
        $fileStream = [System.IO.File]::Open(
            $DestinationPath,
            [System.IO.FileMode]::CreateNew,
            [System.IO.FileAccess]::Write,
            [System.IO.FileShare]::None
        )
        $createdDestination = $true
        $archive = [System.IO.Compression.ZipArchive]::new(
            $fileStream,
            [System.IO.Compression.ZipArchiveMode]::Create,
            $false
        )

        foreach ($file in $Files) {
            $entryName = Get-NormalizedRelativePath -BasePath $EntryBasePath -FullPath $file.FullName
            $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
            $entry.LastWriteTime = $file.LastWriteTime

            $inputStream = $null
            $outputStream = $null

            try {
                $inputStream = $file.OpenRead()
                $outputStream = $entry.Open()
                $inputStream.CopyTo($outputStream)
            }
            finally {
                if ($null -ne $outputStream) {
                    $outputStream.Dispose()
                }
                if ($null -ne $inputStream) {
                    $inputStream.Dispose()
                }
            }
        }
    }
    catch {
        if ($null -ne $archive) {
            $archive.Dispose()
            $archive = $null
        }
        if ($null -ne $fileStream) {
            $fileStream.Dispose()
            $fileStream = $null
        }
        if ($createdDestination -and (Test-Path -LiteralPath $DestinationPath)) {
            Remove-Item -LiteralPath $DestinationPath -Force
        }
        throw
    }
    finally {
        if ($null -ne $archive) {
            $archive.Dispose()
        }
        if ($null -ne $fileStream) {
            $fileStream.Dispose()
        }
    }
}

$appDirectories = @('app', 'bootstrap', 'config', 'database', 'resources', 'routes')
if ($includeVendor) {
    $appDirectories += 'vendor'
}

$appRootFiles = @(
    'artisan',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'vite.config.js'
)

$appFiles = Get-ReleaseFiles -Directories $appDirectories -Files $appRootFiles

$publicRoot = Join-Path $projectRoot 'public'
$publicFiles = Get-ChildItem -LiteralPath $publicRoot -File -Recurse -Force | Where-Object {
    $projectRelativePath = Get-NormalizedRelativePath -BasePath $projectRoot -FullPath $_.FullName
    -not (Test-IsExcluded -ProjectRelativePath $projectRelativePath)
} | Sort-Object FullName -Unique

if ($appFiles.Count -eq 0) {
    throw 'No se encontraron archivos para el ZIP de aplicación.'
}

if ($publicFiles.Count -eq 0) {
    throw 'No se encontraron archivos para el ZIP público.'
}

New-Item -ItemType Directory -Path $distPath -Force | Out-Null

$createdZipPaths = [System.Collections.Generic.List[string]]::new()

try {
    New-ReleaseZip -Files $appFiles -EntryBasePath $projectRoot -DestinationPath $appZipPath
    $createdZipPaths.Add($appZipPath)
    New-ReleaseZip -Files $publicFiles -EntryBasePath $publicRoot -DestinationPath $publicZipPath
    $createdZipPaths.Add($publicZipPath)
}
catch {
    foreach ($createdZipPath in $createdZipPaths) {
        if (Test-Path -LiteralPath $createdZipPath) {
            Remove-Item -LiteralPath $createdZipPath -Force
        }
    }
    throw
}

$appZip = Get-Item -LiteralPath $appZipPath
$publicZip = Get-Item -LiteralPath $publicZipPath

Write-Host ''
Write-Host 'Release generado correctamente:' -ForegroundColor Green
Write-Host ("Aplicación: {0}" -f $appZip.FullName)
Write-Host ("  Archivos: {0:N0}" -f $appFiles.Count)
Write-Host ("  Tamaño:  {0:N2} MB" -f ($appZip.Length / 1MB))
Write-Host ("Público:    {0}" -f $publicZip.FullName)
Write-Host ("  Archivos: {0:N0}" -f $publicFiles.Count)
Write-Host ("  Tamaño:  {0:N2} MB" -f ($publicZip.Length / 1MB))
