#!/usr/bin/env bash

# ==============================================================================
# Ottodot Trial Class Booking - Single Command Quick Setup
# ==============================================================================
# Description: Fully provisions SQLite database, dependencies, keys, migrations,
#              seed data, frontend assets, and verifies tests in under 1 minute.
# ==============================================================================

set -e

# Detect target directory (can be run from repository root or trial-class-booking/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -d "$SCRIPT_DIR/trial-class-booking" ]; then
    APP_DIR="$SCRIPT_DIR/trial-class-booking"
else
    APP_DIR="$SCRIPT_DIR"
fi

cd "$APP_DIR"

echo "================================================================="
echo "   Ottodot Trial Class Booking - Single Command Quick Setup      "
echo "================================================================="
echo "Working directory: $APP_DIR"
echo ""

# 1. Check PHP version
PHP_BIN=$(which php || true)
if [ -z "$PHP_BIN" ]; then
    echo "[-] Error: PHP is not installed or not in PATH."
    exit 1
fi
echo "[+] Using PHP: $($PHP_BIN -r 'echo PHP_VERSION;')"

# 2. Check Composer
COMPOSER_BIN=$(which composer || true)
if [ -z "$COMPOSER_BIN" ]; then
    echo "[-] Error: Composer is not installed or not in PATH."
    exit 1
fi

# 3. Environment file setup
if [ ! -f .env ]; then
    echo "[*] Creating .env from .env.example..."
    cp .env.example .env
else
    echo "[+] .env file already exists."
fi

# 4. Install PHP dependencies if vendor is missing
if [ ! -d vendor ]; then
    echo "[*] Installing Composer dependencies..."
    $COMPOSER_BIN install --no-interaction --prefer-dist --optimize-autoloader
else
    echo "[+] Composer dependencies installed."
fi

# 5. Generate Application Key if not set
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo "[*] Generating application key..."
    $PHP_BIN artisan key:generate --force
else
    echo "[+] Application key already set."
fi

# 6. Prepare SQLite Database
echo "[*] Preparing SQLite database..."
mkdir -p database
touch database/database.sqlite

# 7. Run Migrations & Seeders
echo "[*] Running database migrations and seeders..."
$PHP_BIN artisan migrate:fresh --seed --force

# 8. Install NPM dependencies & build Tailwind CSS bundle
NPM_BIN=$(which npm || true)
if [ -n "$NPM_BIN" ]; then
    echo "[*] Installing Node dependencies and building Vite/Tailwind assets..."
    $NPM_BIN install --ignore-scripts
    $NPM_BIN run build
else
    echo "[!] Node/npm not found, skipping asset compilation."
fi

# 9. Clear Caches
echo "[*] Clearing application caches..."
$PHP_BIN artisan config:clear
$PHP_BIN artisan view:clear

# 10. Verify with Automated Test Suite
echo ""
echo "[*] Running automated test suite..."
$PHP_BIN artisan test --compact

echo ""
echo "================================================================="
echo " [SUCCESS] Ottodot Trial Booking System is ready!"
echo "================================================================="
echo ""
echo " Quick Run Instructions:"
echo " 1. Start Web Server:"
echo "    cd $APP_DIR && php artisan serve"
echo "    -> Open browser: http://localhost:8000"
echo ""
echo " 2. Run Last-Seat Race Concurrency Verification:"
echo "    cd $APP_DIR && php artisan test:last-seat-race"
echo ""
echo " 3. Run Pest Test Suite:"
echo "    cd $APP_DIR && vendor/bin/pest"
echo "================================================================="
