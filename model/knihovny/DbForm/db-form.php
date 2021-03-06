<?php

class DbForm {

  private $table;
  private $fields;
  private $postName = 'cDbForm';

  /**
   * Creates default (full) form for given table
   * @todo check if table exists?
   */
  function __construct($table) {
    $this->table = $table;
  }

  /**
   * Creates new field, field type (class) based on given description.
   * This should be overriden in inherited per-project implementations to add
   * custom field classes fitting project needs.
   * @todo thus, make this abstract and force custom implementation?
   */
  protected function fieldFromDescription($d) {
    if($d['Key'] == 'PRI') return new DbffPkey($d);
    if(in_array($d['Type'], ['text', 'shorttext', 'mediumtext', 'longtext'])) return new DbffText($d);
    // fallback to string
    return new DbffString($d);
  }

  /**
   * Returns assoc. array of table / form fields. Array is structured like
   * 'column_name' => (obj)Field
   */
  protected function fields() {
    if(!isset($this->fields)) $this->fieldsInit();
    return $this->fields;
  }

  /** Responsibility obvious */
  protected function fieldsInit() {
    foreach(dbDescribe($this->table()) as $d) {
      $f = $this->fieldFromDescription($d);
      $this->fields[$f->name()] = $f;
    }
  }

  /**
   * Returns full editor / form html code
   * @todo inline() alternative for inline editor (excel style)
   * @todo don't rely on xtpl or only bundle compiled result
   */
  function full() {
    $t = new XTemplate(__DIR__.'/db-form.xtpl');
    foreach($this->fields() as $f) {
      $t->assign('field', $f);
      if($f->display() == DbFormField::RAW)         $t->parse('form.raw');
      elseif($f->display() == DbFormField::CUSTOM)  $t->parse('form.custom');
      else                                          $t->parse('form.row');
    }
    $t->assign('submitted', $this->postName().'[submitted]');
    $t->parse('form');
    return $t->text('form');
    //TODO add one field to $this->postName to check in processPost
  }

  /**
   * Fills form with given database row $r (as associative array)
   */
  function loadRow($r) {
    foreach($this->fields() as $f) $f->value($r[$f->name()]);
  }

  /**
   * Fills form with post data
   * @todo prefix might or might not be set here, individual post variables are handled by fields themselves
   */
  protected function loadPost() {
    foreach($this->fields() as $f) $f->loadPost();
  }

  /**
   * Gets/sets POST namespace (/prefix) where DbForm variable(s) will be stored
   * @todo fix this to accordance with inherited classes
   */
  function postName($val = null) {
    if(isset($val)) $this->postName = $val;
    else return $this->postName;
  }

  /**
   * Processes post data for this form (if any) and redirects to caller,
   * otherwise does nothing.
   * @todo what if fields list (in post) is incomplete? Throw error? Allow this
   *  (for case of restricted privileges) but only for update? ...?
   * @todo remove is_ajax and die in favor of methods on request object or
   *  something like that, which should be passed as parameter or injected.
   */
  function processPost() {
    if(empty($_POST[$this->postName()])) return;
    try {
      $this->loadPost();
      // preparations when needed
      foreach($this->fields() as $f) $f->preInsert();
      // master record update
      $r = [];
      $pkey = null;
      $newId = null;
      foreach($this->fields() as $f) {
        $r[$f->name()] = $f->value();
        if($f instanceof DbffPkey) $pkey = $f;
      }
      if($pkey->value()) {
        dbUpdate($this->table(), $r, [$pkey->name() => $pkey->value()]);
      } else {
        dbInsert($this->table(), $r);
        $newId = dbInsertId();
      }
      // final cleanup
      foreach($this->fields() as $f) $f->postInsert();
    } catch(Exception $e) {
      if(is_ajax()) die(json_encode(['error' => $e->getMessage()]));
      else throw $e;
    }
    // redirect
    if(is_ajax()) die(json_encode(['id' => $newId]));
    else header('Location: '.$_SERVER['HTTP_REFERER'], true, 303);
  }

  /**
   * Returns table name. Form is created based on this table
   */
  protected function table() {
    return $this->table;
  }

}
