#!/bin/bash

# Release script for Grandstream UCM Integration
# Usage: ./scripts/release.sh <version>
# Example: ./scripts/release.sh 1.1.0

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check arguments
if [ -z "$1" ]; then
    echo -e "${RED}Error: Version number required${NC}"
    echo "Usage: $0 <version>"
    echo "Example: $0 1.1.0"
    exit 1
fi

VERSION=$1
TAG="v${VERSION}"

# Validate version format
if ! [[ $VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}Error: Invalid version format. Use semantic versioning (e.g., 1.0.0)${NC}"
    exit 1
fi

echo -e "${GREEN}=== Grandstream UCM Integration Release ${VERSION} ===${NC}"
echo ""

# Check if we're in the right directory
if [ ! -f "VERSION" ]; then
    echo -e "${RED}Error: VERSION file not found. Are you in the project root?${NC}"
    exit 1
fi

# Check for uncommitted changes
if ! git diff-index --quiet HEAD --; then
    echo -e "${YELLOW}Warning: You have uncommitted changes${NC}"
    read -p "Do you want to continue? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Update VERSION file
echo -e "${YELLOW}Updating VERSION file...${NC}"
echo "$VERSION" > VERSION

# Update Odoo module version
echo -e "${YELLOW}Updating Odoo module version...${NC}"
ODOO_VERSION="17.0.${VERSION}"
sed -i "s/'version': '[^']*'/'version': '${ODOO_VERSION}'/" grandstream_ucm_integration/__manifest__.py

# Update version in Odoo update manager
sed -i "s/CURRENT_VERSION = '[^']*'/CURRENT_VERSION = '${VERSION}'/" grandstream_ucm_integration/models/update_manager.py

# Update Dolibarr module version
echo -e "${YELLOW}Updating Dolibarr module version...${NC}"
sed -i "s/\$this->version = '[^']*'/\$this->version = '${VERSION}'/" dolibarr_grandstreamucm/core/modules/modGrandstreamUCM.class.php

# Update version in Dolibarr update manager
sed -i "s/public \$currentVersion = '[^']*'/public \$currentVersion = '${VERSION}'/" dolibarr_grandstreamucm/class/updatemanager.class.php

# Check if CHANGELOG has entry for this version
if ! grep -q "## \[${VERSION}\]" CHANGELOG.md; then
    echo -e "${YELLOW}Warning: No CHANGELOG entry found for version ${VERSION}${NC}"
    echo "Please update CHANGELOG.md before releasing"
    read -p "Do you want to continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Commit version changes
echo -e "${YELLOW}Committing version changes...${NC}"
git add VERSION \
    grandstream_ucm_integration/__manifest__.py \
    grandstream_ucm_integration/models/update_manager.py \
    dolibarr_grandstreamucm/core/modules/modGrandstreamUCM.class.php \
    dolibarr_grandstreamucm/class/updatemanager.class.php \
    CHANGELOG.md

git commit -m "Release v${VERSION}"

# Create tag
echo -e "${YELLOW}Creating tag ${TAG}...${NC}"
git tag -a "$TAG" -m "Release ${VERSION}"

# Push changes
echo -e "${YELLOW}Pushing to remote...${NC}"
git push origin HEAD
git push origin "$TAG"

echo ""
echo -e "${GREEN}=== Release ${VERSION} completed! ===${NC}"
echo ""
echo "Next steps:"
echo "1. GitHub Actions will automatically create the release with packages"
echo "2. Check the Actions tab on GitHub for build status"
echo "3. Once complete, the release will be available at:"
echo "   https://github.com/fred-selest/odoo-integration-grandstream/releases/tag/${TAG}"
echo ""
