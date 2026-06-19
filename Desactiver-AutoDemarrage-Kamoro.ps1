$ErrorActionPreference = "Stop"

$StartupFolder = [Environment]::GetFolderPath("Startup")
$ShortcutPath = Join-Path $StartupFolder "Kamoro - Auto-demarrage.lnk"

if (Test-Path $ShortcutPath) {
    Remove-Item -LiteralPath $ShortcutPath -Force
    Write-Host ""
    Write-Host "[OK] Auto-demarrage desactive."
} else {
    Write-Host ""
    Write-Host "[INFO] Aucun raccourci d'auto-demarrage n'a ete trouve."
}
