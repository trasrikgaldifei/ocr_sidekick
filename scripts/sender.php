<?php

	#####################################################
	# OCR Sidekick sender / correspondent configuration	#
	#####################################################
	# File:			sender.php							#
	# Author:		Trasrik Galdifei					#
	# Last change:	2019-09-25							#
	#####################################################

	$senders = array();
	
	/*-- Sender / correspondent definition
	In the following lines you can define correspondents, which will be added to the document´s author and are used for file naming.
	The syntax is:
	array_push($tags, array("SEARCHTYPE", array("SENDERNAME", "SEARCHTERM1", "SEARCHTERM2", ...));
	SEARCHTYPE	1 is a standard search
				2 is a regex search (in this case the found value will be used as SENDERNAME)
	SENDERNAME	is the correspondent, which will be added, if the search is successful
	SEARCHTERMx	is the value, for which the search is performed. If multiple values are given, they´re connected with OR
	*/
	
	array_push($senders, array(1, array("Example1", "Example1 GmbH", "Customer 12345")));

?>