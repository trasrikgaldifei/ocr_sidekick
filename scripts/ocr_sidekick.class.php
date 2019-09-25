<?php
	#################################################
	# OCR Sidekick Main Class						#
	#################################################
	# File:			ocr_sidekick.class.php			#
	# Author:		Trasrik Galdifei				#
	# Last change:	2019-09-25						#
	#################################################

	// Include Telegram API
	require_once("vendor/autoload.php");
	use \unreal4u\TelegramAPI\HttpClientRequestHandler;
	use \unreal4u\TelegramAPI\TgLog;
	use \unreal4u\TelegramAPI\Telegram\Methods\SendMessage;

	class ocr_sidekick
	{
		# Configuration variables
		#########################
		
		// Directories
		protected $dir_config = "/ocr_sidekick_mount/config";
		protected $dir_input = "/ocr_sidekick_mount/0_input";
		protected $dir_output = "/ocr_sidekick_mount/0_output";
		protected $dir_processed = "/ocr_sidekick_mount/0_processed";
		protected $dir_temp = "/ocr_sidekick_mount/workdir";
		protected $dir_log = "/ocr_sidekick_mount/logs";
		
		// Metadata
		protected $meta_title = "";
		protected $meta_author = "OCR Sidekick";
		protected $meta_author_initial = "OCR Sidekick";
		protected $meta_subject = "";
		protected $meta_keywords = "";
		protected $meta_keywords_initial = "";
		
		// Tessaeract options
		protected $tesseract_overscan = "";
		protected $tesseract_rotate_threshold = "5";
		
		// Telegram
		protected $telegram_token = "";
		protected $telegram_target = "";
		protected $telegram_message = "";

		// Tagging
		protected $tags_found = array();
		protected $tags_definition = array();
		protected $fixed_tags = array();
		
		// Sender / correspondent
		protected $sender = array();
		protected $sender_definition = array();
		
		// Title and Date
		protected $document_title = "";
		protected $document_date = "";
		
		# Other variables
		#################
		protected $files = array();
		protected $subdirs = array();
		protected $num_files = 0;
		protected $num_skipped = 0;
		protected $num_valid = 0;
		
		protected $file_source = "";
		protected $file_work = "";
		protected $file_work_ocr = "";
		protected $file_work_text = "";
		protected $file_target;
		
		protected $ocr_text;
		protected $version = "0.2";
		
		protected $debug_keep_ocr_file = false;
				
		
		
		# Protected Functions
		#####################
		
		// Read config
		protected function read_config()
		{
			require_once($this->dir_config . "/config.php");
			
			// Metadata
			$this->meta_title = $meta_title;
			$this->meta_author = $meta_author;
			$this->meta_author_initial = $meta_author;
			$this->meta_subject = $meta_subject;
			$this->meta_keywords = $meta_keywords;
			$this->meta_keywords_initial = $meta_keywords;
			
			// Tessaeract options
			$this->tesseract_oversample = $tesseract_oversample;
			$this->tesseract_rotate_threshold = $tesseract_rotate_threshold;
			
			// Telegram
			$this->telegram_token = $telegram_token;
			$this->telegram_target = $telegram_recipient;
			
			// Debug settings
			$this->debug_keep_ocr_file = $debug_keep_ocr_file;

			// Taggging
			$tagfile = $this->dir_config . "/tags.php";
			if (is_file($tagfile))
			{
				require_once($tagfile);
				$this->tags_definition = $tags;
				$this->fixed_tags = $fixed_tags;
			}

			// Sender / correspondent identification
			$senderfile = $this->dir_config . "/sender.php";
			if (is_file($senderfile))
			{
				require_once($senderfile);
				$this->sender_definition = $senders;
			}

		}
		
		// Logging
		protected function log_write($text)
		{
			$logdate = date("Y-m-d", time());
			$logfile = $this->dir_log . "/" . $logdate . ".log";
			$logfile_handle = fopen($logfile, "a+");
			
			$logtime = date("Y-m-d, H:i:s", time());
			fwrite($logfile_handle, ("{$logtime}: {$text}\n"));
			
			fclose($logfile_handle);
		}
		
		// Write file contents into log file
		protected function file_to_log($file)
		{
			if(is_file($file))
			{
				$file_handle = fopen($file, "r");
				while ($text = fgets($file_handle))
				{
					$text = str_replace("\n", "", $text);
					if ($text <> "")
						$this->log_write($text);
				}
				fclose($file_handle);
				unlink($file);
				
				return true;
			}
			else
				return false;
		}
		
		// Delete a file
		protected function delete_file($file)
		{
			if (is_file($file))
			{
				unlink($file);
				$this->log_write("Temporary file {$file} deleted.");
			}
		}

		// Scan an input directory for PDF files
		protected function scan_dir($dir, $tag = "")
		{
			if (is_dir($dir))
			{
				if ($dir_handle = opendir($dir))
				{
					while (($filename = readdir($dir_handle)) != false)
					{
						// Push found subdirs into subdir array
						if (($filename != ".") && ($filename != "..") && (is_dir($dir . "/" . $filename)))
							array_push($this->subdirs, $filename);
						else
						{	
							$this->num_files += 1;
							
							$file_modify_time = filemtime($dir . "/" . $filename);
							$file_extension = pathinfo($dir . "/" . $filename, PATHINFO_EXTENSION);
							
							if ($file_extension == "pdf")
							{
								if ($file_modify_time < (time() - 30))
								{
									$this->num_valid += 1;
									array_push($this->files, array($filename, $tag));
								}
								else
									$this->num_skipped += 1;
							}
						}
					}
					closedir($dir_handle);
				}
			}
		}
		
		// Run OCR
		protected function run_ocr()
		{
			$this->log_write("Starting OCR");
			$command = "ocrmypdf -r -d -s -l deu+eng+fra --clean --output-type pdf";
			if ($this->tesseract_oversample <> "") $command .= " --oversample {$this->tesseract_oversample}";
			if ($this->tesseract_rotate_threshold <> "") $command .= " --rotate-pages-threshold {$this->tesseract_rotate_threshold}";
			$command .= " {$this->file_work} {$this->file_work_ocr} >{$this->dir_temp}/ocrmypdf.txt 2>&1";
			$this->log_write($command);
			$output = shell_exec($command);
			$this->file_to_log($this->dir_temp . "/ocrmypdf.txt");
		}

		// Extract rext from OCRed file
		protected function get_text_from_pdf()
		{
			if (is_file($this->file_work_ocr))
			{
				$this->ocr_text = "";
				
				$this->log_write("Extracting Text");
				$command = "pdftotext -nopgbrk -eol unix {$this->file_work_ocr} {$this->file_work_text} >{$this->dir_temp}/pdftotext.txt 2>&1";
				$output = shell_exec($command);
				$this->file_to_log($this->dir_temp . "/pdftotext.txt");

				if (is_file($this->file_work_text))
				{
					if($file_handle = fopen($this->file_work_text, "r"))
					{
						while ($text = fgets($file_handle))
						{
							$text = str_replace("\n", " ", $text);
							$this->ocr_text .= " " . $text;
						}
						fclose($file_handle);
					}
				}
			}
		}
		
		// Taggging
		protected function tagging($dir_tag = "")
		{
			$this->log_write("Starting TAGGING");
			$this->telegram_message .= "\nTAGS:";

			// Initialize tags array
			$this->tags_found = array();
			
			// Apply directory tags
			if ($dir_tag != "")
			
			// Add fixed tags
			for ($i = 0; $i < count($this->fixed_tags); $i++)
				array_push($this->tags_found, $this->fixed_tags[$i]);

			for ($i = 0; $i < count($this->tags_definition); $i++)
			{
				$current_tag = $this->tags_definition[$i];
				// Standard search
				if ($current_tag[0] == 1)
				{
					$found = false;
					$tag_value = $current_tag[1][0];
					for ($search_no = 1; $search_no < count($current_tag[1]); $search_no++)
					{
						if (strpos($this->ocr_text, $current_tag[1][$search_no]) !== false) 
							$found = true;
					}
					if ($found)
					{
						array_push($this->tags_found, $tag_value);
						$this->log_write("Found tag \"{$tag_value}\".");
					}	
				}
				// Regex search
				elseif ($current_tag[0] == 2)
				{
					$matches = array();
					$pattern = $current_tag[1];
					preg_match($pattern, $this->ocr_text, $matches);
					for ($hit_no = 0; $hit_no < count($matches); $hit_no++)
					{
						array_push($this->tags_found, $matches[$hit_no]);
						$this->log_write("Found tag \"{$matches[$hit_no]}\".");
					}
				}
			}
			
			// Remove double entries
			$this->tags_found = array_unique($this->tags_found);
			
			// Update meta keywords
			$this->meta_keywords = $this->meta_keywords_initial;
			for ($i = 0; $i < count($this->tags_found); $i++)
			{
				if ($this->meta_keywords != "") 
					$this->meta_keywords .= ",";
				$this->meta_keywords .= $this->tags_found[$i];
				
				$this->telegram_message .= "\n - {$this->tags_found[$i]}";
			}
		}

		// Sender / correspondent identification
		protected function sender()
		{
			// Initialize sender array
			$this->sender = array();
			
			$this->log_write("Starting SENDER IDENTIFICATION");
			$this->telegram_message .= "\nSENDER:";
			for ($i = 0; $i < count($this->sender_definition); $i++)
			{
				$current_sender = $this->sender_definition[$i];
				// Standard search
				if ($current_sender[0] == 1)
				{
					$found = false;
					$sender_value = $current_sender[1][0];
					for ($search_no = 1; $search_no < count($current_sender[1]); $search_no++)
					{
						if (strpos($this->ocr_text, $current_sender[1][$search_no]) !== false) 
							$found = true;
					}
					if ($found)
					{
						array_push($this->sender, $sender_value);
						$this->log_write("Found sender \"{$sender_value}\".");
					}	
				}
				// Regex search
				elseif ($current_sender[0] == 2)
				{
					$matches = array();
					$pattern = $current_sender[1];
					preg_match($pattern, $this->ocr_text, $matches);
					for ($hit_no = 0; $hit_no < count($matches); $hit_no++)
					{
						array_push($this->sender, $matches[$hit_no]);
						$this->log_write("Found sender \"{$matches[$hit_no]}\".");
					}
				}
			}
			
			// Update meta author
			if ((count($this->sender) == 0) || (count($this->sender) > 1))
				$this->meta_author = $this->meta_author_initial;
			else
				$this->meta_author = $this->sender[0];
			$this->telegram_message .= "\n - {$this->meta_author}";
			
		}
		
		// Guess the document date
		protected function guess_date()
		{
			$matches = array();
			$pattern = "/(0[1-9]|[12][0-9]|3[01])\.(0[1-9]|1[012])\.(19|20\d\d)/";
			preg_match($pattern, $this->ocr_text, $matches);
			
			if (count($matches) == 0)
				$this->document_date = mktime(12,0,0,1,1,1970);
			else
				$this->document_date = mktime(12,0,0,$matches[2],$matches[1],$matches[3]);
			
			$this->telegram_message .= "\nDATE:\n   " . date("d.m.Y", $this->document_date);
		}
		
		// Determine the document archive ID
		protected function document_id()
		{
			$matches = array();
			$pattern = "/Y(19|20\d\d)D(\d{4})/";
			preg_match($pattern, $this->ocr_text, $matches);
			
			if (count($matches) == 0)
				$this->document_title = "No archive ID";
			else
				$this->document_title = $matches[0];

			$this->meta_title = $this->document_title;
			$this->meta_subject = $this->document_title;
			
			$this->telegram_message .= "\nTITLE:\n   " . $this->document_title;
		}
		
		// Paperless Filename
		protected function paperless_filename($index = 0)
		{
			$filename = "";
			
			// Date in Zulu format
			$filename .= date("Ymdhis\Z", $this->document_date);
			$filename .= " - ";
			
			// Correspondent
			$filename .= $this->meta_author;
			$filename .= " - ";
			
			// Title
			$filename .= $this->document_title;
			if ($index > 0)
				$filename .= "_" . $index;
			$filename .= " - ";
			
			// Tags
			$filename .= $this->meta_keywords;
			
			// Filetype
			$filename .= ".pdf";
			
			return $filename;
		}

		// Convert to PDF/A
		protected function convert_to_pdfa()
		{
			$this->log_write("Converting to PDF/A");
			$command = "ocrmypdf -s --output-type pdfa";
			if ($this->meta_title <> "") $command .= " --title \"{$this->meta_title}\"";
			if ($this->meta_author <> "") $command .= " --author \"{$this->meta_author}\"";
			if ($this->meta_subject <> "") $command .= " --subject \"{$this->meta_subject}\"";
			if ($this->meta_keywords <> "") $command .= " --keywords \"{$this->meta_keywords}\"";
			$command .= " {$this->file_work_ocr} \"{$this->file_target}\" >{$this->dir_temp}/ocrmypdf.txt 2>&1";
			$this->log_write($command);
			$output = shell_exec($command);
			$this->file_to_log($this->dir_temp . "/ocrmypdf.txt");
		}
		
		
		# Public functions
		##################
		
		// Constructor
		public function __construct()
		{
			$this->read_config();
		}
		
		// Destructor
		public function __destruct()
		{
		}
		
		// Scan input dir for PDF files
		public function scan_input_dir()
		{
			$this->files = array();
			$this->subdirs = array();
			
			$this->num_files = 0;
			$this->num_skipped = 0;
			$this->num_valid = 0;
			
			// Scan input dir
			$this->scan_dir($this->dir_input);
			
			// Scan subdirs
			foreach($this->subdirs as $subdir)
				$this->scan_dir($this->dir_input . "/" . $subdir, $subdir);
			
			return array($this->num_files, $this->num_valid, $this->num_skipped);
		}
		
		// Process valid PDF files
		public function process_files()
		{
			foreach($this->files as $file_array)
			{
				$dir_tag = $file_array[1];
				if ($dir_tag != "")
					$subdir = "/" . $dir_tag;
				else
					$subdir = "";
				$file = $file_array[0];
				
				// Initialize variables
				$temp_filename = substr(md5(time()), 0, 10);
				
				$this->telegram_message = "";
				$this->file_source = $this->dir_input . $subdir ."/" . $file;
				$this->file_work = $this->dir_temp . "/" . $temp_filename . ".source.pdf";
				$this->file_work_ocr = $this->dir_temp . "/" . $temp_filename . ".ocr.pdf";
				$this->file_work_text = $this->dir_temp . "/" . $temp_filename . ".ocr.txt";
				$this->file_processed = $this->dir_processed . "/" . $file;
				
				// Log entry
				$this->log_write("-----");
				$this->log_write("Found new PDF: " . $file);
				
				// Telegram Information
				$this->telegram_message .= "OCR Sidekick v" . $this->version;
				$this->telegram_message .= "\n================";
				$this->telegram_message .= "\nNEW PDF:\n - " . $file;

				// Move PDF to work dir...
				$this->log_write("Moving PDF to work directory... (" . $this->file_work . ")");
				chmod($this->file_source, 0755);
				rename($this->file_source, $this->file_work);
				
				// Execute OCR functions
				$this->run_ocr();
				$this->get_text_from_pdf();
				$this->document_id();
				$this->guess_date();
				$this->sender();
				$this->tagging($dir_tag);

				// Create target file
				$index = 0;
				do
				{
					$this->file_target = $this->dir_output . "/" . $this->paperless_filename($index);
					$index++;
				}
				while (file_exists($this->file_target));

				$this->convert_to_pdfa();

				// Set access rights for target file
				$this->log_write("Setting access rights for output file...");
				chmod($this->file_target, 0755);
				
				// Delete temporary files
				$this->delete_file($this->file_work_ocr);
				if (!$this->debug_keep_ocr_file)
					$this->delete_file($this->file_work_text);

				// Move processed file to processed folder
				$this->log_write("Moving processed file to " . $this->file_processed);
				rename($this->file_work, $this->file_processed);
				$this->log_write("Finished.");
				
				$this->telegram_send();
			}
		}

		// Telegram notification
		public function telegram_send()
		{
			if ((isset($this->telegram_token)) && ($this->telegram_message <> ""))
			{
				$loop = \React\EventLoop\Factory::create();
				$handler = new HttpClientRequestHandler($loop);
				$tgLog = new TgLog($this->telegram_token, $handler);

				$sendMessage = new SendMessage();
				$sendMessage->chat_id = $this->telegram_target;
				$sendMessage->text = $this->telegram_message;

				$tgLog->performApiRequest($sendMessage);
				$loop->run();
			}
		}
	}
?>