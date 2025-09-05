#!/bin/bash

# Smart Ticket Triage - Stop Script
# This script stops all running services

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_status "Stopping Smart Ticket Triage services..."

# Stop Laravel server
print_status "Stopping Laravel server..."
pkill -f "php artisan serve" 2>/dev/null || true

# Stop queue worker
print_status "Stopping queue worker..."
pkill -f "php artisan queue:work" 2>/dev/null || true

# Stop Vite dev server
print_status "Stopping Vite development server..."
pkill -f "npm run dev" 2>/dev/null || true
pkill -f "vite" 2>/dev/null || true

# Stop any Node.js processes related to this project
print_status "Stopping Node.js processes..."
pkill -f "node.*vite" 2>/dev/null || true

print_success "All services stopped ✓"
