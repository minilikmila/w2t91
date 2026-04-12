#!/usr/bin/env bash
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# ---------------------------------------------------------------------------
# If running OUTSIDE Docker, delegate to Docker Compose and exit.
# ---------------------------------------------------------------------------
if [ ! -f /.dockerenv ] || [ ! -f vendor/autoload.php ]; then
    # Detect docker compose command (V2 plugin vs V1 standalone)
    if docker compose version &>/dev/null; then
        COMPOSE="docker compose"
    elif command -v docker-compose &>/dev/null; then
        COMPOSE="docker-compose"
    else
        echo "Error: Neither 'docker compose' nor 'docker-compose' found." >&2
        exit 1
    fi

    echo "Starting Docker services..."
    if ! $COMPOSE up -d; then
        echo "Error: Failed to start Docker services." >&2
        exit 1
    fi

    # Wait for vendor/autoload.php (signals composer install finished)
    echo "Waiting for app container to be ready..."
    RETRIES=0
    until $COMPOSE exec -T app test -f vendor/autoload.php 2>/dev/null; do
        RETRIES=$((RETRIES + 1))
        if [ "$RETRIES" -ge 30 ]; then
            echo "Error: App container not ready after 60 seconds." >&2
            exit 1
        fi
        sleep 2
    done

    # Run this same script inside the container; propagate its exit code
    $COMPOSE exec -T app bash run_tests.sh
    exit $?
fi

# ---------------------------------------------------------------------------
# Inside the container – run tests directly.
# ---------------------------------------------------------------------------

PASS=0
FAIL=0
TOTAL_TESTS=0
TOTAL_ASSERTIONS=0
ERRORS=()
START_TIME=$(date +%s)

# Color output if terminal supports it
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_header() {
    echo ""
    echo "============================================"
    echo " EaglePoint Test Runner"
    echo " $(date '+%Y-%m-%d %H:%M:%S')"
    echo "============================================"
    echo ""
}

# Extract test counts from PHPUnit output
parse_phpunit_output() {
    local output="$1"
    # Match lines like "Tests: 25, Assertions: 50" or "OK (25 tests, 50 assertions)"
    local tests=$(echo "$output" | sed -n 's/.*\([0-9][0-9]*\) test.*/\1/p' | head -1)
    local assertions=$(echo "$output" | sed -n 's/.*\([0-9][0-9]*\) assertion.*/\1/p' | head -1)
    if [ -n "$tests" ]; then
        TOTAL_TESTS=$((TOTAL_TESTS + tests))
    fi
    if [ -n "$assertions" ]; then
        TOTAL_ASSERTIONS=$((TOTAL_ASSERTIONS + assertions))
    fi
}

run_suite() {
    local suite_name="$1"
    local command="$2"

    echo "--- ${suite_name} ---"

    local output
    local exit_code
    output=$(eval "$command" 2>&1)
    exit_code=$?

    echo "$output"
    echo ""

    parse_phpunit_output "$output"

    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}[PASS]${NC} ${suite_name} passed."
        PASS=$((PASS + 1))
    else
        echo -e "${RED}[FAIL]${NC} ${suite_name} failed."
        FAIL=$((FAIL + 1))
        ERRORS+=("$suite_name")
    fi
    echo ""
}

print_header

# --- PHPUnit Built-in Unit Tests ---
run_suite "Unit Tests (tests/Unit)" "php artisan test --testsuite=Unit"

# --- PHPUnit Built-in Feature Tests ---
run_suite "Feature/API Tests (tests/Feature)" "php artisan test --testsuite=Feature"

# --- Custom Unit Tests (unit_tests/) ---
if ls unit_tests/*.php 1>/dev/null 2>&1; then
    run_suite "Custom Unit Tests (unit_tests/)" "php vendor/bin/phpunit --configuration phpunit.xml --testdox unit_tests/"
else
    echo "--- Custom Unit Tests (unit_tests/) ---"
    echo -e "${YELLOW}[SKIP]${NC} No test files found in unit_tests/."
    echo ""
fi

# --- Custom API Tests (API_tests/) ---
if ls API_tests/*.php 1>/dev/null 2>&1; then
    run_suite "Custom API Tests (API_tests/)" "php vendor/bin/phpunit --configuration phpunit.xml --testdox API_tests/"
else
    echo "--- Custom API Tests (API_tests/) ---"
    echo -e "${YELLOW}[SKIP]${NC} No test files found in API_tests/."
    echo ""
fi

# --- Timing ---
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

# --- Summary ---
echo "============================================"
echo " Test Summary"
echo "============================================"
echo ""
echo " Suites run:    $((PASS + FAIL))"
echo " Suites passed: $PASS"
echo " Suites failed: $FAIL"
echo ""

if [ "$TOTAL_TESTS" -gt 0 ]; then
    echo " Total tests:      $TOTAL_TESTS"
    echo " Total assertions: $TOTAL_ASSERTIONS"
    echo ""
fi

echo " Duration: ${DURATION}s"
echo ""

if [ ${#ERRORS[@]} -gt 0 ]; then
    echo -e " ${RED}Failed suites:${NC}"
    for err in "${ERRORS[@]}"; do
        echo "   - $err"
    done
    echo ""
fi

if [ "$FAIL" -gt 0 ]; then
    echo -e " ${RED}RESULT: FAILED${NC}"
    echo "============================================"
    exit 1
else
    echo -e " ${GREEN}RESULT: ALL PASSED${NC}"
    echo "============================================"
    exit 0
fi
