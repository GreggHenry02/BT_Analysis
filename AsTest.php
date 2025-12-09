<?php

namespace BT_Analysis;

require_once "DbCredentials.php";
require_once "AlphaStrikeRoleCalc.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$o_mysqli = new \mysqli(
  \DbCredentials::HOSTNAME,
  \DbCredentials::USERNAME,
  \DbCredentials::PASSWORD,
  \DbCredentials::DATABASE
);

$o_mysqli->query("
  delete from AlphaStrikeCalc
");

/* If we have to retrieve large amount of data we use MYSQLI_USE_RESULT */
$o_result = $o_mysqli->query("
  select
    AlphaStrike.Id,
    AlphaStrike.Name,
    AlphaStrike.Model,
    PV,
    Type,
    Size,
    Move,
    AlphaStrike.Jump,
    TMM,
    Skill,
    Short,
    Medium,
    `Long`,
    Extreme,
    Overheat,
    AlphaStrike.Armor,
    AlphaStrike.Structure,
    Threshold,
    Specials,
    mtf_parse.k_mtf
  from
    AlphaStrike left join
    mtf_parse on AlphaStrike.Id = mtf_parse.Id
  where
    Type like 'BM'
");

$o_mech = new \BT_Analysis\AlphaStrikeCalcRoleCalc();

while($a_record = $o_result->fetch_assoc())
{
  $o_mech->submit($a_record,$o_mysqli,0);
}


?>