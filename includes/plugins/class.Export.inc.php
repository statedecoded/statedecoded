<?php

/**
 * Export base class
 *
 * Note: I've tried to give a concrete implementation
 * example below for extension.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.9
 *
 */

abstract class Export extends Plugin
{
	public $listeners = array(
		'exportLaw',
		'finishExport'
	);

	public $format = 'data';
	public $extension = '.data';

	// The export should not prescribe paths,
	// it should just add its own
	public function createExportDir($path)
	{
		mkdir_safe($path);

		return $path;
	}

	public function clearExportDir($path) {
		remove_dir($path);
	}

	public function writeLawFile($filename, $data)
	{
		$this->logger->message("Writing $filename<br>", 1);
		file_put_contents($filename, $data);
	}

	public function exportLaw($law, $dir)
	{
		list($this->path, $this->filename) = $this->getLawPaths($law, $dir);

		$this->createExportDir($this->path);
		$this->writeLawFile(
			join_paths($this->path, $this->filename),
			$this->formatLawForExport($law)
		);

		return TRUE;
	}

	public function formatLawForExport($law)
	{
		return var_export($law, true);
	}

	public function getLawPaths($law, $dir)
	{
		/*
		 * Remove colons, etc. from tokens, since some OSes can't handle these.
		 */
		$token = str_replace(':', '_', $law->token);
		$tokens = explode('/', $token);
		$filebase = array_pop($tokens);

		$path = join_paths($dir, 'code-' . $this->format, $tokens);
		$filename = $filebase . $this->extension;

		return array($path, $filename);
	}

	/*
	 * We wrap up our export by creating a zip archive of the contents.
	 */
	public function finishExport($downloads_dir)
	{
		$filename = 'code-' . $this->format;

		$zip_filename = join_paths($downloads_dir, $filename . '.zip');

		$zip = $this->generateZip($zip_filename);

		if($zip)
		{
			$export_directory = join_paths($downloads_dir, 'code-' . $this->format);
			$this->addDirectoryToZip($zip, $export_directory);
			$zip->close();
		}
		else {
			$this->logger('Unable to create zip archive.', 10);
		}
	}

	/*
	 * We don't create dictionaries for every format, but we include the code so
	 * this is easier for those that do.
	 */
	public function exportDictionary($dictionary, $downloads_dir)
	{
		$this->logger->message('Creating dictionary.', '10');
		/*
		 * Define the filename for our dictionary.
		 */
		$filename = 'dictionary' . $this->extension;

		$zip_filename = join_paths($downloads_dir, $filename . '.zip');

		$zip = $this->generateZip($zip_filename);

		if($zip)
		{
			$zip->addFromString($filename,
				$this->formatDictionaryForExport($dictionary));

			$zip->close();
		}
		else {
			$this->logger('Unable to create zip archive.', 10);
		}

		$this->logger->message('Created a ZIP file of all dictionary terms as JSON', 3);
	}

	public function generateZip($zip_filename)
	{
		if (file_exists($zip_filename))
		{
			unlink($zip_filename);
		}

		/*
		 * Create a new ZIP file object.
		 */
		$zip = new ZipArchive();

		/*
		 * If we cannot create a new ZIP file, bail.
		 */
		if ($zip->open($zip_filename, ZIPARCHIVE::CREATE) !== TRUE)
		{
			return false;
		}
		return $zip;
	}

	public function addDirectoryToZip(&$zip, $directory)
	{
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($files as $name => $file)
		{
			if (!$file->isDir())
			{
				// Get real and relative path for current file
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($directory) + 1);

				// Add current file to archive
				$zip->addFile($filePath, $relativePath);
			}
		}
	}
}
