$ErrorActionPreference = "Stop"

$ProjectRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
$DesktopCandidates = @(
    [Environment]::GetFolderPath("DesktopDirectory"),
    [Environment]::GetFolderPath("Desktop")
) | Where-Object { $_ -and (Test-Path $_) } | Select-Object -Unique
$Shell = New-Object -ComObject WScript.Shell
$LauncherBatch = Join-Path $ProjectRoot "Lancer-Kamoro-Docker.bat"

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
        $Shortcut.Arguments = if ($Arguments) { $Arguments } else { "" }
        $Shortcut.WorkingDirectory = $ProjectRoot
        $Shortcut.Description = $Description
        $Shortcut.Save()
    }
}

New-ShortcutForTarget `
    -Name "Kamoro - Lancer" `
    -Target $LauncherBatch `
    -Description "Lancer Kamoro Reservation Facturation"

New-ShortcutForTarget `
    -Name "Kamoro - Arreter" `
    -Target (Join-Path $ProjectRoot "Arreter-Kamoro-Docker.bat") `
    -Description "Arreter Kamoro Reservation Facturation"

Write-Host ""
Write-Host "[OK] Deux raccourcis ont ete crees sur le Bureau :"
Write-Host " - Kamoro - Lancer"
Write-Host " - Kamoro - Arreter"
