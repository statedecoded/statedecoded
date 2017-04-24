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
 * @version		1.0
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
		'finishExport',
		'postGetLaw',
		'showBulkDownload'
	);

	/*
	 * The name to display on pages.
	 */
	public $public_name = 'Raw Data';

	/*
	 * The internal name for the directories, etc.
	 */
	public $format = 'data';

	/*
	 * The file extension to use for this type.
	 */
	public $extension = '.data';

	/*
	 * A general description for the bulk download for this type. Used on the
	 * Downloads page.
	 */
	public $description = 'A generic data file type.';

	/*
	 * A description for the dictionary download for this type. Used on the
	 * Downloads page.
	 */
	public $dictionary_description = 'All terms defined in the laws, with each
			term’s definition, the section in which it is defined, and the scope
			(section, chapter, title, global) of that definition.';


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
		// Trim the beginning and trailing slash from our URL
		$token = trim($law->url, '/');

		$tokens = explode('/', $token);
		$filebase = array_pop($tokens);

		$path = join_paths($dir, 'code-' . $this->format, $tokens);
		$filename = $filebase;
		if(isset($law->metadata) && isset($law->metadata->dupe_number))
		{
			$filename .= '_' . $law->metadata->dupe_number;
		}

		$filename .= $this->extension;

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
		if(constant('LAW_LONG_URLS'))
		{
			/*
			 * Remove colons, etc. from tokens, since some OSes can't handle these.
			 */
			$token = str_replace(':', '_', $structure->permalink->token);
			$tokens = explode('/', $token);

			$path = join_paths($dir, 'code-' . $this->format, $tokens);
			$filename = 'index' . $this->extension;
		}
		else
		{
			// Structures need particular handling for short urls.

			// Trim the beginning and trailing slash from our URL
			$token = trim($structure->permalink->url, '/');

			$token = str_replace('/', '_', $token);

			$path = join_paths($dir, 'code-' . $this->format);
			$filename = $token . $this->extension;
		}

		return array($path, $filename);
	}

	/*
	 * We wrap up our export by creating a zip archive of the contents.
	 */
	public function finishExport($downloads_dir)
	{
		$filename = $this->getFullDownloadName();

		$zip_filename = join_paths($downloads_dir, $filename);

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

	public function getFullDownloadName() {
		return 'code-' . $this->format . '.zip';
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
		$filename = $this->getDictionaryDownloadName();
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

	public function getDictionaryDownloadName() {
		return 'dictionary' . $this->extension . '.zip';
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
	/*
	 * Set this as an available format to download.
	 */
	public function postGetLaw(&$law)
	{
		if(isset($law->permalink->url))
		{
			$url = '/downloads/' . $law->edition->slug . '/code-' . $this->format .
				'/' . trim($law->permalink->url, '/');

			if(isset($law->metadata) && isset($law->metadata->dupe_number))
			{
				$url .= '_' . $law->metadata->dupe_number;
			}
			$url .= $this->extension;

			$law->formats[] = array(
				'name' => $this->public_name,
				'format' => $this->format,
				'url' => $url
			);
		}
	}

	/*
	 * Show a blurb and download link on the Downloads page. This should only be
	 * used for export types that have the entire code in one file (usually
	 * created in finishExport).
	 */
	public function showBulkDownload($slug = 'current') {
		$filename = $this->getFullDownloadName();
		$url = '/downloads/' . $slug . '/' . $filename;

		$content = '<h2>Laws as ' . $this->public_name . '</h2>
			<a href="' . $url . '">' . $filename . '</a>
			<p>' . $this->description . '</p>
			';
		// If we have an exported dictionary, show that here too.
		if(in_array('exportDictionary', $this->listeners) !== FALSE)
		{
			$dictionary_filename = $this->getDictionaryDownloadName();
			$dictionary_url = '/downloads/' . $slug . '/' . $dictionary_filename;

			$content .= '<h2>Dictionary as ' . $this->public_name . '</h2>
				<a href="' . $dictionary_url . '">' . $dictionary_filename . '</a>
				<p>' . $this->dictionary_description . '</p>
				';
		}

		return $content;
	}

}
