<?php

$config_dir = "/ocr_sidekick_mount/config";
$input_dir = "/ocr_sidekick_mount/0_input";
$output_dir = "/ocr_sidekick_mount/0_output";
$work_dir = "/ocr_sidekick_mount/workdir";
$log_dir = "/ocr_sidekick_mount/logs";
$temp_dir = "/ocr_sidekick_mount/temp";

$files = array();

# Read Config
require_once($config_dir . "/config.php");

# Include Telegram API
require_once("vendor/autoload.php");
use \unreal4u\TelegramAPI\HttpClientRequestHandler;
use \unreal4u\TelegramAPI\TgLog;
use \unreal4u\TelegramAPI\Telegram\Methods\SendMessage;
if (isset($telegram_token))
{
	$loop = \React\EventLoop\Factory::create();
	$handler = new HttpClientRequestHandler($loop);
	$tgLog = new TgLog($telegram_token, $handler);
}


function log_write($text)
{
	global $log_dir;

	$logdate = date("Y-m-d", time());
	$logfile = $log_dir . "/" . $logdate . ".log";
	$logfile_handle = fopen($logfile, "a+");
	
	$logtime = date("Y-m-d, H:i:s", time());
	fwrite($logfile_handle, ($logtime . ": " . $text . "\n"));
	
	fclose($logfile_handle);
}

function read_output($output_file)
{
	global $temp_dir;

	if(is_file($temp_dir . "/" . $output_file))
	{
		$output_file_handle = fopen($temp_dir . "/" . $output_file, "r");
		while ($output_text = fgets($output_file_handle))
		{
			$output_text = str_replace("\n", "", $output_text);
			log_write($output_text);
		}
		fclose($output_file_handle);
		unlink($temp_dir . "/" . $output_file);
	}

}

while(true)
{
	# Read INPUT dir
	if (is_dir($input_dir))
	{
		if ($input_dir_handle = opendir($input_dir))
		{

			while (($file = readdir($input_dir_handle)) !== false)
			{
				if (substr($file, -3) == "pdf") array_push($files, $file);
			}
			closedir($input_dir_handle);
		}
	}

	# Run OCR
	foreach($files as $file)
	{
		# Initialize Telegram Message
		$telegram_message = "";

		# Generate temporary filename
		$temp_filename = substr(md5(time()), 0, 10);
		
		# Set working files
		$input_file = $input_dir . "/" . $file;
		$output_file = $output_dir . "/ocr/" . $file;
		$work_file = $work_dir . "/" . $temp_filename . ".pdf";
		$work_file_text = $work_dir . "/" . $temp_filename . ".txt";
		$work_file_ocr = $work_dir . "/" . $temp_filename . "_ocr.pdf";

		# Initialize OCR text
		$ocr_text = "";

		log_write("-----");
		log_write("Found new PDF: " . $file);
		$telegram_message .= "Found new PDF: " . $file . "\n";

		# Move PDF to work dir...
		log_write("Moving PDF to work directory...");
		rename($input_file, $work_file);

		# Run OCR
		log_write("Starting OCR");
		$command = "ocrmypdf -r -d -s -l deu+eng+fra --clean --output-type pdf " . $work_file . " " . $work_file_ocr . " >" . $temp_dir . "/ocrmypdf.txt 2>&1";
		$output = shell_exec($command);
		read_output("ocrmypdf.txt");

		# Extract Text
		if (is_file($work_file_ocr))
		{
			log_write("Extracting Text");
			$command = "pdftotext -nopgbrk -eol unix " . $work_file_ocr . " " . $work_file_text . " >" . $temp_dir . "/pdftotext.txt 2>&1";
			$output = shell_exec($command);
			read_output("pdftotext.txt");
		}

		
		if (is_file($work_file_text))
		{
			if($work_file_text_handle = fopen($work_file_text, "r"))
			{
				while ($ocr = fgets($work_file_text_handle))
				{
					$ocr = str_replace("\n", " ", $ocr);
					$ocr_text .= " " . $ocr;
				}
				fclose($work_file_text_handle);
			}
		}
		echo $ocr_text;

		# Convert to PDF/A
		log_write("Converting to PDF/A");
			$command = "ocrmypdf -s --output-type pdfa " . $work_file_ocr . " " . $output_file . ">" . $temp_dir . "/ocrmypdf.txt 2>&1";
			$output = shell_exec($command);
			read_output("ocrmypdf.txt");

		log_write("Setting access rights for output file...");
		chmod($output_file, 0755);

		# Move processed file to processed folder
		log_write("Moving processed file to " . $output_dir . "/processed/" . $file);
		rename($work_file, $output_dir . "/processed/" . $file);
		log_write("Finished.");
		
		if (isset($telegram_token))
		{
			$sendMessage = new SendMessage();
			$sendMessage->chat_id = $telegram_recipient;
			$sendMessage->text = $telegram_message;

			$tgLog->performApiRequest($sendMessage);
			$loop->run();
		}
	}
}
?>
