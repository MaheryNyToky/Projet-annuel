$ErrorActionPreference = "Stop"

$ProjectRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
$DesktopCandidates = @(
    [Environment]::GetFolderPath("DesktopDirectory"),
    [Environment]::GetFolderPath("Desktop")
) | Where-Object { $_ -and (Test-Path $_) } | Select-Object -Unique
$Shell = New-Object -ComObject WScript.Shell
$LauncherScript = Join-Path $ProjectRoot "Demarrer-Kamoro-Au-Demarrage.ps1"

if (-not $DesktopCandidates -or $DesktopCandidates.Count -eq 0) {
    throw "Aucun dossier Bureau valide n'a ete trouve."
}

function New-ShortcutForTarget {
    param(
        [string]$Name,
        [string]$Target,
        [string]$Arguments,
        [string]$Description
    )

    if (-not (Test-Path $Target)) {
        throw "Fichier introuvable : $Target"
    }

    foreach ($DesktopPath in $DesktopCandidates) {
        $ShortcutPath = Join-Path $DesktopPath "$Name.lnk"
        $Shortcut = $Shell.CreateShortcut($ShortcutPath)
        $Shortcut.TargetPath = $Target
        if ($Arguments) {
            $Shortcut.Arguments = $Arguments
        }
        $Shortcut.WorkingDirectory = $ProjectRoot
        $Shortcut.Description = $Description
        $Shortcut.Save()
    }
}

New-ShortcutForTarget `
    -Name "Kamoro - Lancer" `
    -Target (Join-Path $PSHOME "powershell.exe") `
    -Arguments "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$LauncherScript`"" `
    -Description "Lancer Kamoro Reservation Facturation avec mise a jour locale"

New-ShortcutForTarget `
    -Name "Kamoro - Arreter" `
    -Target (Join-Path $ProjectRoot "Arreter-Kamoro-Docker.bat") `
    -Description "Arreter Kamoro Reservation Facturation"

Write-Host ""
Write-Host "[OK] Deux raccourcis ont ete crees sur le Bureau :"
Write-Host " - Kamoro - Lancer"
Write-Host " - Kamoro - Arreter"
