<?php

require_once('sdilene-hlavicky.php');

$davniUcastnici[] = null; // zbytek uživatelů z databáze po prvních dvou tisících
  $o = dbQuery('
    SELECT u.email1_uzivatele
    FROM uzivatele_hodnoty u
    LEFT JOIN r_uzivatele_zidle uz ON uz.id_uzivatele = u.id_uzivatele AND uz.id_zidle MOD 100 = -1
    WHERE u.nechce_maily IS NULL
    GROUP BY u.id_uzivatele
    ORDER BY MIN(COALESCE(uz.id_zidle, 0))
    LIMIT 2000,5000'
  );
while($r = mysqli_fetch_assoc($o)) {
  if (strpos($r["email1_uzivatele"],'@')) {
    $davniUcastnici[] = $r;
  }
}

array_shift($davniUcastnici); //aby nulová pozice nebyla prázdná
$report = Report::zPole($davniUcastnici);
$report->tFormat(get('format'));
