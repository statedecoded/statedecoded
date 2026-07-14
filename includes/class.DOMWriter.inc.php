<?php

class DOMWriter {
  public $dom;
  public $root;

  public function __construct($root, &$dom = null)
  {
    if(empty($dom))
    {
      $this->dom = new DOMDocument();
    }
    else
    {
      $this->dom =& $dom;
    }

    if(is_string($root))
    {
      $this->root = $this->dom->appendChild($this->dom->createElement($root));
    }
    elseif(is_object($root))
    {
      $this->root =& $root;
    }
  }

  public function create($name, $value = null, $attributes = []) {
    /*
     * XML element names cannot start with a digit or contain arbitrary
     * characters, but the names passed here can be arbitrary metadata keys --
     * or integer indexes, when a metadata value is a list. Rather than let
     * createElement() throw an "Invalid Character Error" (which aborts the
     * entire export), fall back to a generic <item> element and preserve the
     * original key as an attribute.
     */
    $element_name = (string) $name;
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/', $element_name))
    {
      $attributes = ['key' => $element_name] + $attributes;
      $element_name = 'item';
    }

    $elm = $this->dom->createElement($element_name);

    /*
     * If we have an object or array, iterate over the properties and add them.
     */
    if(is_object($value) || is_array($value))
    {
      foreach($value as $key=>$val)
      {
        $obj = $this->create($key, $val);
        $elm->appendChild($obj->root);
      }
    }
    /*
     * If we've got tags, embed the markup as child nodes.
     */
    elseif(is_string($value) && strip_tags($value) !== $value)
    {
      $elm->appendChild($this->markupToNode($value));
    }
    /*
     * Otherwise, just append the content.
     */
    else
    {
      $elm->appendChild($this->dom->createTextNode($value ?? ''));
    }

    if(count($attributes))
    {
      foreach($attributes as $key => $value)
      {
        $attr = $this->dom->createAttribute($key);
        $attr->value = $value;
        $elm->appendChild($attr);
      }
    }

    $this->root->appendChild($elm);

    return new DOMWriter($elm, $this->dom);
  }

  /*
   * Embed a string of markup as DOM child nodes.
   *
   * The markup is meant to become XML child nodes, but law text is HTML and is
   * not guaranteed to be well-formed XML (unescaped attribute quotes, unclosed
   * tags, and so on). DOMDocumentFragment::appendXML() requires strict XML and,
   * on any error, emits a PHP warning and silently drops the *entire* node --
   * which is how laws were being exported with empty <text> elements. So:
   *
   *   1. Try a strict parse (the fast path for well-formed content).
   *   2. Fall back to libxml's lenient HTML parser.
   *   3. As a last resort, preserve the raw markup verbatim in a CDATA section
   *      so content is never lost.
   *
   * libxml errors are captured rather than emitted as warnings throughout.
   */
  protected function markupToNode($markup)
  {
    $previous = libxml_use_internal_errors(true);

    /*
     * 1. Strict XML fast path -- preserves the existing output for the
     *    well-formed majority of laws.
     */
    $fragment = $this->dom->createDocumentFragment();
    if(@$fragment->appendXML($markup) && $fragment->hasChildNodes())
    {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
      return $fragment;
    }

    /*
     * 2. Lenient HTML parse. Wrap in a container so we can pull the children
     *    back out without the implied <html>/<body> scaffolding.
     */
    $html_doc = new DOMDocument();
    $wrapped = '<?xml encoding="UTF-8"?><div>' . $markup . '</div>';
    if(@$html_doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD))
    {
      $container = $html_doc->getElementsByTagName('div')->item(0);
      if($container)
      {
        $fragment = $this->dom->createDocumentFragment();
        foreach(iterator_to_array($container->childNodes) as $child)
        {
          $fragment->appendChild($this->dom->importNode($child, true));
        }

        if($fragment->hasChildNodes())
        {
          libxml_clear_errors();
          libxml_use_internal_errors($previous);
          return $fragment;
        }
      }
    }

    /*
     * 3. Last resort: keep the raw markup as CDATA rather than lose it.
     */
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $this->dom->createCDATASection($markup);
  }

  public function append(&$elm)
  {
    $this->root->appendChild($elm);
  }

  public function __toString()
  {
    return $this->dom->saveXML();
  }
}