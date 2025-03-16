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
    $i_defence = $this->a_mech['i_armour'] * 1.5 + $this->a_mech['i_struct'];
    $i_tmm = max($this->a_mech['i_run_tmm'],$this->a_mech['i_jump_tmm']);
    $i_tmm += !empty($this->a_mech['has_stealth'])?1:0;
    $i_defence = intval(ceil($i_defence * (1+($i_tmm * .25))));

    // Light and XL engines reduce mech longevity by 10% and 25% respectively.
    // A mech with a standard engine is very diminished by losing a torso, so don't apply the full penalty.
    if(str_contains($this->a_mech['s_engine'],'xl'))
      $i_defence = intval(ceil($i_defence * .85));
    else if(str_contains($this->a_mech['s_engine'],'light'))
      $i_defence = intval(ceil($i_defence * .95));

    $i_offence = 0;
    $i_heat_max = 0;
    if(!empty($this->a_mech['has_stealth']))
      $i_heat_max += 10;

    $has_tarcomp = !empty($this->a_mech['has_tarcomp']);
    foreach($this->a_mech['a_weapon_list'] as $a_weapon)
    {
      $s_name = $a_weapon['s_display_name'] ?? $a_weapon['s_name'];
      $i_range = $a_weapon['i_long'] - (intval($a_weapon['i_min_range']) * 0.75);
      if($a_weapon['s_subtype'] == 'VSP')
        $i_range = intval(ceil($i_range / 2));
      $i_range += $this->a_mech['i_run'];

      $i_damage = $a_weapon['i_damage'];
      $i_heat = $a_weapon['i_heat'];

      if($a_weapon['sid_type'] == 'Missile')
      {
        if($a_weapon['s_subtype'] == 'Streak')
        {
          $i_cluster = $a_weapon['i_damage'];
          $i_heat = intval(ceil($i_heat/2));
        }
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
        $i_heat = intval($i_heat*2);
      }
      else if($a_weapon['s_subtype'] == 'Rotary')
      {
        $i_damage = intval($i_damage * 3);
        $i_heat = intval($i_heat*5);
      }

      $i_accuracy = $a_weapon['i_accuracy'];
      if($has_tarcomp)
        $i_accuracy -= WeaponData::usesTargetingComputer($a_weapon['sid_type'], $a_weapon['s_subtype'])?1:0;
      $f_accuracy = match($i_accuracy)
      {
        -4 => 1.7,
        -3 => 1.6,
        -2 => 1.45,
        -1 => 1.25,
        0 => 1,
        1 => 0.8,
        default => 1,
      };

      //printf("%30s     %5d     %5d     %5d\n",$s_name,$a_weapon['i_long'],$i_damage,$a_weapon['i_accuracy']);

      $i_offence += $i_range * intval(ceil($i_damage * $f_accuracy));
      $i_heat_max += $i_heat;
    }

    if($this->a_mech['i_heatsink'] < $i_heat_max)
    {
      $i_offence = intval(ceil($i_offence * $this->a_mech['i_heatsink'] / $i_heat_max));
    }

    $this->a_mech['a_calc'] = [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => intval((($i_defence + $i_offence) / $this->a_mech['i_bv'])*1000),
      'i_total' => $i_defence + $i_offence,
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
    $this->a_mech['i_heatsink'] = Tables::getHeatSink($a_row['Heatsink']);
    $this->a_mech['i_tech'] = Tables::getTechBase($a_row['TechBase']);
    $this->a_mech['i_struct'] = $i_struct;
    $this->a_mech['i_walk'] = intval($a_row['Walk']);
    $this->a_mech['i_walk_tmm'] = \BT_Analysis\Tables::getTMM($this->a_mech['i_walk']);
    $this->a_mech['i_run'] = intval(ceil($this->a_mech['i_walk']*1.5));
    $this->a_mech['i_run_tmm'] = \BT_Analysis\Tables::getTMM($this->a_mech['i_run']);
    $this->a_mech['i_jump'] = intval($a_row['Jump']);
    $this->a_mech['i_jump_tmm'] = \BT_Analysis\Tables::getTMM($this->a_mech['i_jump'],true);
    $this->a_mech['is_invalid'] = false;
    $this->a_mech['is_unsporting'] = false;

    if(str_contains(strtolower($a_row['Armor']),'stealth'))
      $this->a_mech['has_stealth'] = true;
    else
      $this->a_mech['has_stealth'] = false;
    if(str_contains(strtolower($a_row['Equipment']),'targeting computer'))
      $this->a_mech['has_tarcomp'] = true;
    else
      $this->a_mech['has_tarcomp'] = false;

    return [];
  }

  public function getCalc(): array
  {
    return $this->a_mech['a_calc'];
  }

  public function getWeaponData(string $s_weapon): array
  {
    // Parse error:
    if((str_contains($s_weapon,'Mass') && str_contains($s_weapon,'Engine')) ||
      str_contains($s_weapon,'HD:3:'))
    {
      $this->a_mech['is_invalid'] = true;
      return [];
    }

    $a_weapon = \BT_Analysis\Tables::getWeapons($s_weapon);
    $a_data = [];
    foreach($a_weapon as $s_weapon)
    {
      if(str_contains($s_weapon,'Mech Taser') || str_contains($s_weapon,'TSEMP'))
      {
        $this->a_mech['is_unsporting'] = true;
        break;
      }

      if(str_contains($s_weapon,'OS)') ||
        str_contains($s_weapon,'Rocket Launcher') ||
        str_contains($s_weapon,'RocketLauncher') ||
        str_contains($s_weapon,'(R)')
      )
        continue;

      preg_match('/^[0-9]*/', $s_weapon, $a_match);
      $i_multi = intval($a_match[0])?intval($a_match[0]):1;

      $s_weapon = preg_replace('/^[0-9]* /','',$s_weapon);
      $x_result = WeaponData::getWeapon($s_weapon);
      if(!$x_result)
      {
        echo $s_weapon.PHP_EOL;
        exit(); // On error, exit;
      }
      else
      {
        for($i_count=1;$i_count<=$i_multi;$i_count++)
          $a_data[] = $x_result;
      }
    }
    return $a_data;
  }

  public function offense(array $a_row)
  {
    $this->a_mech['a_weapon_list'] = $this->getWeaponData($a_row['Weapon']);
  }

  public function setMech(array $a_row)
  {
    $this->defense($a_row);
    $this->offense($a_row);
    $this->calcA();
    if(!$this->a_mech['is_unsporting'] && !$this->a_mech['is_invalid'])
    {
      printf("%-30s %-20s -   %4d   %4d   %4d    %2s    %4d\n",
        $this->a_mech['s_chassis'],
        $this->a_mech['s_model'],
        $this->a_mech['a_calc']['i_defence'],
        $this->a_mech['a_calc']['i_offence'],
        $this->a_mech['a_calc']['i_total'],
        !empty($this->a_mech['has_tarcomp'])?'TC':'',
        $this->a_mech['a_calc']['i_ratio']
      );
    }
    else
    {
      printf("%-30s %-20s -   %s\n",
        $this->a_mech['s_chassis'],
        $this->a_mech['s_model'],
        ($this->a_mech['is_unsporting']?'Unsporting Equipment':'').($this->a_mech['is_invalid']?' Invalid':'')
      );
    }
  }
}


?>