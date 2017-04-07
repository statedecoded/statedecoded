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

  public function create($name, $value = null, $attributes = array()) {
    $elm = $this->dom->createElement($name);

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
      $elm->appendChild($this->dom->createTextNode($value));
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