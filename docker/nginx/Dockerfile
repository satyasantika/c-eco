FROM nginx:1.25-alpine

# Berkas, bukan bind-mount: Docker membuat folder jika path host belum ada.
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
