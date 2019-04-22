<?php
	#################################################
	# OCR Sidekick Main Class						#
	#################################################
	# File:			ocr_sidekick.class.php			#
	# Author:		Trasrik Galdifei				#
	# Last change:	2019-04-22						#
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
		protected $meta_subject = "";
		protected $meta_keywords = "";
		
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
		
		# Other variables
		#################
		protected $files = array();
		
		protected $file_source = "";
		protected $file_work = "";
		protected $file_work_ocr = "";
		protected $file_work_text = "";
		protected $file_target;
		
		protected $ocr_text;
		
		
		
		
		# Protected Functions
		#####################
		
		// Read config
		protected function read_config()
		{
			require_once($this->dir_config . "/config.php");
			
			// Metadata
			$this->meta_title = $meta_title;
			$this->meta_author = $meta_author;
			$this->meta_subject = $meta_subject;
			$this->meta_keywords = $meta_keywords;
			
			// Tessaeract options
			$this->tesseract_oversample = $tesseract_oversample;
			$this->tesseract_rotate_threshold = $tesseract_rotate_threshold;
			
			// Telegram
			$this->telegram_token = $telegram_token;
			$this->telegram_target = $telegram_recipient;

			// Taggging
			$tagfile = $this->dir_config . "/tags.php";
			if (is_file($tagfile))
			{
				require_once($tagfile);
				$this->tags_definition = $tags;
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
		protected function tagging()
		{
			$this->log_write("Starting TAGGING");
			$this->telegram_message .= "TAGS:";
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
						$this->telegram_message .= " {$tag_value}";
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
						$this->telegram_message .= " {$matches[$hit_no]}";
					}
				}
			}
			$this->telegram_message .= "\n";
			
			// Update meta keywords
			for ($i = 0; $i < count($this->tags_found); $i++)
			{
				if ($this->meta_keywords != "") $this->meta_keywords .= ", ";
				$this->meta_keywords .= $this->tags_found[$i];
			}
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
			$command .= " {$this->file_work_ocr} {$this->file_target} >{$this->dir_temp}/ocrmypdf.txt 2>&1";
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
			$num_files = 0;
			$num_skipped = 0;
			$num_valid = 0;
			
			if (is_dir($this->dir_input))
			{
				if ($dir_handle = opendir($this->dir_input))
				{
					while (($filename = readdir($dir_handle)) != false)
					{
						$num_files += 1;
						
						$file_modify_time = filemtime($this->dir_input . "/" . $filename);
						$file_extension = pathinfo($this->dir_input . "/" . $filename, PATHINFO_EXTENSION);
						
						if ($file_extension == "pdf")
						{
							if ($file_modify_time < (time() - 30))
							{
								$num_valid += 1;
								array_push($this->files, $filename);
							}
							else
								$num_skipped += 1;
						}
					}
					closedir($dir_handle);
				}
			}
			
			return array($num_files, $num_valid, $num_skipped);
		}
		
		// Process valid PDF files
		public function process_files()
		{
			foreach($this->files as $file)
			{
				// Initialize variables
				$temp_filename = substr(md5(time()), 0, 10);
				
				$this->telegram_message = "";
				$this->file_source = $this->dir_input . "/" . $file;
				$this->file_work = $this->dir_temp . "/" . $temp_filename . ".source.pdf";
				$this->file_work_ocr = $this->dir_temp . "/" . $temp_filename . ".ocr.pdf";
				$this->file_work_text = $this->dir_temp . "/" . $temp_filename . ".ocr.txt";
				$this->file_target = $this->dir_output . "/" . $file;
				$this->file_processed = $this->dir_processed . "/" . $file;
				
				// Log entry
				$this->log_write("-----");
				$this->log_write("Found new PDF: " . $file);
				$this->telegram_message .= "NEW PDF: " . $file . "\n";

				// Move PDF to work dir...
				$this->log_write("Moving PDF to work directory... (" . $this->file_work . ")");
				chmod($this->file_source, 0755);
				rename($this->file_source, $this->file_work);
				
				// Execute OCR functions
				$this->run_ocr();
				$this->get_text_from_pdf();
				$this->tagging();
				$this->convert_to_pdfa();

				// Set access rights for target file
				$this->log_write("Setting access rights for output file...");
				chmod($this->file_target, 0755);
				
				// Delete temporary files
				$this->delete_file($this->file_work_ocr);
				// $this->delete_file($this->file_work_text);

				// Move processed file to processed folder
				$this->log_write("Moving processed file to " . $this->file_processed);
				rename($this->file_work, $this->file_processed);
				$this->log_write("Finished.");
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