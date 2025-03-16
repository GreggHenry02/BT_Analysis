<?php

namespace BT_Analysis;

require_once "DbCredentials.php";
require_once "Analyze.php";
require_once "WeaponData.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$o_mysqli = new \mysqli(
  \DbCredentials::HOSTNAME,
  \DbCredentials::USERNAME,
  \DbCredentials::PASSWORD,
  \DbCredentials::DATABASE
);

/* If we have to retrieve large amount of data we use MYSQLI_USE_RESULT */
$o_result = $o_mysqli->query("
  select * from
    mtf_parse
  where
    TechBase like '%Inner Sphere%' and
    BV <> 0 and
    Mass >= 20 and
    Mass <= 100
");

WeaponData::readFile();
//var_dump(WeaponData::$a_weapon);
$o_mech = new Analyze();

printf("%-30s %-20s -   %4s   %4s   %4s    %2s    %4s\n",
  'Chassis', 'Model', 'Def', 'Off', 'Totl', 'TC', 'Rato'
);

while($a_record = $o_result->fetch_assoc())
{
  $o_mech->setMech($a_record);
  $a_calc = $o_mech->getCalc();

  if(empty($a_calc))
    continue;

  $s_insert = '"'.implode('","',[
      $a_record['k_mtf'],
      $a_calc['i_defence'],
      $a_calc['i_offence'],
      $a_calc['i_total'],
      $a_calc['i_ratio'],
    ]).'"';
  $s_insert = "insert into calcA (k_mtf,i_defence,i_offence,i_total,i_ratio)
      values (".$s_insert.")";

  $o_mysqli->query($s_insert);
}


?>