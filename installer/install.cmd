@echo off
setlocal
set "DEST=%LOCALAPPDATA%\Family Money"
mkdir "%DEST%" 2>nul
copy /Y "%~dp0FamilyMoney.cmd" "%DEST%\FamilyMoney.cmd" >nul
copy /Y "%~dp0favicon.ico" "%DEST%\FamilyMoney.ico" >nul
powershell -NoProfile -ExecutionPolicy Bypass -Command "$w = New-Object -ComObject WScript.Shell; foreach ($place in @([Environment]::GetFolderPath('Desktop'), [Environment]::GetFolderPath('Programs'))) { if ([string]::IsNullOrWhiteSpace($place)) { continue }; $s = $w.CreateShortcut((Join-Path $place 'Family Money.lnk')); $s.TargetPath = Join-Path $env:LOCALAPPDATA 'Family Money\FamilyMoney.cmd'; $s.WorkingDirectory = Join-Path $env:LOCALAPPDATA 'Family Money'; $s.IconLocation = Join-Path $env:LOCALAPPDATA 'Family Money\FamilyMoney.ico'; $s.Description = 'Family Money'; $s.Save() }"
