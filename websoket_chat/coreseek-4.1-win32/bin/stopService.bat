@ echo off
f:
cd F:\work\websoket_chat\coreseek-4.1-win32\bin 
searchd.exe --stop
f:
cd F:\work\websoket_chat\coreseek-4.1-win32\bin 
echo %date:~0,4%%date:~5,2%%date:~8,2%%time:~0,2%%time:~3,2%%time:~6,2% >> F:\work\websoket_chat\log.txt

