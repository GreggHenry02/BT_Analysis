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
    Model like 'BLR-1G' 
");

//TR1

WeaponData::readFile();
//var_dump(WeaponData::$a_weapon);
while($a_record = $o_result->fetch_assoc())
{
  Analyze::Mech($a_record);
}

?>