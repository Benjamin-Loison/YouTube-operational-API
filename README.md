# YouTube operational API
YouTube operational API works when [YouTube Data API v3](https://developers.google.com/youtube/v3) fails.

[API website](https://yt.lemnoslife.com)

## Install your own instance of the API:

1. If not already hosting a website, run in a terminal:

### On Linux (Ubuntu, Debian and Mint):

```
sudo apt update
sudo apt install apache2 php
sudo a2enmod rewrite
```

Replace `AllowOverride None` with `AllowOverride All` in `<Directory /var/www/>` in `/etc/apache2/apache2.conf`.

Then run:

```
sudo service apache2 start
```

### On Windows:

Download and run [WampServer 3](https://sourceforge.net/projects/wampserver/files/latest/download).

### On MacOS:

On MacOS (Intel) use `/usr/local/` instead of `/opt/homebrew/`.

```zsh
brew install apache2 php

echo 'LoadModule php_module /opt/homebrew/opt/php/lib/httpd/modules/libphp.so
Include /opt/homebrew/etc/httpd/extra/httpd-php.conf' >> /opt/homebrew/etc/httpd/httpd.conf

echo '<IfModule php_module>
  <FilesMatch \.php$>
    SetHandler application/x-httpd-php
  </FilesMatch>

  <IfModule dir_module>
    DirectoryIndex index.php
  </IfModule>
</IfModule>' >> /opt/homebrew/etc/httpd/extra/httpd-php.conf

sed -i '' 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /opt/homebrew/etc/httpd/httpd.conf
```

Replace `AllowOverride none` with `AllowOverride all` in `<Directory "/opt/homebrew/var/www">` in `/opt/homebrew/etc/httpd/httpd.conf`.

Then run:

```
brew services start httpd
```

2. Now that you are hosting a website, get the current working directory of your terminal into the folder that is online.

- On Linux, use `cd /var/www/html/`
- On Windows, use `cd C:\wamp64\www\`
- On MacOS, use `cd /opt/homebrew/var/www/`

3. Clone this repository by using `git clone https://github.com/Benjamin-Loison/YouTube-operational-API`

4. Verify that your API instance is reachable by trying to access:

- On Linux and Windows: http://localhost/YouTube-operational-API/
- On MacOS: http://localhost:8080/YouTube-operational-API/

If you want me to advertise your instance (if you have opened your port, and have a fixed IP address or a domain name), please use below contacts.

## Contact:

- [Matrix](https://matrix.to/#/#youtube-operational-api:matrix.org)
- [Discord](https://discord.gg/pDzafhGWzf)

## Contributing:

See [`CONTRIBUTING.md`](https://github.com/Benjamin-Loison/YouTube-operational-API/blob/main/CONTRIBUTING.md).
