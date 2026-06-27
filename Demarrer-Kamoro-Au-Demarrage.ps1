$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$LocalWebRoot = Join-Path $ProjectRoot "hestia_app/build/web"
$LocalWebServerScript = Join-Path $ProjectRoot "Start-Kamoro-LocalWebServer.ps1"
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

function Invoke-GitPullWithTimeout {
    param(
        [int]$TimeoutSeconds = 8
    )

    $git = Get-Command git -ErrorAction SilentlyContinue
    if (-not $git) {
        throw "Git n'est pas disponible dans le PATH."
    }

    $gitProcess = Start-Process -FilePath $git.Path -ArgumentList @(
        "-C",
        $ProjectRoot,
        "pull",
        "--ff-only",
        "origin",
        "main"
    ) -PassThru -WindowStyle Hidden -RedirectStandardOutput $StdOutLog -RedirectStandardError $StdErrLog

    if (-not $gitProcess.WaitForExit($TimeoutSeconds * 1000)) {
        try {
            $gitProcess.Kill()
        } catch {
            # Ignore les erreurs de terminaison forcée.
        }

        return @{
            TimedOut = $true
            ExitCode = $null
        }
    }

    return @{
        TimedOut = $false
        ExitCode = $gitProcess.ExitCode
    }
}

function Start-LocalFallback {
    if (-not (Test-Path (Join-Path $LocalWebRoot "index.html"))) {
        $flutter = Get-Command flutter -ErrorAction SilentlyContinue
        if (-not $flutter) {
            throw "Le build Flutter local est introuvable dans '$LocalWebRoot' et Flutter n'est pas disponible."
        }

        Write-Log "Build Flutter local manquant. Reconstruction en cours."
        $flutterProcess = Start-Process -FilePath $flutter.Path -ArgumentList @(
            "build",
            "web",
            "--pwa-strategy=none",
            "--dart-define=API_BASE_URL=http://127.0.0.1:8000"
        ) -WorkingDirectory (Join-Path $ProjectRoot "hestia_app") -PassThru -WindowStyle Hidden -RedirectStandardOutput $StdOutLog -RedirectStandardError $StdErrLog

        $flutterProcess.WaitForExit()
        if ($flutterProcess.ExitCode -ne 0) {
            throw "La reconstruction Flutter locale a echoue."
        }
    }

    if (-not (Test-Path $LocalWebServerScript)) {
        throw "Le script de secours local est introuvable."
    }

    Write-Log "Lancement du fallback local."
    Start-Process -FilePath (Join-Path $PSHOME "powershell.exe") `
        -ArgumentList @(
            "-NoProfile",
            "-ExecutionPolicy",
            "Bypass",
            "-WindowStyle",
            "Hidden",
            "-File",
            $LocalWebServerScript,
            "-Root",
            $LocalWebRoot,
            "-Port",
            "8080"
        ) `
        -WorkingDirectory $ProjectRoot | Out-Null

    Start-Sleep -Seconds 2
    Start-Process "http://127.0.0.1:8080/index.html" | Out-Null
    Write-Log "Fallback local lance."
}

function Wait-ForDocker {
    param([int]$Attempts = 10)

    for ($i = 1; $i -le $Attempts; $i++) {
        try {
            docker info | Out-Null
            return
        } catch {
            Start-Sleep -Seconds 3
        }
    }

    throw "Docker n'est pas pret apres $Attempts tentatives."
}

Set-Location $ProjectRoot

try {
    Write-Log "Mise a jour du depot local..."
    $gitUpdate = Invoke-GitPullWithTimeout -TimeoutSeconds 8
    if ($gitUpdate.TimedOut) {
        Write-Log "Timeout git apres 8 secondes. On continue avec le depot local courant."
    } elseif ($gitUpdate.ExitCode -ne 0) {
        Write-Log "Echec du git pull avec le code $($gitUpdate.ExitCode). On continue avec le depot local courant."
    } else {
        Write-Log "Depot local mis a jour depuis origin/main."
    }

    Write-Log "Attente de Docker Desktop..."
    Wait-ForDocker

    Write-Log "Lancement de docker compose..."
    $Process = Start-Process -FilePath "docker" -ArgumentList @("compose", "up", "-d", "--build") -WorkingDirectory $ProjectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput $StdOutLog -RedirectStandardError $StdErrLog
    $Process.WaitForExit()

    if ($Process.ExitCode -ne 0) {
        Write-Log "Echec de docker compose avec le code $($Process.ExitCode)."
        throw "Lancement automatique Kamoro echoue."
    }

    Write-Log "Kamoro est lance via Docker."
} catch {
    Write-Log "Fallback local active: $($_.Exception.Message)"
    Start-LocalFallback
}
