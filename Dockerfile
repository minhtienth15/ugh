FROM php:8.1-cli

# Copy all files into /var/www/html
COPY . /var/www/html
WORKDIR /var/www/html

# Install curl for getlinkplay
RUN apt-get update && apt-get install -y curl

CMD ["php", "-S", "0.0.0.0:10000", "-t", "/var/www/html"]
