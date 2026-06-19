$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$StartupFolder = [Environment]::GetFolderPath("Startup")
$LauncherScript = Join-Path $ProjectRoot "Demarrer-Kamoro-Au-Demarrage.ps1"

if (-not (Test-Path $LauncherScript)) {
    throw "Le fichier Demarrer-Kamoro-Au-Demarrage.ps1 est introuvable."
}

$ShortcutPath = Join-Path $StartupFolder "Kamoro - Auto-demarrage.lnk"
$Shell = New-Object -ComObject WScript.Shell
$Shortcut = $Shell.CreateShortcut($ShortcutPath)
$Shortcut.TargetPath = (Join-Path $PSHOME "powershell.exe")
$Shortcut.Arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$LauncherScript`""
$Shortcut.WorkingDirectory = $ProjectRoot
$Shortcut.Description = "Lancer Kamoro Reservation Facturation au demarrage de Windows"
$Shortcut.Save()

Write-Host ""
Write-Host "[OK] Auto-demarrage active."
Write-Host "Le raccourci a ete cree dans le dossier Demarrage de Windows."
