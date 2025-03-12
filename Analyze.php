<?php

namespace BT_Analysis;

require_once "LocationSid.php";
require_once "Tables.php";

use BT_Analysis\LocationSid;
use BT_Analysis\Tables;

class Analyze
{
  private array $a_mech = [];

  public function calcA(): array
  {
    $i_defence = $this->a_mech['i_armour'] + $this->a_mech['i_struct'];
    $i_tmm = max($this->a_mech['i_run_tmm'],$this->a_mech['i_jump_tmm']);
    $i_defence = intval(ceil($i_defence * (1+($i_tmm * .25))));

    $i_offence = 0;
    foreach($this->a_mech['a_weapon_list'] as $a_weapon)
    {
      if(gettype($a_weapon) !== 'array')
      {
//        var_dump($this->a_mech['a_weapon_list']);
        exit();
      }

      $s_name = $a_weapon['s_display_name'] ?? $a_weapon['s_name'];
      $i_range = $a_weapon['i_long'] - (intval($a_weapon['i_min_range']) * 0.75);
      $i_damage = $a_weapon['i_damage'];

      if($a_weapon['sid_type'] == 'Missile')
      {
        if($a_weapon['s_subtype'] == 'Streak')
          $i_cluster = $a_weapon['i_damage'];
        else
          $i_cluster = WeaponData::getClusterHits($a_weapon['i_damage'],7);

        if($a_weapon['s_subtype'] == 'Streak' || $a_weapon['s_subtype'] == 'SRM')
          $i_damage = $i_cluster * 2;
        else
          $i_damage = $i_cluster;
      }
      else if($a_weapon['s_subtype'] == 'Ultra')
      {
        $i_damage = intval($i_damage * 1.4);
      }

      //printf("%30s     %5d     %5d     %5d\n",$s_name,$a_weapon['i_long'],$i_damage,$a_weapon['i_accuracy']);
      $i_offence += $i_range * $a_weapon['i_damage'] * intval(ceil(1+(-0.25 * $a_weapon['i_accuracy'])));
    }

    $this->a_mech['a_calc'] = [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_total' => $i_defence + $i_offence
    ];

    return [];
  }

  public function defense(array $a_row): array
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

    $this->a_mech['s_chassis'] = $a_row['Chassis'];
    $this->a_mech['s_model'] = $a_row['Model'];
    $this->a_mech['i_bv'] = intval($a_row['BV']);
    $this->a_mech['i_mass'] = intval($a_row['Mass']);
    $this->a_mech['i_armour'] = intval($i_armour);
    $this->a_mech['s_armour'] = $s_armour;
    $this->a_mech['i_engine'] = intval($i_engine);
    $this->a_mech['s_engine'] = Tables::getEngineType($s_engine);
    $this->a_mech['i_tech'] = Tables::getTechBase($a_row['TechBase']);
    $this->a_mech['i_struct'] = $i_struct;
    $this->a_mech['i_walk'] = intval($a_row['Walk']);
    $this->a_mech['i_walk_tmm'] = \BT_Analysis\Tables::getTMM($this->a_mech['i_walk']);
    $this->a_mech['i_run'] = intval(ceil($this->a_mech['i_walk']*1.5));
    $this->a_mech['i_run_tmm'] = \BT_Analysis\Tables::getTMM($this->a_mech['i_run']);
    $this->a_mech['i_jump'] = intval($a_row['Jump']);
    $this->a_mech['i_jump_tmm'] = \BT_Analysis\Tables::getTMM($this->a_mech['i_jump'],true);

    return [];
  }

  public function offense(array $a_row)
  {
    $this->a_mech['a_weapon_list'] = Tables::getWeaponData($a_row['Weapon']);
    if(str_contains(strtolower($a_row['Equipment']),'targeting computer'))
      $this->a_mech['has_tarcomp'] = true;
  }

  public function setMech(array $a_row)
  {
    $this->defense($a_row);
    $this->offense($a_row);
    $this->calcA();
//      var_dump($this->a_mech);
//    var_dump($this->a_mech['a_calc']);
    printf("%-30s %-10s -   %3d   %3d   %3d\n",
      $this->a_mech['s_chassis'],
      $this->a_mech['s_model'],
      $this->a_mech['a_calc']['i_defence'],
      $this->a_mech['a_calc']['i_offence'],
      $this->a_mech['a_calc']['i_total'],
    );
  }
}


?>