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

  public static function getHeatSink(string $s_heatsink): int
  {
    $a_heatsink = explode(' ',$s_heatsink);
    $i_heatsink = $a_heatsink[0];
    if(str_contains(strtolower($s_heatsink),'double'))
      $i_heatsink *= 2;
    return $i_heatsink;
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

  public static function getWeaponData(string $s_weapon): array
  {
    $a_weapon = self::getWeapons($s_weapon);
    $a_data = [];
    foreach($a_weapon as $s_weapon)
    {
      $s_weapon = preg_replace('/^[0-9]* /','',$s_weapon);
      $s_weapon = trim(str_replace('(R)','',$s_weapon));
      $x_result = WeaponData::getWeapon($s_weapon);
      if(!$x_result)
      {
        echo $s_weapon.PHP_EOL;
        exit(); // On error, exit;
      }
      else
        $a_data[] = $x_result;
    }
    return $a_data;
  }
}

?>