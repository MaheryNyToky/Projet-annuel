$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$StartupFolder = [Environment]::GetFolderPath("Startup")
$LauncherScript = Join-Path $ProjectRoot "Demarrer-Kamoro-Au-Demarrage.ps1"

if (-not (Test-Path $LauncherScript)) {
    throw "Le fichier Demarrer-Kamoro-Au-Demarrage.ps1 est introuvable."
}

function Get-PowerShellExecutable {
    $candidates = @(
        (Get-Command powershell.exe -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Source),
        (Join-Path $PSHOME "powershell.exe"),
        (Get-Command pwsh.exe -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Source),
        (Join-Path $PSHOME "pwsh.exe")
    ) | Where-Object { $_ } | Select-Object -Unique

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    throw "Aucun executable PowerShell compatible n'a ete trouve."
}

$ShortcutPath = Join-Path $StartupFolder "Kamoro - Auto-demarrage.lnk"
$Shell = New-Object -ComObject WScript.Shell
$Shortcut = $Shell.CreateShortcut($ShortcutPath)
$Shortcut.TargetPath = Get-PowerShellExecutable
$Shortcut.Arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$LauncherScript`""
$Shortcut.WorkingDirectory = $ProjectRoot
$Shortcut.Description = "Lancer Kamoro Reservation Facturation au demarrage de Windows"
$Shortcut.Save()

Write-Host ""
Write-Host "[OK] Auto-demarrage active."
Write-Host "Le raccourci a ete cree dans le dossier Demarrage de Windows."
