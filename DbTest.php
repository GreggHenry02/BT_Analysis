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
    Mass >= 20 and
    Mass <= 100
");

//     Model in ('BNC-9S','SRC-5C','CRD-9R','PXH-1','MS1-OF') and

WeaponData::readFile();
//var_dump(WeaponData::$a_weapon);
$o_mech = new Analyze();

printf("%-30s %-20s -   %4s   %4s   %4s    %2s    %4s\n",
  'Chassis', 'Model', 'Def', 'Off', 'Totl', 'TC', 'Rato'
);

while($a_record = $o_result->fetch_assoc())
{
//  $o_mech->submit($a_record,$o_mysqli,0);
//  $o_mech->updateMtfParse($a_record,$o_mysqli,0);
  $o_mech->specificRange($a_record,$o_mysqli,0,2);
}


?>