import re, io, sys
s = io.open('build/web/flutter_bootstrap.js', encoding='utf-8').read()
m = re.search(r'useLocalCanvasKit\W{0,3}(true|false)', s)
print('useLocalCanvasKit setting:', m.group(0) if m else 'NOT SET (defaults to CDN)')
# 找 buildConfig 里是否声明了本地 canvaskit
for key in ('fileBasePattern', 'canvasKitBaseUrl', 'useLocalCanvasKit'):
    for mm in re.finditer(key + r'\W{0,4}[^,}\n]{0,80}', s):
        print(key, '=>', mm.group(0)[:100])
