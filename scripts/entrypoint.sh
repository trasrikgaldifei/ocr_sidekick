#!/bin/bash

# Populate empty ocr_sidekick mount directory
if [ ! -f /ocr_sidekick_mount ];
then
	cp -Rf /ocr_sidekick_source/* /ocr_sidekick_mount
fi

cd /ocr_sidekick

ocrmypdf --version >/ocr_sidekick_mount/version.txt

while :
do
  php ocr_sidekick.php
done