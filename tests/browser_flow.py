"""Real HTTP installation, authorization and FFmpeg regression checks (disposable CI DB)."""
import http.cookiejar, os, pathlib, re, shutil, subprocess, time, urllib.request, urllib.parse, urllib.error
root=pathlib.Path(__file__).resolve().parents[1]
os.chdir(root)
assert not (root/'includes/local.php').exists(), 'Run only in a disposable checkout'
subprocess.run(['php','-r', '$db=new PDO("mysql:host=127.0.0.1","root","test"); $db->exec("CREATE DATABASE videotube_http CHARACTER SET utf8mb4");'],check=True)
env=dict(os.environ,VIDEOTUBE_INSTALL_TOKEN='ci-only-install-token-01234567890123456789')
log=open('/tmp/videotube-http.log','w+')
server=subprocess.Popen(['php','-d','upload_max_filesize=50M','-d','post_max_size=52M','-S','127.0.0.1:8089'],env=env,stdout=log,stderr=log)
class Client:
 def __init__(self): self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())); self.csrf=''
 def request(self,path,data=None,headers=None,expect=200):
  if isinstance(data,dict): data=urllib.parse.urlencode(data).encode()
  request=urllib.request.Request('http://127.0.0.1:8089/'+path,data=data,headers=headers or {})
  try: response=self.opener.open(request,timeout=150)
  except urllib.error.HTTPError as e: response=e
  body=response.read(); assert response.status==expect,(path,response.status,body[:300]); return body
 def page(self,path):
  body=self.request(path).decode(); match=re.search(r'name="csrf" value="([a-f0-9]+)"',body) or re.search(r'name="csrf-token" content="([a-f0-9]+)"',body)
  if match: self.csrf=match[1]
  return body
 def post(self,path,data,expect=200): return self.request(path,dict(data,csrf=self.csrf),expect=expect)
try:
 for _ in range(50):
  try: owner=Client(); owner.page('install.php'); break
  except urllib.error.URLError: time.sleep(.1)
 owner.request('install.php',{},expect=403)
 fields=dict(token=env['VIDEOTUBE_INSTALL_TOKEN'],host='127.0.0.1',database='videotube_http',dbuser='root',dbpass='test',username='owner',email='owner@example.test',password='a!StrongPassword123',ffmpeg=shutil.which('ffmpeg'),ffprobe=shutil.which('ffprobe'))
 assert b'Installation complete' in owner.post('install.php',fields)
 assert (root/'includes/local.php').stat().st_mode & 0o077 == 0
 owner.request('install.php',expect=403)
 owner.page('signIn.php'); owner.post('signIn.php',dict(submitButton=1,username='owner',password=fields['password']))
 owner.page('upload.php')
 subprocess.run(['ffmpeg','-v','error','-f','lavfi','-i','color=c=blue:s=160x120:d=1','-c:v','libx264','-y','/tmp/videotube-test.mp4'],check=True)
 def upload(content):
  boundary='VideoTubeBoundary'; data=b''
  for key,value in dict(csrf=owner.csrf,titleInput='Private fixture',descriptionInput='Test description',categoryInput='16',privacyInput='0').items():
   data+=f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()
  data+=f'--{boundary}\r\nContent-Disposition: form-data; name="fileInput"; filename="test.mp4"\r\nContent-Type: video/mp4\r\n\r\n'.encode()+content+f'\r\n--{boundary}--\r\n'.encode()
  return data,{'Content-Type':'multipart/form-data; boundary='+boundary}
 data,headers=upload(b'not a video');owner.request('processing.php',data,headers,expect=422)
 data,headers=upload(pathlib.Path('/tmp/videotube-test.mp4').read_bytes());owner.request('processing.php',data,headers)
 assert 'Private fixture' in owner.page('profile.php?username=owner')
 owner.page('watch.php?id=1'); owner.request('stream.php?id=1',headers={'Range':'bytes=0-9'},expect=206)
 owner.request('stream.php?id=1',headers={'Range':'bytes=99999999-'},expect=416)
 owner.request('thumbnail.php?id=1')
 guest=Client();assert 'Private fixture' not in guest.page('index.php')
 for path in ['watch.php?id=1','stream.php?id=1','thumbnail.php?id=1']: guest.request(path,expect=404)
 guest.post('ajax/likeVideo.php',{'videoId':1},expect=403)
 owner.page('editVideo.php?videoId=1');owner.post('editVideo.php?videoId=1',dict(title='Public fixture',description='<script>bad()</script>',privacy=1,category=16))
 assert 'Public fixture' in guest.page('search.php?term=Public')
 assert '<script>bad()</script>' not in guest.page('watch.php?id=1')
 owner.post('ajax/postComment.php',dict(videoId=1,postedBy='impersonated',responseTo=0,commentText='<script>comment()</script>'))
 body=guest.page('watch.php?id=1');assert '<script>comment()</script>' not in body and '&lt;script&gt;comment()' in body
 owner.post('ajax/likeComment.php',dict(videoId=1,commentId=1))
 owner.post('ajax/disLikeComment.php',dict(videoId=1,commentId=1))
 owner.post('ajax/getCommentReplies.php',dict(videoId=1,commentId=999),expect=404)
 owner.page('logout.php');owner.post('logout.php',{})
 owner.page('index.php');owner.post('ajax/likeVideo.php',dict(videoId=1),expect=403)
 print('HTTP installation, CSRF, login/logout, upload, edit, privacy, ranges, comments and votes passed.')
finally:
 server.terminate();server.wait(timeout=10);log.flush();log.seek(0)
 output=log.read()
 if 'Fatal error' in output or 'Warning:' in output or 'Uncaught' in output: print(output);raise AssertionError('PHP server emitted errors')
