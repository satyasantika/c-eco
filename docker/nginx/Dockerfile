# Dipakai jika web dibangun terpisah. Compose produksi memakai
# docker/php/Dockerfile target=web supaya public/ sama dengan image PHP
# (hasil Vite + aset Filament dari composer).
FROM node:22-alpine AS assets

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM nginx:1.25-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=assets /build/public/build /var/www/html/public/build
