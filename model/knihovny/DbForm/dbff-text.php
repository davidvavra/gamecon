<?php

/**
 * @todo distinguish between nulls and empty strings
 */
class DbffText extends DbFormField {

  function html() {
    return
      '<textarea name="'.$this->postName().'">'.
      htmlspecialchars($this->value()).
      '</textarea>';
  }

  function loadPost() {
    $this->value($this->postValue());
  }

}
