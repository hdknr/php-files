# Simple File Downloader

## nginx

### sites-enabled

sites-enabeled/0.upstream.conf:

~~~conf
upstream upstream_dev.mysite.com {
  server unix:/home/vagrant/.anyenv/envs/phpenv/versions/7.2.10/var/run/phpfpm.mysite.sock;
}
~~~

sites-enabeled/server.conf:

~~~conf
server {
    listen 80;
    listen [::]:80;

    server_name dev.mysite.com;
    set $APP_ROOT "/home/vagrant/projects/mysite/wordpress";

    include sites-available/mysite/root.conf;
    include sites-available/mysite/files.conf;

    add_header 'Access-Control-Allow-Origin' "*";
}
~~~

### sites-available

sites-available/mysite/root.conf:

~~~conf
index index.html;

location / {
    root $APP_ROOT;
    try_files $uri $uri/ /index.php?$args;
    index index.php;

    location ~ \.php$ {
        include sites-available/mysite/php.conf;
    }
}
~~~

sites-available/mysite/php.conf:

~~~conf
fastcgi_split_path_info ^(.+\.php)(/.*)$;
fastcgi_pass upstream_$http_host;
fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
fastcgi_param PATH_INFO $fastcgi_script_name;
fastcgi_param HTTP_PROXY "";
fastcgi_param PHP_VALUE "upload_max_filesize = 200M \n post_max_size=200M";
include fastcgi_params;
~~~

sites-available/mysite/files.conf:

~~~conf
location /files {
    root $APP_ROOT/files/public;
    try_files $uri $uri/ /files/index.php?$args;
    index index.php;

    location ~ ^/files/.+\.php$ {
        include sites-available/mysite/php.conf;
    }
}
location ~ /files/\.data.+ {
    deny all;
    return 404;
}
~~~

## php-fpm

sites-available/mysite/dev.mysite.com/php-fpm.conf:

~~~ini
[global]

[www]
user = vagrant
group = vagrant
listen.owner = ubuntu
listen.group = ubuntu
listen = var/run/phpfpm.mysite.sock
listen.allowed_clients = 127.0.0.1
listen.mode = 0666
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
~~~

~~~bash
$  sudo $(phpenv prefix)/sbin/php-fpm -y /etc/nginx/sites-available/mysite/dev.mysite.com/php-fpm.conf
.
~~~
