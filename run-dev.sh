#!/bin/bash

# Zabbix Weathermap - Development Server
# This script runs the application in development mode using PHP's built-in server

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Zabbix Weathermap Development Server ===${NC}"

# Check PHP version
if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: PHP is not installed${NC}"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo -e "PHP Version: ${GREEN}$PHP_VERSION${NC}"

# Check required extensions
echo -e "\n${YELLOW}Checking PHP extensions...${NC}"
REQUIRED_EXTENSIONS=("gd" "pdo" "pdo_sqlite" "json" "mbstring" "curl")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -qi "^$ext$"; then
        echo -e "  ✓ $ext"
    else
        echo -e "  ${RED}✗ $ext (missing)${NC}"
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    echo -e "\n${RED}Missing extensions: ${MISSING_EXTENSIONS[*]}${NC}"
    echo -e "Install with: ${YELLOW}sudo apt install php-gd php-sqlite3 php-mbstring php-curl${NC}"
    exit 1
fi

# Create .env if not exists
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo -e "\n${YELLOW}Creating .env from .env.example...${NC}"
        cp .env.example .env
        echo -e "${GREEN}Created .env file. Please edit it with your Zabbix API credentials.${NC}"
    fi
fi

# Install composer dependencies if needed
if [ ! -d "vendor" ]; then
    echo -e "\n${YELLOW}Installing Composer dependencies...${NC}"
    if command -v composer &> /dev/null; then
        composer install --no-dev
    else
        echo -e "${RED}Composer not found. Please install dependencies manually:${NC}"
        echo -e "  composer install"
        echo -e "\nOr install Composer: https://getcomposer.org/download/"
        exit 1
    fi
fi

# Create required directories
echo -e "\n${YELLOW}Creating required directories...${NC}"
mkdir -p data/configs data/output data/cache src/logs

# Set permissions
chmod -R 777 data src/logs 2>/dev/null || true

# Configuration
HOST="${DEV_HOST:-localhost}"
PORT="${DEV_PORT:-8080}"

# Export environment variables for the application
export APP_ENV="development"
export APP_DEBUG="true"
export APP_ROOT="$SCRIPT_DIR/src"
export DATA_ROOT="$SCRIPT_DIR/data"

echo -e "\n${GREEN}Starting development server...${NC}"
echo -e "URL: ${GREEN}http://${HOST}:${PORT}${NC}"
echo -e "Document root: ${SCRIPT_DIR}/src/public"
echo -e "\nPress ${YELLOW}Ctrl+C${NC} to stop the server\n"
echo -e "-------------------------------------------"

# Start PHP built-in server
php -S "${HOST}:${PORT}" -t src/public
