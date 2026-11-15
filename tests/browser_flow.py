"""Real HTTP installation, authorization and FFmpeg regression checks (disposable CI DB)."""
import json, http.cookiejar, os, pathlib, re, shutil, subprocess, time, urllib.request, urllib.parse, urllib.error
root=pathlib.Path(__file__).resolve().parents[1]
os.chdir(root)
assert not (root/'includes/local.php').exists(), 'Run only in a disposable checkout'
subprocess.run(['php','-r', '$db=new PDO("mysql:host=127.0.0.1","root","test"); $db->exec("CREATE DATABASE videotube_http CHARACTER SET utf8mb4");'],check=True)
outbox=pathlib.Path('/tmp/videotube-mail');outbox.mkdir(exist_ok=True)
media=pathlib.Path('/tmp/videotube-media');media.mkdir(exist_ok=True)
env=dict(os.environ,VIDEOTUBE_INSTALL_TOKEN='ci-only-install-token-01234567890123456789',VIDEOTUBE_ENV='test',VIDEOTUBE_BASE_URL='http://127.0.0.1:8089',VIDEOTUBE_TEST_MAIL_DIR=str(outbox),VIDEOTUBE_TRUSTED_PROXIES='127.0.0.1')
def mail_link(purpose,email):
 messages=[json.loads(p.read_text()) for p in sorted(outbox.glob('*.json'),key=lambda p:p.stat().st_mtime_ns)]
 matches=[m for m in messages if m['purpose']==purpose and m['to']==email]
 assert matches, (purpose,email)
 return matches[-1]['url'].split('8089/')[1]
def verify(client,email):
 link=mail_link('verify',email);client.page(link);params=dict(urllib.parse.parse_qsl(urllib.parse.urlsplit(link).query))
 assert b'Account updated' in client.post('account.php',params)
 assert b'Invalid or expired' in client.post('account.php',params)

