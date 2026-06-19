$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$LogDir = Join-Path $ProjectRoot ".startup-logs"
$StdOutLog = Join-Path $LogDir "auto-start.out.log"
$StdErrLog = Join-Path $LogDir "auto-start.err.log"
$LogFile = Join-Path $LogDir "auto-start.log"

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

function Write-Log {
    param([string]$Message)

    $Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $LogFile -Value "[$Timestamp] $Message"
}

function Wait-ForDocker {
    param([int]$Attempts = 60)

    for ($i = 1; $i -le $Attempts; $i++) {
        try {
            docker info | Out-Null
            return
        } catch {
            Start-Sleep -Seconds 5
        }
    }

    throw "Docker n'est pas pret apres $Attempts tentatives."
}

Set-Location $ProjectRoot
Write-Log "Attente de Docker Desktop..."
Wait-ForDocker

Write-Log "Mise a jour du depot local..."
git -C $ProjectRoot pull --ff-only origin main | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Log "Echec du git pull."
    throw "Impossible de recuperer les dernieres modifications."
}

Write-Log "Lancement de docker compose..."
$Process = Start-Process -FilePath "docker" -ArgumentList @("compose", "up", "-d", "--build") -WorkingDirectory $ProjectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput $StdOutLog -RedirectStandardError $StdErrLog
$Process.WaitForExit()

if ($Process.ExitCode -ne 0) {
    Write-Log "Echec de docker compose avec le code $($Process.ExitCode)."
    throw "Lancement automatique Kamoro echoue."
}

Write-Log "Kamoro est lance."
