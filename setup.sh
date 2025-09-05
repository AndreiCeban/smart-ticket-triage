#!/bin/bash

# TODO: Smart Ticket Triage - Setup and Start Script
# 
# Requirements from specification:
# - README.md with ≤ 10 setup steps (clone → composer install → npm install → .env → php artisan migrate --seed → npm run dev/build, etc.)
# - Automated installation and startup process
# - System requirements checking (PHP 8.2+, Node.js 18+, Composer)
# - Environment setup and database seeding
# - Service management (Laravel, Queue, Vite)

# Smart Ticket Triage - Setup and Start Script
# This script handles installation and startup of the application

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check if file exists
file_exists() {
    [ -f "$1" ]
}

# Function to check if directory exists
dir_exists() {
    [ -d "$1" ]
}

# Parse command line arguments
FORCE_INSTALL=false
SKIP_QUEUE=false
SKIP_DEV=false
FRESH_DB=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --force-install)
            FORCE_INSTALL=true
            shift
            ;;
        --fresh)
            FRESH_DB=true
            shift
            ;;
        --skip-queue)
            SKIP_QUEUE=true
            shift
            ;;
        --skip-dev)
            SKIP_DEV=true
            shift
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --force-install    Force reinstallation of dependencies"
            echo "  --fresh            Fresh database migration and seeding"
            echo "  --skip-queue       Skip starting queue worker"
            echo "  --skip-dev         Skip starting development server"
            echo "  --help             Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                 # Normal startup"
            echo "  $0 --force-install # Force reinstall everything"
            echo "  $0 --fresh         # Fresh database with new data"
            echo "  $0 --skip-queue    # Start without queue worker"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

print_status "Smart Ticket Triage - Setup & Start Script"
echo ""

# Check system requirements
print_status "Checking system requirements..."

if ! command_exists php; then
    print_error "PHP is not installed. Please install PHP 8.2+ first."
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
if [[ $(echo "$PHP_VERSION 8.2" | awk '{print ($1 < $2)}') == 1 ]]; then
    print_error "PHP version $PHP_VERSION is too old. Please install PHP 8.2+ first."
    exit 1
fi

if ! command_exists composer; then
    print_error "Composer is not installed. Please install Composer first."
    exit 1
fi

if ! command_exists node; then
    print_error "Node.js is not installed. Please install Node.js 18+ first."
    exit 1
fi

NODE_VERSION=$(node -v | sed 's/v//')
if [[ $(echo "$NODE_VERSION 18.0" | awk '{print ($1 < $2)}') == 1 ]]; then
    print_error "Node.js version $NODE_VERSION is too old. Please install Node.js 18+ first."
    exit 1
fi

print_success "System requirements met"
echo ""

# Check if installation is needed
NEED_INSTALL=false

if [ "$FORCE_INSTALL" = true ]; then
    print_warning "Force install flag detected"
    NEED_INSTALL=true
elif ! dir_exists "vendor" || ! dir_exists "node_modules"; then
    print_status "Dependencies not found, installation needed"
    NEED_INSTALL=true
fi

# Installation process
if [ "$NEED_INSTALL" = true ]; then
    print_status "Installing dependencies..."
    
    # Install PHP dependencies
    print_status "Installing PHP dependencies with Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
    
    # Install Node.js dependencies
    print_status "Installing Node.js dependencies..."
    npm install
    
    # Check if .env exists
    if ! file_exists ".env"; then
        print_status "Creating .env file from .env.example..."
        if file_exists ".env.example"; then
            cp .env.example .env
        else
            print_error ".env.example not found. Please create .env file manually."
            exit 1
        fi
    fi
    
    # Generate application key
    print_status "Generating application key..."
    php artisan key:generate --no-interaction
    
    # Run database migrations
    if [ "$FRESH_DB" = true ]; then
        print_status "Fresh database requested - dropping and recreating..."
        php artisan migrate:fresh --force
    else
        print_status "Running database migrations..."
        php artisan migrate --force
    fi
    
    # Seed database
    print_status "Seeding database with sample data..."
    php artisan db:seed --force
    
    # Build frontend assets
    print_status "Building frontend assets..."
    npm run build
    
    print_success "Installation completed successfully"
    echo ""
else
    print_success "Dependencies already installed, skipping installation"
    echo ""
fi

# Check if .env exists and has required variables
if ! file_exists ".env"; then
    print_error ".env file not found. Please run with --force-install or create .env manually."
    exit 1
fi

# Check for OpenAI API key
if ! grep -q "OPENAI_API_KEY=" .env || grep -q "OPENAI_API_KEY=your_openai_api_key_here" .env; then
    print_warning "OpenAI API key not configured in .env file"
    print_warning "AI classification will use fallback mode"
    echo ""
fi

# Start the application
print_status "Starting Smart Ticket Triage application..."
echo ""

# Start Laravel server in background
print_status "Starting Laravel development server..."
php artisan serve --host=127.0.0.1 --port=8000 > /dev/null 2>&1 &
LARAVEL_PID=$!

# Wait a moment for Laravel to start
sleep 2

# Start queue worker if not skipped
if [ "$SKIP_QUEUE" = false ]; then
    print_status "Starting queue worker for AI classification..."
    php artisan queue:work --daemon > /dev/null 2>&1 &
    QUEUE_PID=$!
else
    print_warning "Queue worker skipped (use --skip-queue to suppress this warning)"
fi

# Start development server if not skipped
if [ "$SKIP_DEV" = false ]; then
    print_status "Starting Vite development server..."
    npm run dev > /dev/null 2>&1 &
    VITE_PID=$!
else
    print_warning "Development server skipped (use --skip-dev to suppress this warning)"
fi

# Function to cleanup on exit
cleanup() {
    print_status "Shutting down services..."
    
    if [ ! -z "$LARAVEL_PID" ]; then
        kill $LARAVEL_PID 2>/dev/null || true
    fi
    
    if [ ! -z "$QUEUE_PID" ]; then
        kill $QUEUE_PID 2>/dev/null || true
    fi
    
    if [ ! -z "$VITE_PID" ]; then
        kill $VITE_PID 2>/dev/null || true
    fi
    
    print_success "All services stopped"
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

# Display application information
echo ""
print_success "Smart Ticket Triage is now running!"
echo ""
echo "  Application URLs:"
echo "   • Main App:     http://127.0.0.1:8000"
echo "   • API:          http://127.0.0.1:8000/api"
echo "   • Tickets:      http://127.0.0.1:8000/tickets"
echo "   • Dashboard:    http://127.0.0.1:8000/dashboard"
echo ""
echo "Services Running:"
echo "   • Laravel Server (PID: $LARAVEL_PID)"
if [ "$SKIP_QUEUE" = false ]; then
    echo "   • Queue Worker (PID: $QUEUE_PID)"
fi
if [ "$SKIP_DEV" = false ]; then
    echo "   • Vite Dev Server (PID: $VITE_PID)"
fi
echo ""
echo "Quick Commands:"
echo "   • Bulk classify: php artisan tickets:bulk-classify"
echo "   • View logs:     tail -f storage/logs/laravel.log"
echo "   • Stop all:      Press Ctrl+C"
echo ""
print_warning "Press Ctrl+C to stop all services"

# Wait for user interrupt
wait
