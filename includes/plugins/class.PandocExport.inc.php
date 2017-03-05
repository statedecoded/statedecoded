<?php

/**
 * Pandoc Export base class
 *
 * Used to create the various exports that use Pandoc.
 *
 * PHP version 5
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 * @version   0.9
 * @link    http://www.statedecoded.com/
 * @since   0.9
 *
 */

 abstract class PandocExport extends Export
 {

	/*
	 * This class uses the already generated HTML files, translating them into
	 * the destination formats.
	 */
	public $listeners = array(
		'HTMLExportLaw',
		'HTMLExportStructure',
		'HTMLFinishExport'
	);

	public function HTMLExportLaw($law, $dir, $html_filename, $content)
	{
		list($path, $filename) = $this->getLawPaths($law, $dir);

		$this->createExportDir($path);

		$cmd = 'pandoc ' . $html_filename . ' -o ' . join_paths($path, $filename);
		exec($cmd);
	}

	public function HTMLExportStructure($structure, $laws, $dir, $html_filename, $content)
	{
		list($path, $filename) = $this->getStructurePaths($structure, $dir);

		$this->createExportDir($path);

		$cmd = 'pandoc ' . $html_filename . ' -o ' . join_paths($path, $filename);
		exec($cmd);
	}

	public function HTMLFinishExport($exported, $downloads_dir)
	{
		$filename = join_paths($downloads_dir, $this->getFullDownloadName());

		/*
		 * We need a title page.
		 */
		$title_page = $this->createTitlePage($downloads_dir);

		$input_files = array($title_page);

		foreach($exported as $file) {
			$input_files[] = $file['filename'];
		}

		/*
		 * We need a page at the end to clear our title.
		 */
		$end_page = $this->createEndPage($downloads_dir);
		$input_files[] = $end_page;

		$cmd = 'pandoc -S ' . join(' ', $input_files) . ' -o ' . $filename;
		exec($cmd);

		/*
		 * Remove the unneeded pages.
		 */
		unlink($title_page);
		unlink($end_page);
	}

	/*
	 * These files all use a multiple-page document, not a zipped archive as most
	 * Exports do.
	 */
	public function getFullDownloadName() {
		return 'code' . $this->extension;
	}

	/*
	 * For our all-in-one export, we need a title page in many cases.
	 */
	public function createTitlePage($downloads_dir)
	{
		$filename = join_paths($downloads_dir, 'code-title.html');

		$content = '<html>
			<head><title>' . SITE_TITLE . ' - ' . LAWS_NAME . '</title></head>
			<body><h1>' . SITE_TITLE . '</h1><h2> ' . LAWS_NAME . '</h2></body>
		</html>';

		file_put_contents($filename, $content);

		return $filename;
	}

	/*
	 * We also need to override the last page so our title is correct.
	 */
	public function createEndPage($downloads_dir)
	{
		$filename = join_paths($downloads_dir, 'code-end.html');

		$content = '<html>
			<head><title>' . SITE_TITLE . ' - ' . LAWS_NAME . '</title></head>
			<body></body>
		</html>';

		file_put_contents($filename, $content);

		return $filename;
	}
}
