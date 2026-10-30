# VideoTube
   #### video uploading site just like YouTube 
   
---

### Table of Contents

- [Description](#description)
- [Preview](#preview)
- [How To Use](#how-to-use)
- [Requirements](#requirements)
- [License](#license)


---

## Description

VideoTube is a video uploading site containing standard features of YouTube that enables you to launch your video and other features like:
 - video thumbnail generation 
 - server side video conversion
 - full video player</li>
 - likes + dislikes on videos
 - full comment system with likes/dislikes, replies etc.
 - video details editing
 - account creation
 - and <strong>much</strong> more!

#### Technologies

- HTML
- CSS
- JavaScript
- JQuery
- PHP
- MySQL

[Back To The Top](#videotube)

---

## Preview
<img src = 'https://github.com/manoharys/VideoTube/blob/master/preview.gif'>

#### Screenshots
<img src='https://github.com/manoharys/VideoTube/blob/master/screenshot/signUp.JPG'>
<img src='https://github.com/manoharys/VideoTube/blob/master/screenshot/signIn.JPG'>



## How To Use

#### Installation

Follow [INSTALL.md](INSTALL.md) for protected browser setup. Use a fresh empty database; the installer creates the complete schema and your first account.

[Back To The Top](#videotube)

---
## Requirements

- **PHP 8.4 or newer** (older releases are rejected by the application bootstrap).
- PHP extensions: PDO and PDO MySQL. PDO SQLite is also required for development tests.
- MySQL or MariaDB, with the database imported from `database/schema.sql`.
- FFmpeg and FFprobe for video metadata and thumbnails. Set executable paths in the installer or VIDEOTUBE_FFMPEG and VIDEOTUBE_FFPROBE.
- Apache or another PHP-capable web server, and a modern browser.

Optional database environment variables: `VIDEOTUBE_DSN`, `VIDEOTUBE_DB_USER`, and `VIDEOTUBE_DB_PASSWORD`. Without these, the original local `VideoTube` database defaults apply.

### Verify PHP compatibility

```sh
composer validate --no-check-publish
composer check-platform-reqs
composer test
```

CI lints every PHP file and runs regression checks with all PHP warnings and deprecations treated as failures on PHP 8.4 and 8.5. Future PHP releases must be tested before production use; the minimum-version constraint does not guarantee compatibility with unreleased versions.

This upgrade does not integrate phpLD features. Existing database setup and upload limitations are not resolved by changing the PHP runtime requirement.

[Back To The Top](#videotube)
## License
      MIT License

      Copyright (c) 2020 Manohar

      Permission is hereby granted, free of charge, to any person obtaining a copy
      of this software and associated documentation files (the "Software"), to deal
      in the Software without restriction, including without limitation the rights
      to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
      copies of the Software, and to permit persons to whom the Software is
      furnished to do so, subject to the following conditions:

      The above copyright notice and this permission notice shall be included in all
      copies or substantial portions of the Software.

      THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
      IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
      FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
      AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
      LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
      OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
      SOFTWARE.


[Back To The Top](#videotube)
