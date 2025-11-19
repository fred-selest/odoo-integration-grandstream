#!/bin/bash

# Build script for creating distribution packages
# Usage: ./scripts/build.sh

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Get version
VERSION=$(cat VERSION)

echo -e "${GREEN}=== Building Grandstream UCM Integration v${VERSION} ===${NC}"
echo ""

# Create dist directory
mkdir -p dist

# Clean old builds
rm -f dist/*.zip

# Build Odoo module package
echo -e "${YELLOW}Building Odoo module package...${NC}"
zip -r "dist/grandstream_ucm_integration_odoo_v${VERSION}.zip" \
    grandstream_ucm_integration \
    -x "*.pyc" \
    -x "*__pycache__*" \
    -x "*.DS_Store"

# Build Dolibarr module package
echo -e "${YELLOW}Building Dolibarr module package...${NC}"
zip -r "dist/grandstream_ucm_dolibarr_v${VERSION}.zip" \
    dolibarr_grandstreamucm \
    -x "*.DS_Store"

# Build combined package
echo -e "${YELLOW}Building combined package...${NC}"
zip -r "dist/grandstream_ucm_all_v${VERSION}.zip" \
    grandstream_ucm_integration \
    dolibarr_grandstreamucm \
    README.md \
    CHANGELOG.md \
    VERSION \
    INSTALL.md \
    requirements.txt \
    -x "*.pyc" \
    -x "*__pycache__*" \
    -x "*.DS_Store"

echo ""
echo -e "${GREEN}=== Build completed! ===${NC}"
echo ""
echo "Packages created in dist/:"
ls -lh dist/
echo ""
