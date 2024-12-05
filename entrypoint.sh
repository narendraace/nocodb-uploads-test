#!/bin/sh
# Set file and directory permissions
chmod -R 777 /var/www/html/src/uploads
#chown -R www-data:www-data /var/www/html/src/uploads

# Run the original command (e.g., PHP-FPM)
exec "$@"
