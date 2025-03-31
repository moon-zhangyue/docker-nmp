@echo off
echo 正在清理 Cursor 缓存...

:: 设置 Cursor 缓存目录
set CURSOR_CACHE=%LOCALAPPDATA%\Cursor\Cache
set CURSOR_GPUCACHE=%LOCALAPPDATA%\Cursor\GPUCache
set CURSOR_COOKIES=%LOCALAPPDATA%\Cursor\Cookies
set CURSOR_SESSION=%LOCALAPPDATA%\Cursor\Session Storage
set CURSOR_LOCAL=%LOCALAPPDATA%\Cursor\Local Storage

:: 关闭 Cursor 进程
taskkill /F /IM "Cursor.exe" 2>nul
timeout /t 2 /nobreak >nul

:: 清理缓存目录
echo 清理主缓存目录...
if exist "%CURSOR_CACHE%" rd /s /q "%CURSOR_CACHE%"
if exist "%CURSOR_GPUCACHE%" rd /s /q "%CURSOR_GPUCACHE%"
if exist "%CURSOR_COOKIES%" rd /s /q "%CURSOR_COOKIES%"
if exist "%CURSOR_SESSION%" rd /s /q "%CURSOR_SESSION%"
if exist "%CURSOR_LOCAL%" rd /s /q "%CURSOR_LOCAL%"

:: 清理临时文件
echo 清理临时文件...
del /f /q "%TEMP%\Cursor*.*" 2>nul
del /f /q "%TEMP%\cursor*.*" 2>nul

:: 清理日志文件
echo 清理日志文件...
del /f /q "%LOCALAPPDATA%\Cursor\*.log" 2>nul

echo 清理完成！
echo 请重新启动 Cursor 以应用更改。
pause 