log=open('/tmp/videotube-http.log','w+')
server=subprocess.Popen(['php','-d','upload_max_filesize=50M','-d','post_max_size=52M','-S','127.0.0.1:8089'],env=env,stdout=log,stderr=log)
class Client:
 def __init__(self): self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())); self.csrf=''
 def request(self,path,data=None,headers=None,expect=200):
  if isinstance(data,dict): data=urllib.parse.urlencode(data).encode()
  request=urllib.request.Request('http://127.0.0.1:8089/'+path,data=data,headers=headers or {})
  try: response=self.opener.open(request,timeout=150)
  except urllib.error.HTTPError as e: response=e
  body=response.read(); assert response.status==expect,(path,response.status,body[:300]); self.headers=response.headers; return body
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
 fields=dict(token=env['VIDEOTUBE_INSTALL_TOKEN'],host='127.0.0.1',database='videotube_http',dbuser='root',dbpass='test',username='owner',email='owner@example.test',password='a!StrongPassword123',ffmpeg=shutil.which('ffmpeg'),ffprobe=shutil.which('ffprobe'),storage=str(media))
 assert b'Installation complete' in owner.post('install.php',fields)
 assert (root/'includes/local.php').stat().st_mode & 0o077 == 0
 owner.request('install.php',expect=403)
 owner.page('signIn.php'); owner.post('signIn.php',dict(submitButton=1,username='OWNER',password=fields['password']))
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
 assert 'pending' in owner.page('jobs.php')
 subprocess.run(['php','bin/worker.php','--once'],env=env,check=True)
 assert 'complete' in owner.page('jobs.php')
 # Media is written to the configured storage directory under unguessable names, never into the web root.
 stored=[p.name for p in (media/'videos').glob('*.mp4')]
 assert len(stored)==1 and re.fullmatch(r'[a-f0-9]{48}\.mp4',stored[0]),stored
 assert not (root/'uploads/videos').exists() and not list((media/'queue').glob('*'))
 assert 'Private fixture' in owner.page('profile.php?username=owner')
 owner.page('watch.php?id=1'); owner.request('stream.php?id=1',headers={'Range':'bytes=0-9'},expect=206)
 owner.request('stream.php?id=1',headers={'Range':'bytes=99999999-'},expect=416)
 owner.request('thumbnail.php?id=1')
 # Media carries validators: an unchanged file is answered with 304, and private media is never cached blindly.
 assert 'no-cache' in owner.headers['Cache-Control']
 owner.request('thumbnail.php?id=1',headers={'If-None-Match':owner.headers['ETag']},expect=304)
 guest=Client();assert 'Private fixture' not in guest.page('index.php')
 for path in ['watch.php?id=1','stream.php?id=1','thumbnail.php?id=1']: guest.request(path,expect=404)
 guest.post('ajax/likeVideo.php',{'videoId':1},expect=403)
 other=Client();other.page('signUp.php')
 other.post('signUp.php',dict(submitButton=1,firstName='Other',lastName='User',username='other',email='other@example.test',email2='other@example.test',password='other!Password123',password2='other!Password123'))
 other.page('index.php');other.post('ajax/likeVideo.php',dict(videoId=1),expect=403)
 verify(other,'other@example.test')
 other.page('index.php');other.request('editVideo.php?videoId=1',expect=403)
 other.request('stream.php?id=1',expect=404)
 other.post('ajax/likeVideo.php',dict(videoId=1),expect=404)
 owner.page('editVideo.php?videoId=1');owner.post('editVideo.php?videoId=1',dict(title='Public fixture',description='<script>bad()</script>',privacy=1,category=16))
 assert 'Public fixture' in guest.page('search.php?term=Public')
 assert '<script>bad()</script>' not in guest.page('watch.php?id=1')
 owner.post('ajax/postComment.php',dict(videoId=1,postedBy='impersonated',responseTo=0,commentText='<script>comment()</script>'))
 # Repeat visits by the same viewer count once; the uploader's own visits never count.
 owner.page('watch.php?id=1');guest.page('watch.php?id=1')
 assert '<span class="viewCount">1 views</span>' in guest.page('watch.php?id=1')
 guest.request('thumbnail.php?id=1');assert 'max-age=3600' in guest.headers['Cache-Control']
 body=guest.page('watch.php?id=1');assert '<script>comment()</script>' not in body and '&lt;script&gt;comment()' in body
 # Pages carry a strict policy and no inline script or style for it to block.
 assert "script-src 'self'" in guest.headers['Content-Security-Policy'] and 'unsafe-inline' not in guest.headers['Content-Security-Policy']
 assert not re.search(r'\son[a-z]+\s*=|<script(?![^>]*\ssrc=)|\sstyle\s*=|<style',body), 'inline script or style in watch page'
 # A reply written under a reply is addressed to the thread's top-level comment.
 reply=owner.post('ajax/postComment.php',dict(videoId=1,responseTo=1,commentText='First reply')).decode()
 assert 'data-reply-to="1"' in reply and 'data-comment-id="2"' in reply
 owner.post('ajax/postComment.php',dict(videoId=1,responseTo=2,commentText='Nested'),expect=422)
 assert 'First reply' in owner.post('ajax/getCommentReplies.php',dict(videoId=1,commentId=1)).decode()
 assert json.loads(owner.post('ajax/disLikeVideo.php',dict(videoId=1)))['disLikes']==1
 assert json.loads(owner.post('ajax/disLikeVideo.php',dict(videoId=1)))['disLikes']==-1
 owner.post('ajax/likeComment.php',dict(videoId=1,commentId=1))
 owner.post('ajax/disLikeComment.php',dict(videoId=1,commentId=1))
 owner.post('ajax/getCommentReplies.php',dict(videoId=1,commentId=999),expect=404)
 owner.post('ajax/subscribe.php',dict(userTo='other',userFrom='impersonated'))
 owner.post('ajax/subscribe.php',dict(userTo='owner',userFrom='other'),expect=422)
 stale=Client();stale.page('signIn.php');stale.post('signIn.php',dict(submitButton=1,username='owner',password=fields['password']));stale.page('index.php')
 owner.page('settings.php')
 # Changing the recovery address needs the current password, warns the old address and re-verifies the new one.
 assert b'current password' in owner.post('settings.php',dict(saveDetailsButton=1,firstName='Mohd Elfie',lastName='Site Owner',email='Mixed.Case@example.test'))
 assert b'Details updated successfully' in owner.post('settings.php',dict(saveDetailsButton=1,firstName='Mohd Elfie',lastName='Site Owner',email='Mixed.Case@example.test',currentPassword=fields['password']))
 assert any(json.loads(p.read_text()).get('purpose')=='email-changed' and json.loads(p.read_text())['to']=='owner@example.test' for p in outbox.glob('*.json'))
 mail_link('verify','Mixed.Case@example.test')
 assert b'Password updated successfully' in owner.post('settings.php',dict(savePasswordButton=1,oldPassword=fields['password'],newPassword='changed!Password123',newPassword2='changed!Password123'))
 assert 'Mohd Elfie Site Owner' in owner.page('profile.php?username=owner')
 stale.post('ajax/likeVideo.php',dict(videoId=1),expect=403)
 owner.page('logout.php');owner.post('logout.php',{})
 owner.page('index.php');owner.post('ajax/likeVideo.php',dict(videoId=1),expect=403)
 # Recovery tokens require POST, are single use, and cannot reset another account.
 guest.page('account.php');guest.post('account.php',dict(action='reset',email='other@example.test'))
 link=mail_link('reset','other@example.test');guest.page(link);params=dict(urllib.parse.parse_qsl(urllib.parse.urlsplit(link).query));params['password']='reset!Password123'
 assert b'Account updated' in guest.post('account.php',params)
 assert b'Invalid or expired' in guest.post('account.php',params)
 other.page('index.php');other.post('ajax/likeVideo.php',dict(videoId=1),expect=403)
 other.page('signIn.php');other.post('signIn.php',dict(submitButton=1,username='other',password='reset!Password123'));other.page('index.php')
 other.request('admin.php',expect=403)
 owner.page('signIn.php');owner.post('signIn.php',dict(submitButton=1,username='owner',password='changed!Password123'));owner.page('admin.php')
 # Comments load a page at a time; only the author may delete one, and its replies go with it.
 for n in range(22): other.post('ajax/postComment.php',dict(videoId=1,responseTo=0,commentText='Paged %02d'%n))
 first=guest.page('watch.php?id=1');assert first.count('class="itemContainer"')==20 and 'data-action="loadComments"' in first and 'Paged 21' in first and 'Paged 01' not in first
 before=re.search(r'data-before="(\d+)"',first)[1]
 rest=guest.post('ajax/getComments.php',dict(videoId=1,before=before)).decode();assert 'Paged 01' in rest and 'loadComments' not in rest
 owner_page=owner.page('watch.php?id=1')
 other_comment=re.search(r'data-comment-id="(\d+)"',first)[1]
 assert 'data-action="deleteComment"' not in owner_page.split('First reply')[0].split('data-comment-id="%s"'%other_comment)[1].split('itemContainer')[0]
 owner.post('ajax/deleteComment.php',dict(videoId=1,commentId=other_comment),expect=403)
 other.post('ajax/deleteComment.php',dict(videoId=1,commentId=other_comment))
 assert 'Paged 21' not in guest.page('watch.php?id=1')
 owner.page('admin.php')
 owner.post('admin.php',dict(action='suspend',id=1),expect=422)
 owner.post('admin.php',dict(action='suspend',id=2));owner.post('admin.php',dict(action='restore',id=2))
 other.post('ajax/likeVideo.php',dict(videoId=1),expect=403)
 owner.post('admin.php',dict(action='remove_comment',id=1))
 owner.post('admin.php',dict(action='remove_video',id=1))
 guest.request('stream.php?id=1',expect=404)
 # Oversized bodies are reported as such rather than as a session error.
 assert b'larger than this server accepts' in owner.request('processing.php',b'x'*(53*1024*1024),{'Content-Type':'application/x-www-form-urlencoded'},expect=413)
 # Repeated failures lock sign-in for that account, from any client, even with the right password.
 # Each attempt arrives through the trusted proxy from a different address, so the per-address limit never trips.
 def sign_in(address,password,expect=200):
  client=Client();client.page('signIn.php')
  return client.request('signIn.php',dict(submitButton=1,username='OTHER',password=password,csrf=client.csrf),{'X-Forwarded-For':'203.0.113.9, '+address},expect)
 for attempt in range(10): assert b'incorrect' in sign_in('198.51.100.%d'%attempt,'wrong-password')
 assert b'Too many failed sign-in attempts' in sign_in('198.51.100.200','reset!Password123',429)
 print('HTTP installation, CSRF, login/logout, upload, edit, privacy, ranges, comments and votes passed.')
finally:
 server.terminate();server.wait(timeout=10);log.flush();log.seek(0)
 # PHP itself logs the deliberately oversized request; every other warning is a failure.
 output='\n'.join(line for line in log.read().splitlines() if 'POST Content-Length of' not in line)
 if 'Fatal error' in output or 'Warning:' in output or 'Uncaught' in output: print(output);raise AssertionError('PHP server emitted errors')
