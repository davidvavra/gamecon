<?php

/**
 * Load one column into array in $id => $value manner
 */
function dbArrayCol($q, $param = null) {
  $a = dbQueryS($q, $param);
  $o = array();
  while($r = mysql_fetch_row($a)) {
    $o[$r[0]] = $r[1];
  }
  return $o;
}

function dbConnect()
{
  global $spojeni, $dbLastQ, $dbNumQ, $dbExecTime;
  if($spojeni==NULL)
  {
    // inicializace glob. nastavení
    $dbhost = DB_SERV;
  	$dbname = DB_NAME;
  	$dbuser = DB_USER;
  	$dbpass = DB_PASS;
  	$spojeni=NULL;
  	$dbLastQ='';     //vztahuje se pouze na dotaz v aktualnim skriptu
  	$dbNumQ=0;       //počet dotazů do databáze
  	$dbExecTime=0.0; //délka výpočtu dotazů
    // připojení
    $start=microtime(true);
    $spojeni=mysql_connect($dbhost, $dbuser, $dbpass);
    if(!$spojeni) die(mysql_error($spojeni));
    mysql_select_db($dbname, $spojeni);
    mysql_set_charset('utf8', $spojeni);
    $end=microtime(true);
    $GLOBALS['dbExecTime']+=$end-$start;
  }
}

/** Deletes from $table where all $whereArray column => value conditions are met */
function dbDelete($table, $whereArray) {
  $where = array();
  foreach($whereArray as $col => $val) {
    $where[] = dbQi($col).' = '.dbQv($val);
  }
  if(!$where) throw new Exception('DELETE … WHERE caluse must not be empty');
  dbQuery('DELETE FROM ' . dbQi($table) . ' WHERE ' . implode(' AND ', $where));
}

/** Returns 2D array with table structure description */
function dbDescribe($table) {
  $a = dbQuery('show full columns from '.dbQi($table));
  $out = array();
  while($r = mysql_fetch_assoc($a)) $out[] = $r;
  return $out;
}

function dbLastId()
{
  return mysql_insert_id($GLOBALS['spojeni']);
}

function dbInsert($table,$valArray)
{
  global $spojeni, $dbLastQ;
  dbConnect();
  $sloupce='';
  $hodnoty='';
  foreach($valArray as $sloupec => $hodnota)
  {
    if($hodnota===NULL)
    {
      $sloupce.=$sloupec.',';
      $hodnoty.='NULL,';
    }    
    else
    {
      $sloupce.=$sloupec.',';
      if(!get_magic_quotes_gpc()) //vstup není slashován
        $hodnota=addslashes($hodnota);
      $hodnoty.='"'.$hodnota.'",';
    }  
  }
  $sloupce=substr($sloupce,0,-1); //useknutí přebytečné čárky na konci
  $hodnoty=substr($hodnoty,0,-1);
  $q='INSERT INTO '.$table.' ('.$sloupce.') VALUES ('.$hodnoty.')';
  $dbLastQ=$q;
  if(!mysql_query($q, $spojeni)) throw new DbException();
}


/** Insert with actualisation */
function dbInsertUpdate($table,$valArray)
{
  global $dbspojeni, $dbLastQ;
  dbConnect();
  $update='INSERT INTO '.$table.' SET ';
  $dupl=' ON DUPLICATE KEY UPDATE ';
  $vals='';
  foreach($valArray as $key=>$val)
  {
    if($val===NULL)
      $vals .= $key.'=NULL, ';
    else
      $vals .= $key.'='.dbQv($val).', ';
  }
  $vals=substr($vals,0,-2); //odstranění čárky na konci
  $q=$update.$vals.$dupl.$vals; 
  $dbLastQ=$q;
  $start=microtime(true);
  $r=mysql_query($q,$GLOBALS['spojeni']);
  $end=microtime(true);
  if(!$r) throw new DbException();
}


