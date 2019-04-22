<?php

	#########################################
	# OCR Sidekick tagging configuration	#
	#########################################
	# File:			tags.php				#
	# Author:		Trasrik Galdifei		#
	# Last change:	2019-04-22				#
	#########################################

	$tags = array();
	
	/*-- Tag definition
	In the following lines you can define tags, which will be added to the documents keywords.
	The syntax is:
	array_push($tags, array("SEARCHTYPE", array("TAGNAME - optional", "SEARCHTERM1", "SEARCHTERM2", ...));
	SEARCHTYPE	1 is a standard search
				2 is a regex search (in this case the found value will be used as TAGNAME)
	TAGNAME		is the tag, which will be added, if the search is successful
	SEARCHTERMx	is the value, for which the search is performed. If multiple values are given, they´re connected with OR
	*/
	// array_push($tags, array(2, "/Y(\d{4})D(\d{4})/"));
	// array_push($tags, array(1, array("Rechnung", "RECHNUNG")));

?>