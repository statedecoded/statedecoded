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
		// 'exportStructure', // Not enabled by default
		// 'exportDictionary', // Not enabled by default
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

	public function clearExportDir($path)
	{
		remove_dir($path);
	}

	/*
	 * Law export
	 */
	public function writeLawFile($filename, $data)
	{
		$this->logger->message("Writing $filename<br>", 1);
		file_put_contents($filename, $data);
	}

	public function exportLaw($law, $dir)
	{
		list($this->path, $this->filename) = $this->getLawPaths($law, $dir);

		$this->createExportDir($this->path);

		$filename = join_paths($this->path, $this->filename);
		$content = $this->formatLawForExport($law);

		$this->writeLawFile($filename, $content);

		return array($filename, $content);
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
	 * Structure export
	 */
	public function writeStructureFile($filename, $data)
	{
		$this->logger->message("Writing $filename<br>", 1);
		file_put_contents($filename, $data);
	}

	public function exportStructure($structure, $laws, $dir)
	{
		list($this->path, $this->filename) = $this->getStructurePaths($structure, $dir);

		$this->createExportDir($this->path);

		$filename = join_paths($this->path, $this->filename);
		$content = $this->formatStructureForExport($structure, $laws);

		$this->writeStructureFile($filename, $content);

		return array($filename, $content);
	}

	public function formatStructureForExport($structure, $laws = array())
	{
		return var_export($structure, true);
	}

	public function getStructurePaths($structure, $dir)
	{
		/*
		 * Remove colons, etc. from tokens, since some OSes can't handle these.
		 */
		$token = str_replace(':', '_', $structure->permalink->token);
		$tokens = explode('/', $token);

		$path = join_paths($dir, 'code-' . $this->format, $tokens);
		$filename = 'index' . $this->extension;

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
		$content = $this->formatDictionaryForExport($dictionary);

		$zip_filename = join_paths($downloads_dir, $filename . '.zip');

		$zip = $this->generateZip($zip_filename);

		if($zip)
		{
			$zip->addFromString($filename, $content);

			$zip->close();
		}
		else {
			$this->logger('Unable to create zip archive.', 10);
		}

		$this->logger->message('Created a ZIP file of all dictionary terms as JSON', 3);

		return array($filename, $content);
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

	/*
	 * Recursively add a directory to a Zip archive.
	 * Takes an optional $prefix to prepend to the path inside the zip file.
	 */
	public function addDirectoryToZip(&$zip, $directory, $prefix = false)
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

				if($prefix)
				{
					$relativePath = join_paths($prefix, $relativePath);
				}

				// Add current file to archive
				$zip->addFile($filePath, $relativePath);
			}
		}
	}
}
