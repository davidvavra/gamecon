<?php

require_once('sdilene-hlavicky.php');

$nedavniUcastnici[] = null; // prvních dva tisíce uživatelů
  $o = dbQuery('
    SELECT u.email1_uzivatele
    FROM uzivatele_hodnoty u
    LEFT JOIN r_uzivatele_zidle uz ON uz.id_uzivatele = u.id_uzivatele AND uz.id_zidle MOD 100 = -1
    WHERE u.nechce_maily IS NULL
    GROUP BY u.id_uzivatele
    ORDER BY MIN(COALESCE(uz.id_zidle, 0))
    LIMIT 0,2000'
  );
while($r = mysqli_fetch_assoc($o)) {
  if (strpos($r["email1_uzivatele"],'@')) {
    $nedavniUcastnici[] = $r;
  }
}

array_shift($nedavniUcastnici); //aby nulová pozice nebyla prázdná
$report = Report::zPole($nedavniUcastnici);
$report->tFormat(get('format'));
