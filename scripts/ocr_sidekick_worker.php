<?php
	#################################################
	# OCR Sidekick Worker							#
	#################################################
	# File:			ocr_sidekick_worker.php			#
	# Author:		Trasrik Galdifei				#
	# Last change:	2019-04-22						#
	#################################################

	require_once("ocr_sidekick.class.php");
	
	$ocr_sidekick = new ocr_sidekick();
	$ocr_sidekick->scan_input_dir();
	$ocr_sidekick->process_files();
	$ocr_sidekick->telegram_send();
	unset($ocr_sidekick);
?>