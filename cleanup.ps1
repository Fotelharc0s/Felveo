<#
cleanup.ps1 - Interactive cleanup helper for the Felveo project

Usage:
  .\cleanup.ps1            # interactive: list candidate files and ask before deleting
  .\cleanup.ps1 -Force     # delete found files without prompting
  .\cleanup.ps1 -RemoveSamples  # remove samples folder (if present)
#>
param(
    [switch]$Force,
    [switch]$RemoveSamples,
    [switch]$RemoveUploads
)

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$root = (Get-Location).Path
Write-Host "Running cleanup in: $root"

# Only target top-level candidate files to avoid accidental wide deletes
$patterns = @('*.sql','*.log','screenshot*.png','import_*.log','*.bak')
$candidates = @()
foreach ($p in $patterns) {
    $candidates += Get-ChildItem -Path $root -File -Filter $p -ErrorAction SilentlyContinue
}

if ($candidates.Count -eq 0) {
    Write-Host "No matching top-level files found." -ForegroundColor Yellow
} else {
    Write-Host "Found the following files:" -ForegroundColor Cyan
    $candidates | ForEach-Object { Write-Host " - $_" }

    if ($Force) {
        $candidates | Remove-Item -Force
        Write-Host "Deleted ${($candidates).Count} files." -ForegroundColor Green
    } else {
        $confirm = Read-Host "Delete these files? (y/N)"
        if ($confirm -match '^[Yy]') {
            $candidates | Remove-Item -Force
            Write-Host "Deleted ${($candidates).Count} files." -ForegroundColor Green
        } else {
            Write-Host "Aborted deletion." -ForegroundColor Yellow
        }
    }
}

if ($RemoveSamples) {
    $samp = Join-Path $root 'samples'
    if (Test-Path $samp) {
        if ($Force) {
            Remove-Item -LiteralPath $samp -Recurse -Force
            Write-Host "Removed samples/" -ForegroundColor Green
        } else {
            $c = Read-Host "Remove samples/ directory? (y/N)"
            if ($c -match '^[Yy]') { Remove-Item -LiteralPath $samp -Recurse -Force; Write-Host "Removed samples/" -ForegroundColor Green } else { Write-Host "Left samples/" -ForegroundColor Yellow }
        }
    } else {
        Write-Host "samples/ not found." -ForegroundColor Yellow
    }
}

if ($RemoveUploads) {
    $up = Join-Path $root 'uploads'
    if (Test-Path $up) {
        if ($Force) {
            Remove-Item -LiteralPath $up -Recurse -Force
            Write-Host "Removed uploads/" -ForegroundColor Green
        } else {
            $c = Read-Host "Remove uploads/ directory? (y/N)"
            if ($c -match '^[Yy]') { Remove-Item -LiteralPath $up -Recurse -Force; Write-Host "Removed uploads/" -ForegroundColor Green } else { Write-Host "Left uploads/" -ForegroundColor Yellow }
        }
    } else {
        Write-Host "uploads/ not found." -ForegroundColor Yellow
    }
}

Write-Host "Cleanup finished." -ForegroundColor Cyan
