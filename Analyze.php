<?php

namespace BT_Analysis;

require_once "LocationSid.php";
require_once "Tables.php";

use BT_Analysis\LocationSid;
use BT_Analysis\Tables;

class Analyze
{
  private static array $a_mech = [];


  public static function defense(array $a_row): array
  {
    $a_split = explode("\n",$a_row['Armor']);
    $i_armour = 0;
    $s_armour = '';
    foreach($a_split as $s_line)
    {
      if(!$s_armour)
        $s_armour = str_replace("\n",'',$s_line);
      else
      {
        preg_match('/[0-9]{1,3}/',$s_line,$a_match);
        $i_armour += array_sum($a_match);
      }
    }

    $a_engine = explode(' ',$a_row['Engine']);
    $i_engine = $a_engine[0];
    unset($a_engine[0]);
    $s_engine = implode(' ',$a_engine);

    $a_struct = \BT_Analysis\LocationSid::INTERNAL_STRUCTURE[$a_row['Mass']];
    $i_struct = array_sum($a_struct)*2 - $a_struct[0];
    self::$a_mech['i_mass'] = $a_row['Mass'];
    self::$a_mech['i_armour'] = $i_armour;
    self::$a_mech['s_armour'] = $s_armour;
    self::$a_mech['i_engine'] = $i_engine;
    self::$a_mech['s_engine'] = Tables::getEngineType($s_engine);
    self::$a_mech['i_tech'] = Tables::getTechBase($a_row['TechBase']);
    self::$a_mech['i_struct'] = $i_struct;
    self::$a_mech['i_walk'] = $a_row['Walk'];
    self::$a_mech['i_walk_tmm'] = \BT_Analysis\Tables::getTMM(self::$a_mech['i_walk']);
    self::$a_mech['i_run'] = intval(ceil($a_row['Walk']*1.5));
    self::$a_mech['i_run_tmm'] = \BT_Analysis\Tables::getTMM(self::$a_mech['i_run']);
    self::$a_mech['i_jump'] = $a_row['Jump'];
    self::$a_mech['i_jump_tmm'] = \BT_Analysis\Tables::getTMM(self::$a_mech['i_jump'],true);
    var_dump(self::$a_mech);

    return [];
  }

  public static function mech(array $a_row): array
  {
    if($a_row['BV'])
    {
      self::offense($a_row);
//      self::defense($a_row);
    }

    return [];
  }

  public static function offense(array $a_row): array
  {
    $a_weapon = Tables::getWeapons($a_row['Weapon']);
    $a_weapon = Tables::getWeaponData($a_weapon);
    var_dump($a_weapon);
    return [];
  }
}


?>