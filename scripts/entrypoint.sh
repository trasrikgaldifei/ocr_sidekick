#!/bin/bash

# Populate empty ocr_sidekick mount directory
if [ ! -f /ocr_sidekick_mount ];
then
	cp -Rf /ocr_sidekick_source/* /ocr_sidekick_mount
fi

cd /ocr_sidekick
php ocr_sidekick.php

tail -f /dev/null