<?php


/**
 * DOMWrapper
 *
 * Provides a SimpleXML-like interface for DOMDocument.
 *
 * PHP version 5
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 * @version   0.9
 * @link    http://www.statedecoded.com/
 * @since   0.9
*/

class DOMWrapper
{
	protected $dom;
	protected $nodeMap;
	public $_type;
	public $_tag;
	public $strip_whitespace = TRUE;

	public function __construct($xml, $strip_whitespace = null)
	{
		if($strip_whitespace !== null) {
			$this->strip_whitespace = $strip_whitespace;
		}

		if(is_object($xml) && get_class($xml) === 'DOMElement')
		{
			$this->dom =& $xml;
		}
		else
		{
			$this->dom = new DOMDocument();
			$this->dom->loadXML($xml);
		}

		switch($this->dom->nodeType)
		{
			case XML_ELEMENT_NODE:
				$this->_type = 'element';
				$this->_tag = $this->dom->nodeName;
				break;

			case XML_TEXT_NODE:
				$this->_type = 'text';
				break;
		}

		// Map our nodes to an internal lookup for easy access.
		foreach($this->dom->childNodes as $node)
		{
			if($node->nodeType === XML_ELEMENT_NODE)
			{
				if(isset($this->nodeMap[$node->nodeName]))
				{
					if(!is_array($this->nodeMap[$node->nodeName]))
					{
						$this->nodeMap[$node->nodeName] = array($this->nodeMap[$node->nodeName]);
					}

					$this->nodeMap[$node->nodeName][] = $node;
				}
				else
				{
					$this->nodeMap[$node->nodeName] = $node;
				}
			}
		}
	}

	public function __get($name)
	{
		if(isset($this->nodeMap[$name]))
		{
			// If this is an array, return a wrapper for the nodes.
			if(is_array($this->nodeMap[$name]))
			{
				return new DOMListWrapper($this->nodeMap[$name], $this->strip_whitespace);
			}
			// If it's a string, just return the string.
			elseif($this->nodeMap[$name]->nodeType === XML_TEXT_NODE)
			{
				return $this->nodeMap[$name]->nodeType->textContent;
			}
			// If it's an element, return a wrapped node.
			elseif($this->nodeMap[$name]->nodeType === XML_ELEMENT_NODE)
			{
				return new DOMWrapper($this->nodeMap[$name], $this->strip_whitespace);
			}
			// Otherwise just return the node.
			return $this->nodeMap[$name];
		}
	}

	public function attribute($name)
	{
		if(isset($this->dom->attributes))
		{
			$attr = $this->dom->attributes->getNamedItem($name);
			if($attr)
			{
				return $attr->value;
			}
		}
	}

	public function children() {
		return new DOMListWrapper($this->dom->childNodes, $this->strip_whitespace );
	}

	public function value() {
		if($this->dom->childNodes->length === 1 &&
			$this->dom->childNodes->item(0)->nodeType === XML_TEXT_NODE)
		{
			return $this->dom->childNodes->item(0)->textContent;
		}
	}

	public function rawValue($html = FALSE) {
    $newdoc = new DOMDocument();
    $cloned = $this->dom->cloneNode(TRUE);
    $newdoc->appendChild($newdoc->importNode($cloned,TRUE));

    if($html)
    {
	    return $newdoc->saveHTML();
   	}
   	else
   	{
   		return $newdoc->saveXML();
   	}
	}

	public function __toString()
	{
		$value = $this->value();
		return is_string($value) ? $value : '';
	}
}

class DOMListWrapper implements Iterator
{
	protected $nodes = array();
	private $position = 0;
	public $strip_whitespace = TRUE;

	public function __construct($nodes, $strip_whitespace = null)
	{
		if($strip_whitespace !== null)
		{
			$this->strip_whitespace = $strip_whitespace;
		}

		$tmp_nodes = array();

		if(is_array($nodes))
		{
			$tmp_nodes = $nodes;
		}
		elseif(is_object($nodes) && get_class($nodes) === 'DOMNodeList')
		{
			$len = $nodes->length;
			for($i=0; $i<$len; $i++)
			{
				$tmp_nodes[] =  $nodes->item($i);
			}
		}

		/*
		 * If we need to, strip any "empty" nodes that are just whitespace.
		 */
		if($strip_whitespace)
		{
			foreach($tmp_nodes as $node)
			{
				if(!($node->nodeType === XML_TEXT_NODE && trim($node->nodeValue) === ''))
				{
					$this->nodes[] = $node;
				}
			}
		}
		else
		{
			$this->nodes = $tmp_nodes;
		}
	}

	public function length() {
		return count($this->nodes);
	}

	public function get($i) {
		if(isset($this->nodes[$i]))
		{
			return new DOMWrapper($this->nodes[$i], $this->strip_whitespace);
		}
	}

	public function rewind() {
		$this->position = 0;
	}

	public function current() {
		return new DOMWrapper($this->nodes[$this->position], $this->strip_whitespace );
	}

	public function key() {
		return $this->position;
	}

	public function next() {
		++$this->position;
	}

	public function valid() {
		if(isset($this->nodes) && is_array($this->nodes) && isset($this->nodes[$this->position]))
		{
			return ($this->nodes[$this->position] !== null);
		}
		else {
			return false;
		}
	}
}
