<?php

	#########################################
	# OCR Sidekick tagging configuration	#
	#########################################
	# File:			tags.php				#
	# Author:		Trasrik Galdifei		#
	# Last change:	2019-09-25				#
	#########################################

	$tags = array();
	
	/*-- Tag definition
	In the following lines, you can define fixed tags, which will be applied to all files.
	*/
	
	$fixed_tags = array("Archive2019");	
	
	
	/*-- Dynamic Tag definition
	In the following lines you can define tags, which will be added to the document´s keywords and are used for file naming.
	The syntax is:
	array_push($tags, array("SEARCHTYPE", array("TAGNAME - optional", "SEARCHTERM1", "SEARCHTERM2", ...));
	SEARCHTYPE	1 is a standard search
				2 is a regex search (in this case the found value will be used as TAGNAME)
	TAGNAME		is the tag, which will be added, if the search is successful
	SEARCHTERMx	is the value, for which the search is performed. If multiple values are given, they´re connected with OR
	
	In addition, subdirectories within the consumer directory are treated as tags for the files contained in them.
	*/
	
	array_push($tags, array(2, "/Y(19|20\d\d)D(\d{4})/"));
	array_push($tags, array(1, array("Rechnung", "RECHNUNG")));
	array_push($tags, array(1, array("Firma", "FIRMA", "Gehalt")));

?>