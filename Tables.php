<?php

namespace BT_Analysis;

require_once "TechBaseSid.php";
require_once "WeaponData.php";

class Tables
{
  public static array $a_tmm = [
    18 => 5,
    10 => 4,
    7 => 3,
    5 => 2,
    3 => 1,
    0 => 0
  ];

  public static function getEngineType(string $s_engine): string
  {
    $s_engine = strtolower($s_engine);
    $a_engine = explode(' ',$s_engine);
    $s_append = '';
    if(str_contains($s_engine,'clan'))
      $s_append = '-clan';
    return $a_engine[0].$s_append;
  }

  public static function getTechBase(string $s_tech): int
  {
    if(str_contains(strtolower($s_tech),'sphere'))
      return TechBaseSid::InnerSphere;

    if(str_contains(strtolower($s_tech),'clan'))
      return TechBaseSid::Clan;

    return TechBaseSid::Mixed;
  }

  public static function getTMM(int $i_move, bool $is_jump=false)
  {
    $i_tmm = 0;
    foreach(self::$a_tmm as $i_speed => $i_mod)
    {
      if($i_move >= $i_speed)
      {
        $i_tmm = $i_mod;
        break;
      }
    }

    return $i_tmm + ($is_jump?1:0);
  }

  public static function getWeapons(string $s_weapon): array
  {
    $a_weapon = explode("\n",$s_weapon);
    $a_list = [];
    $i_count = 0;
    if(intval($a_weapon[0]) == $a_weapon[0])
    {
      $i_count = intval($a_weapon[0]);
      unset($a_weapon[0]);
    }
    foreach($a_weapon as $s_line)
    {
      $s_line2 = preg_replace('/,.*/','',$s_line);
      $a_list[] = $s_line2;
    }

    if(count($a_list) < $i_count)
    {
      for($i=count($a_list);$i<=$i_count;$i++)
        $a_list[] = 'UNKNOWN';
    }

    return $a_list;
  }

  public static function getWeaponData(array $a_weapon): array
  {
    $a_data = [];
    foreach($a_weapon as $s_weapon)
    {
//      $a_data[] = self::findWeaponData($s_weapon);
      $a_data[] = WeaponData::getWeapon($s_weapon);
    }
    return $a_data;
  }
}

?>