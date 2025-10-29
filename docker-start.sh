#!/bin/bash
# docker-start.sh

# Ensure PORT is set (Railway always provides it)
export PORT=${PORT:-80}

# Update Apache to listen on the correct port
echo "Listen ${PORT}" > /etc/apache2/ports.conf

# Start Apache
apache2ctl -D FOREGROUND
