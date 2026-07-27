SHELL := /bin/bash

.PHONY: install

install:
	@chmod +x install.sh
	@current=$$(git describe --tags --abbrev=0 2>/dev/null || echo "none"); \
	version="$(VERSION)"; \
	if [ -z "$$version" ] && [ -t 0 ]; then \
		echo "Current version: $$current"; \
		read -r -p "New version to tag (blank to build $$current): " version; \
	fi; \
	version="$${version#v}"; \
	if [ -n "$$version" ]; then \
		if git rev-parse -q --verify "refs/tags/$$version" >/dev/null; then \
			echo "Tag $$version already exists. Delete it or pick another version." >&2; \
			exit 1; \
		fi; \
		git tag -a "$$version" -m "$$version"; \
		echo "Tagged $$version on $$(git rev-parse --short HEAD). Push it with: git push origin $$version"; \
		./install.sh "$$version"; \
	else \
		./install.sh; \
	fi
