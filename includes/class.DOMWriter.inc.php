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
     * If we've got tags, create an XML fragment.
     */
    elseif(is_string($value) && strip_tags($value) !== $value)
    {
      $fragment = $this->dom->createDocumentFragment();
      $fragment->appendXML($value);
      $elm->appendChild($fragment);
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

  public function append(&$elm)
  {
    $this->root->appendChild($elm);
  }

  public function __toString()
  {
    return $this->dom->saveXML();
  }
}