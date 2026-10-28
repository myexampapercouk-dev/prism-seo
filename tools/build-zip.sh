#!/bin/sh
# Builds dist/prism-works-site-builder.zip for upload in WordPress (Plugins > Add New > Upload Plugin).
cd "$(dirname "$0")/.." || exit 1
mkdir -p dist
rm -f dist/prism-works-site-builder.zip
if command -v zip >/dev/null 2>&1; then
	zip -qr dist/prism-works-site-builder.zip prism-works-site-builder
else
	# Windows: its bsdtar writes forward-slash paths (PowerShell 5 Compress-Archive writes backslashes, which WordPress can reject)
	/c/Windows/System32/tar.exe -a -c -f dist/prism-works-site-builder.zip prism-works-site-builder
fi
ls -l dist/prism-works-site-builder.zip
