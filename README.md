# YouTube operational API
YouTube operational API works when [YouTube Data API v3](https://developers.google.com/youtube/v3) fails.

[API website](https://yt.lemnoslife.com)

## Install your own instance of the API:

1. If not already hosting a website, run in a terminal:

### On Linux (Ubuntu, Debian and Mint):

```
apt update
sudo apt install apache2 php
sudo service apache2 start
```

### On MacOS (Apple Silicon):

```
brew install apache2 php

echo 'LoadModule php_module /opt/homebrew/opt/php/lib/httpd/modules/libphp.so
Include /opt/homebrew/etc/httpd/extra/httpd-php.conf' >> /opt/homebrew/etc/httpd/httpd.conf

echo '<IfModule php_module>
  <FilesMatch \.php$>
    SetHandler application/x-httpd-php
  </FilesMatch>

  <IfModule dir_module>
    DirectoryIndex index.html index.php
  </IfModule>
</IfModule>' >> /opt/homebrew/etc/httpd/extra/httpd-php.conf

brew services start httpd
```

2. Now that you are hosting a website, get the current working directory of your terminal into the folder that is online.

- On Linux, use `cd /var/www/html/`
- On MacOS, use `cd /opt/homebrew/var/www/`

3. Clone this repository by using `git clone https://github.com/Benjamin-Loison/YouTube-operational-API`

    Note: If you are running a php version >= 8, remove `str_contains` and `str_starts_with` from `common.php`

4. Verify that your API instance is reachable by trying to access:

- On Linux: http://localhost/YouTube-operational-API/
- On MacOS: http://localhost:8080/YouTube-operational-API/

If you want me to advertise your instance (if you have opened your port, and have a fixed IP address or a domain name), please use below contacts.

## Contact:

- [Matrix](https://matrix.to/#/#youtube-operational-api:matrix.org)
- [Discord](https://discord.gg/pDzafhGWzf)
`
