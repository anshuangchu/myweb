#!/bin/bash
cd /var/www/myweb
git pull origin main 2>/dev/null || echo 'no remote yet'
chown -R www-data:www-data .
systemctl restart php8.3-fpm
echo 'deploy done'
