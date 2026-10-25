# ════════════════════════════════════════════════════════════════
#  DocuMind — Production Dockerfile
#  Base: php:8.2-apache  |  Target: Render / Fly.io / Railway
# ════════════════════════════════════════════════════════════════

FROM php:8.2-apache

# ── System packages ───────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
      ca-certificates \
      curl \
      libpng-dev \
      libjpeg-dev \
      libfreetype6-dev \
      libzip-dev \
      unzip \
      poppler-utils \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ────────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd \
      --with-freetype \
      --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
      pdo \
      pdo_mysql \
      gd \
      zip \
      opcache

# ── PHP runtime limits (inline; no custom php.ini file required) ──────────────
RUN { \
      echo 'upload_max_filesize = 64M'; \
      echo 'post_max_size       = 64M'; \
      echo 'max_execution_time  = 120'; \
      echo 'max_input_time      = 120'; \
      echo 'memory_limit        = 256M'; \
    } > /usr/local/etc/php/conf.d/docmind-limits.ini

# ── OPcache tuning ────────────────────────────────────────────────────────────
RUN { \
      echo 'opcache.enable           = 1'; \
      echo 'opcache.memory_consumption = 128'; \
      echo 'opcache.max_accelerated_files = 8000'; \
      echo 'opcache.revalidate_freq  = 60'; \
      echo 'opcache.fast_shutdown    = 1'; \
    } > /usr/local/etc/php/conf.d/docmind-opcache.ini

# ── Apache: enable mod_rewrite for clean URLs ─────────────────────────────────
RUN a2enmod rewrite

# ── Apache virtual host — allow .htaccess overrides ──────────────────────────
RUN sed -i 's|AllowOverride None|AllowOverride All|g' \
      /etc/apache2/apache2.conf

# ── Copy application source ───────────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ── Ensure uploads directory exists with correct permissions ──────────────────
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# ── SSL CA bundle (used for TiDB Cloud Serverless + Gemini HTTPS calls) ───────
RUN update-ca-certificates

# ── Expose standard HTTP port ─────────────────────────────────────────────────
EXPOSE 80

# ── Health-check (Render waits for 200 before routing traffic) ────────────────
HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

# ── Default command (Apache in foreground) ────────────────────────────────────
CMD ["apache2-foreground"]
