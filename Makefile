# kit's sections as cg-cms blocks (kit-blocks/, public/kit-blocks/).
#
#   make sync    rebuild kit-blocks/ from ~/dev/kit and copy its CSS and fonts to public/
#   make check   fail if kit-blocks/ or public/kit-blocks/ is out of date with kit
#
# tests/fixtures/kit-blocks.json is every kit demo with kit's own HTML, for
# tests/Feature/KitBlocksParityTest.php.
#
# Both are committed: the server never runs kit or copies assets (deploy/ship.sh).
# KIT points at the kit checkout; the binary is built from it each time.

KIT ?= $(HOME)/dev/kit
KIT_BIN := $(KIT)/bin/kit

.PHONY: sync check kit cga

# CG Analytics' script, served as public/cga.js (config/site.php cga_site_id).
ANALYTICS ?= $(HOME)/dev/chris/analytics

cga:
	cp $(ANALYTICS)/script/cga.min.js public/cga.js

kit:
	cd $(KIT) && go build -o bin/kit ./cmd/kit

sync: kit
	$(KIT_BIN) build-cgcms -root $(KIT) -out kit-blocks -fixtures tests/fixtures/kit-blocks.json
	php artisan cms:kit-assets kit-blocks

check: kit
	$(KIT_BIN) build-cgcms -root $(KIT) -out kit-blocks -fixtures tests/fixtures/kit-blocks.json -check
	@for f in $$(cd kit-blocks && find kit/kit.css kit/fonts sections -type f \( -name '*.css' -o -name '*.woff2' -o -name '*.woff' -o -name '*.ttf' -o -name '*.otf' \)); do \
		cmp -s kit-blocks/$$f public/kit-blocks/$$f || { echo "public/kit-blocks/$$f is out of date: run make sync"; exit 1; }; \
	done
	@echo "public/kit-blocks matches kit-blocks"
