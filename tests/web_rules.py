"""Exercise shipped Apache/Nginx access restrictions on disposable static fixtures."""
import os, pathlib, pwd, grp, shutil, subprocess, tempfile, time, urllib.request, urllib.error
root=pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='videotube-web-') as tmp:
 base=pathlib.Path(tmp);site=base/'site';site.mkdir()
 shutil.copy(root/'.htaccess',site/'.htaccess')
 sensitive=['includes/local.php','database/schema.sql','tests/install.php','bin/worker.php','uploads/videos/private.mp4','.git/config','includes/.install-secret']
 for name in sensitive:
  path=site/name;path.parent.mkdir(parents=True,exist_ok=True);path.write_text('SECRET')
 (site/'index.txt').write_text('public fixture')
 modules='\n'.join(f'LoadModule {name}_module /usr/lib/apache2/modules/mod_{name}.so' for name in ['mpm_event','authz_core','authz_host','rewrite','dir','mime'])
 apache=base/'apache.conf';apache.write_text(f'''ServerRoot "{base}"
PidFile "{base}/apache.pid"
Listen 127.0.0.1:8091
ServerName localhost
User {pwd.getpwuid(os.getuid()).pw_name}
Group {grp.getgrgid(os.getgid()).gr_name}
{modules}
ErrorLog "{base}/apache.log"
TypesConfig /etc/mime.types
DocumentRoot "{site}"
<Directory "{site}">
AllowOverride FileInfo Options AuthConfig
Require all granted
</Directory>
''')
 nginx=base/'nginx.conf';nginx.write_text(f'''daemon off;
pid {base}/nginx.pid;
error_log {base}/nginx.log;
events {{}}
http {{
access_log off;
client_body_temp_path {base}/body;
proxy_temp_path {base}/proxy;
fastcgi_temp_path {base}/fastcgi;
uwsgi_temp_path {base}/uwsgi;
scgi_temp_path {base}/scgi;
server {{
listen 127.0.0.1:8092;
root {site};
include {root}/deploy/nginx.conf.example;
}}
}}
''')
 for cmd,port in [(['apache2','-f',str(apache),'-DFOREGROUND'],8091),(['nginx','-p',tmp,'-c',str(nginx)],8092)]:
  process=subprocess.Popen(cmd,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
  try:
   for _ in range(50):
    try:
     with urllib.request.urlopen(f'http://127.0.0.1:{port}/index.txt') as response: assert response.read()==b'public fixture'
     break
    except urllib.error.URLError:
     if process.poll() is not None: raise AssertionError(process.communicate())
     time.sleep(.1)
   else: raise AssertionError('Web server did not start')
   for path in sensitive:
    try:
     with urllib.request.urlopen(f'http://127.0.0.1:{port}/{path}') as response: raise AssertionError(f'Exposed {path}')
    except urllib.error.HTTPError as e: assert e.code in (403,404),(path,e.code)
   print(cmd[0]+': configuration, SQL, tests, CLI scripts, private media and hidden files denied.')
  finally:
   process.terminate();process.communicate(timeout=10)
