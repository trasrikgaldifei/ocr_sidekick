<?php

	#########################################
	# OCR Sidekick configuration			#
	#########################################
	# File:			config.php				#
	# Author:		Trasrik Galdifei		#
	# Last change:	2019-04-22				#
	#########################################
	
	/*-- PDF Metadata
	In this section, PDF metadata can be provided to inject into your PDF/A output files.
	*/
	$meta_title = "";														// Document title (if empty, filename will be set as title)
	$meta_author = "OCR Sidekick";											// Document author (replaced if found during author check)
	$meta_subject = "";														// Document subject / description
	$meta_keywords = "ocrsidekick";											// Document keywords / tags (additionally found tags/keywords will be appended)

	/*-- Advanced tesseract options
	In this section, advanced tesseract options can be specified.
	If left empty, the according setting will be skipped.
	*/
	$tesseract_oversample = "";												// Oversample value in dpi (if empty, standard is 200 dpi). This can be used to increase result accuracy.
	$tesseract_rotate_threshold = "5";										// Rotate threshold for auto rotation of ppages

	/*-- Token for Telegram Bot
	In this section, variables for the use of a telegram bot are set up.
	If set, a notification with additional information is sent to the specified recipient after processing each file.
	For information on how to set up a telegram bot, please refer to https://core.telegram.org/bots#6-botfather
	To determine your telegram ID to set up a recipient, see https://telegram.me/myidbot	
	*/
	$telegram_token = "";													// Token for your Telegram bot (get from BotFather)
	$telegram_recipient = "";												// Your Telegram chat ID (get from IDBot)

?>