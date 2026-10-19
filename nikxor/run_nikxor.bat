@echo off
title NiKXor Shop Management System - Ahmad Najeem Nik
color 0b
echo ================================================================
echo     NiKXor - Smart Shop Management System (سیستم دکانداری)
echo     Developer: Ahmad Najeem Nik (+93783788278)
echo ================================================================
echo.
echo [*] Checking local IP address for Wi-Fi access...
for /f "tokens=4" %%a in ('route print ^| findstr 0.0.0.0.*0.0.0.0 ^| findstr /v "255.255.255.255"') do (
    set LOCAL_IP=%%a
)

echo.
echo [OK] NiKXor is starting...
echo [*] Local Computer: http://localhost:8000
if defined LOCAL_IP (
    echo [*] Mobile / Other Devices on Wi-Fi: http://%LOCAL_IP%:8000
) else (
    echo [*] Mobile / Other Devices: Connect to Wi-Fi, run 'ipconfig' and open http://[YOUR-IP]:8000
)
echo.
echo Press Ctrl+C in this window to stop the application.
echo ================================================================
echo.

start "" "http://localhost:8000"
php -S 0.0.0.0:8000 -t .
pause
