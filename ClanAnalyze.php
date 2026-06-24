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
    TechBase like '%Clan%' and 
    Mass >= 20 and
    Mass <= 100
");

//Chassis like '%Dasher%' and
//Model like 'P' and

WeaponData::readFile(TechBaseSid::INNER_SPHERE);
WeaponData::readFile(TechBaseSid::CLAN);
//var_dump(WeaponData::$a_weapon);
$o_mech = new Analyze();

printf("%-30s %-20s -   %4s   %4s   %4s    %2s    %4s\n",
  'Chassis', 'Model', 'Def', 'Off', 'Totl', 'TC', 'Rato'
);

while($a_record = $o_result->fetch_assoc())
{
  $o_mech->submit($a_record,$o_mysqli);
//  $o_mech->submit($a_record,$o_mysqli,1, 'Fast Hunter');
//  $o_mech->roleTest($a_record,'Fast Hunter');
//  $o_mech->updateMtfParse($a_record,$o_mysqli,0);
//  $o_mech->specificRange($a_record,$o_mysqli,0,2);
}


?>