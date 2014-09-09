<?php

class Novinka {

  protected $r;

  protected static $prvniObrazek = '@!\[\]\(([^\)]+)\)@'; // RV odpovídající prvnímu obrázku v textu

  const NOVINKA = 1;
  const BLOG = 2;

  protected function __construct($r) {
    $this->r = $r;
  }

  function datum() {
    return date('j.n.', strtotime($this->r['vydat']));
  }

  /** Prvních $n znaků příspěvku */
  function nahled($n = 400) {
    $t = markdown(
      preg_replace(self::$prvniObrazek, '',
        mb_substr($this->r['text'], 0, $n)
      )
    );
    if($t[3] == '_') $t[3] = ' ';
    return $t;
  }

  function nazev() {
    return $this->r['nazev'];
  }

  function obrazek() {
    preg_match(self::$prvniObrazek, $this->r['text'], $m);
    return $m[1];
  }

  function text() {
    return markdown($this->r['text']);
  }

  /** název enkódovaný do url formátu */
  function url() {
    return $this->r['url'];
  }

  static function zId($id) {
    return self::zWhere('id = $1', array($id));
  }

  static function zNejnovejsi($typ = self::NOVINKA) {
    return self::zWhere('vydat <= NOW() AND typ = $1 ORDER BY vydat DESC LIMIT 1', array($typ));
  }

  static function zUrl($url, $typ = self::NOVINKA) {
    return self::zWhere('url = $1 AND typ = $2', array($url, $typ));
  }

  protected static function zWhere($where, $params = null) {
    $r = dbOneLineS('SELECT * FROM novinky WHERE '.$where, $params);
    if($r) return new self($r);
    else return null;
  }

}

