@echo off
setlocal

cd /d "%~dp0"

echo.
echo Lancement de Kamoro Reservation Facturation...
echo Le lanceur utilise la copie locale du depot et reconstruit l'application si besoin.
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Demarrer-Kamoro-Au-Demarrage.ps1"

if errorlevel 1 (
    echo.
    echo ERREUR : le lancement a echoue. Consultez le dossier .startup-logs pour plus de details.
    pause
    exit /b 1
)

echo.
echo Application lancee.
exit /b 0
