#!/bin/bash

# Populate empty ocr_sidekick mount directory
if [ ! -f /ocr_sidekick_mount/version.txt ];
then
	cp -Rf /ocr_sidekick_source/* /ocr_sidekick_mount
fi

cd /ocr_sidekick

. /ocrmypdf_env/bin/activate
ocrmypdf --version >/ocr_sidekick_mount/version.txt

while :
do
  php ocr_sidekick_worker.php >/dev/null &2>1
  sleep 30
done

deactivate