function dbQuery($q1,$q2=null,$q3=null)
{
  global $spojeni,$dbLastQ;
  if($q2) //používáme přístup jako k mysql_db_query - sql dotaz je 2. parametr
    $q=$q2;
  else //klasický přístup pouze s jedním parametrem - dotazem
    $q=$q1;
  dbConnect();
  $dbLastQ=$q; //echo($q.'<br /><br />'."\n\n");
  $start=microtime(true);
  $r=mysql_query($q,$GLOBALS['spojeni']);
  $end=microtime(true);
  if(!$r) throw new DbException();
  $GLOBALS['dbNumQ']++;
  $GLOBALS['dbExecTime']+=$end-$start;  
  return $r; 
}

/** Dotaz s nahrazováním jmen proměnných, pokud je nastaveno pole, tak jen z 
 *  pole ve forme $0 $1 atd resp $index */
function dbQueryS($q,$pole=null)
{
  if(isset($pole) && is_array($pole))
  {
    $delta = strpos($q, '$0')===false ? -1 : 0; // povolení číslování $1, $2, $3...
    return dbQuery(
      preg_replace_callback('~\$([0-9]+)~', function($m)use($pole,$delta){
        return dbQv($pole[ $m[1] + $delta ]);
      },$q)
    );
  }
  else
  {
    return dbQuery(
      preg_replace_callback('~\$([a-zA-Z]+)~', function($m){
        // TODO smazat pokud se neprojeví
        throw new Exception('nahrazování globálními proměnnými je deprecated');
        return '"'.addslashes($GLOBALS[$m[1]]).'"';
      },$q)
    );
  }
}

/**
 * Quotes array to be used in IN(1,2,3..N) queries
 */
function dbQa($array)
{
  $out = '';
  foreach($array as $v)
    $out .= dbQv($v).',';
  return substr($out, 0, -1);
}

/**
 * Quotes input values for DB. Nulls are passed as real NULLs, other values as
 * strings. Quotes $val as value
 */
function dbQv($val)
{
  if(is_array($val))
    return implode(',', array_map(function($val){ return dbQv($val); }, $val));
  elseif($val===null)
    return 'NULL';
  else
    return '"'.( get_magic_quotes_gpc() ? $val : addslashes($val) ).'"';
}

/**
 * Quotes $val as identifier
 */ 
function dbQi($val)
{
  return '`'.( get_magic_quotes_gpc() ? $val : addslashes($val) ).'`';
}

/**
 * For selecting single-line one column values
 */
function dbOneCol($q, $p = null)
{
  $a = dbOneLine($q, $p);
  return $a ? current($a) : null;
} 

/** Intended for selecting single lines from whatever. If no line found, returns
 *  false, otherwise returns asociative array with one line. If multiple lines
 *  found, causes crash. */
function dbOneLine($q, $p = null)
{
  $r = dbQueryS($q, $p);
  if(mysql_num_rows($r)>1) die('multiple lines matched!');
  elseif(mysql_num_rows($r)<1) return FALSE;
  else return mysql_fetch_assoc($r);
}

/** Single line selector with substitution
 *  @see dbQueryS */
function dbOneLineS($q,$array=null)
{
  $r=dbQueryS($q,$array);
  if(mysql_num_rows($r)>1) die('multiple lines matched!');
  elseif(mysql_num_rows($r)<1) return FALSE;
  else return mysql_fetch_assoc($r);
}

/* return last query */
function dbLastQ()
{
  global $dbLastQ;
  return $dbLastQ;
}

/**
 * Executes update on table $table using associtive array $vals as column=>value
 * pairs and $where as column=>value AND column=>value ... where clause
 */  
function dbUpdate($table,$vals,$where)
{
  global $dbspojeni, $dbLastQ;
  dbConnect();
  $q='UPDATE '.dbQi($table)." SET \n";
  foreach($vals as $key=>$val)
    $q.=( dbQi($key).'='.dbQv($val).",\n" );
  $q=substr($q,0,-2)."\n"; //odstranění čárky na konci
  $q.='WHERE '.dbQi(current(array_keys($where))).'='.dbQv(current($where)).';';
  // query execution
  $dbLastQ=$q;
  $start=microtime(true);
  $r=mysql_query($q,$GLOBALS['spojeni']);
  $end=microtime(true);
  if(!$r) throw new DbException();
}

function dbNumQ() { return $GLOBALS['dbNumQ']; }

function dbExecTime() { return $GLOBALS['dbExecTime']; }

class DbException extends Exception
{

  function __construct()
  {
    $this->message = mysql_error();
  }

}