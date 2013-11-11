<?php

/**
 * The Page class, for rendering HTML and delivering it to the browser
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.8
 * @link		http://www.statedecoded.com/
 * @since		0.1
 *
 * Since all rendering occurs in-class, you can override the rendering method
 * in your theme's Page class to use a totally different rendering engine (Twig,
 * Smarty, or straight PHP) if you'd like!
 *
 */

/**
 * Turn the variables provided by each page into a rendered page.
 */
class Page
{

	public $html;
	public $page = 'default';
	public $template_file = '';
	public $theme_name = '';
	public $theme_dir = '';
	public $theme_web_path = '';
	public $static_path = '';

	public $assets = array(
		/* 'asset_name' => array(
				'path' => '/relative/path/to/file.css',
				'resolved_path' => '/absolute/path/to/file.css', // This is generated.
				'type' => 'stylesheet', // or javascript or whatever.
				'requires' => array('first_asset_name', 'second_asset_name')) */
	);

	public function __construct($page=null)
	{
		/*
		 * Set defaults
		 */
		if(strlen($this->theme_name) === 0)
		{
			$this->theme_name = THEME_NAME;
		}
		if(strlen($this->theme_dir) === 0)
		{
			$this->theme_dir = THEME_DIR;
		}
		if(strlen($this->theme_web_path) == 0)
		{
			$this->theme_web_path = THEME_WEB_PATH;
		}
		if(strlen($this->static_path) == 0)
		{
			$this->static_path = $this->theme_web_path . 'static/';
		}

		/*
		 * Pre-parse assets to set the full path.
		 */
		$this->add_assets($this->assets);

		if (isset($page))
		{
			$this->page = $page;
		}
		$this->template_file = $this->theme_dir . $this->page . '.inc.php';

		$this->html = $this->load_template($this->template_file);
	}

	/**
	 * Get our template data
	 */
	public function load_template($template_file)
	{
		/*
		 * Save the contents of the template file to a variable. First check APC and see if it's
		 * stored there.
		 */
		$storage_name = 'template-'.$this->theme_name.'-'.$this->page;
		if ( APC_RUNNING === TRUE)
		{
			$html = apc_fetch($storage_name);
			if ($html === FALSE)
			{


				if (check_file_available($template_file))
				{
					$html = file_get_contents($template_file);
				}

				apc_store($storage_name, $html);
			}
		}
		else
		{
			$html = file_get_contents($template_file);
		}

		return $html;
		
	}


	/**
	 * A shortcut for all steps necessary to turn variables into an output page.
	 */
	public function parse($content)
	{
		return $this->display($this->render($content));
	}


	/**
	 * Combine the populated variables with the template.
	 */
	public function render($content)
	{
	
		/*
		 * Make a copy of the template here, so we can re-render as often
		 * as we like with new content.
		 */
		$template = $this->html;

		$this->before_render($template, $content);

		/*
		 * Replace all of our in-page tokens with our defined variables.
		 */
		foreach ($content->get() as $field=>$value)
		{
			$template = str_replace('{{' . $field . '}}', $value, $template);
		}

		$this->after_render($template, $content);

		return $template;
		
	}


	/**
	 * Pre-rendering.
	 */
	public function before_render(&$template, &$content)
	{

	}


	/**
	 * Post-rendering.
	 */
	public function after_render(&$template, &$content)
	{

	}


	/**
	 * Send the page to the browser.
	 */
	public function display($content)
	{

		if (!isset($content))
		{
			return FALSE;
		}

		echo $content;
		return TRUE;

	}


	/**
	 * Add a new asset.
	 */
	public function add_asset($name, $asset = array())
	{
	
		/*
		 * If we have a local path.
		 */
		if (substr($asset['path'], 0, 1) === '/' && substr($asset['path'], 0, 2) !== '//')
		{
			/*
			 * Drop the leading slash.
			 */
			$asset['resolved_path'] = $this->static_path . substr($asset['path'], 1);
		}
		else
		{
			$asset['resolved_path'] = $asset['path'];
		}

		if(isset($requires))
		{
			$asset['requires'] = $requires;
		}
		$this->assets[$name] = $asset;
		
	}


	/**
	 * Add an array of assets.
	 */
	public function add_assets($assets)
	{
	
		foreach ($assets as $name => $asset)
		{
			$this->add_asset($name, $asset);
		}
		
	}


	/**
	 * Parse through assets and resolve dependencies.
	 */
	public function parse_assets()
	{
	
		$collated_assets = array();

		foreach ($this->assets as $name => $asset)
		{
			$this->build_asset($collated_assets, $name, $asset);
		}

		return $collated_assets;
		
	}


	/**
	 * Internal asset builder for final asset rendering
	 * Edits $collated_assets in place!
	 */
	public function build_asset(&$collated_assets, $name, $asset)
	{
	
		$type = $asset['type'];
		
		/*
		 * Resolve requirements.
		 */
		if (isset($asset['requires']))
		{
		
			foreach ($asset['requires'] as $required)
			{
			
				if (!in_array($required, array_keys($this->assets)))
				{
					throw new Exception('Required asset "' . $required .
						'" not defined, from parent "' . $name .'"');
				}
				
				else
				{

					/*
					 * If the requirement has not yet been satisfied, we recurse to add it.
					 */
					$resolved_path = $this->assets[$required]['resolved_path'];
					$asset_type = $this->assets[$required]['type'];
					if(!isset($collated_assets[$type]))
					{
						$collated_assets[$type] = array();
					}

					if (!in_array(
						$resolved_path,
						$collated_assets[$type]))
					{
						$collated_assets = $this->build_asset($collated_assets, $required,
							$this->assets[$required]);
					}
					
				} /* !in_array($required ... */
				
			} /* foreach ($asset['requires'] */
			
		} /* if (isset($asset['requires'] .. */

		/*
		 * The easy part - just add the asset.
		 * We collate these into types, for ease of display.
		 */
		$collated_assets[ $type ][] = $asset['resolved_path'];

		return $collated_assets;

	} /* function build_asset */

}
