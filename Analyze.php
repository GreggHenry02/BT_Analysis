<?php

namespace BT_Analysis;

require_once "LocationSid.php";
require_once "RoleCalc.php";
require_once "Tables.php";

use BT_Analysis\LocationSid;
use BT_Analysis\Tables;

class Analyze
{
  private array $a_mech = [];

  /**
   * Performs the 'A' variant calculation to determine a mech's value.
   *
   * @return array - The calculated factors.
   */
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

  /**
   * Checks a mech's equipment and criticals to see if it has arm mounted AES or can flip arms.
   *
   * @return void
   */
  public function checkArms()
  {
    $this->a_mech['has_AES_left'] = false;
    $this->a_mech['has_AES_right'] = false;
    $this->a_mech['can_flip_arms'] = false;
    $i_lower_arm = 2;

    if(str_contains($this->a_mech['s_equipment'],'ISAES:'))
    {
      $s_context = 'Left Arm';
      $a_line = explode("\n", $this->a_mech['s_critical']);
      foreach($a_line as $s_line)
      {
        if($s_line == '' && $s_context == 'Right Arm')
          break; // We don't need to check all the critical locations.
        if($s_line == '')
          $s_context = '';
        else if(str_contains($s_line,'Right Arm'))
          $s_context = 'Right Arm';
        else if(str_contains($s_line,'ISAES'))
        {
          if($s_context == 'Left Arm')
            $this->a_mech['has_AES_left'] = true;
          elseif($s_context == 'Right Arm')
            $this->a_mech['has_AES_right'] = true;
        }
        if(str_contains($s_line,'Lower Arm Actuator'))
          $i_lower_arm --;
      }
    }
    if($i_lower_arm == 0)
      $this->a_mech['can_flip_arms'] = true;
  }

  /**
   * Checks if the mech has a melee weapon.
   *
   * @return void
   */
  public function checkMelee()
  {
    $a_melee_list = WeaponData::getMeleeWeaponList();
    $a_melee_select = $a_melee_list['Kick'];
    // A mech can only perform one melee attack in a turn, in most cases.
    // Finding the first melee weapon should be sufficient. This array describes the one weapon.
    foreach($a_melee_list as $a_melee)
    {
      if(str_contains($this->a_mech['s_critical'],$a_melee['s_name']) ||
        str_contains($this->a_mech['s_critical'],$a_melee['s_display_name'])
      )
      {
        $a_melee_select = $a_melee;
        break;
      }
    }

    $this->a_mech['a_melee_select'] = $a_melee_select;
  }

  /**
   * Sets the mech's general and defensive information.
   *
   * @param array $a_row - The list of the mech's data from the database's row.
   * @return void
   */
  public function defense(array $a_row)
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

    $this->a_mech['k_mtf'] = $a_row['k_mtf'];
    $this->a_mech['s_chassis'] = $a_row['Chassis'];
    $this->a_mech['s_model'] = $a_row['Model'];
    $this->a_mech['i_bv'] = intval($a_row['BV']);
    $this->a_mech['i_mass'] = intval($a_row['Mass']);
    $this->a_mech['s_critical'] = $a_row['Critical'];
    $this->a_mech['i_armour'] = intval($i_armour);
    $this->a_mech['s_armour'] = $s_armour;
    $this->a_mech['i_engine'] = intval($i_engine);
    $this->a_mech['s_engine'] = Tables::getEngineType($s_engine);
    $this->a_mech['s_equipment'] = $a_row['Equipment'] ?? '';
    $this->a_mech['i_heatsink'] = Tables::getHeatSink($a_row['Heatsink']);
    $this->a_mech['i_tech'] = Tables::getTechBase($a_row['TechBase']);
    $this->a_mech['s_struct'] = $a_row['Structure'] ?? 'Standard';
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
    if(str_contains(strtolower($this->a_mech['s_equipment']),'targeting computer'))
      $this->a_mech['has_tarcomp'] = true;
    else
      $this->a_mech['has_tarcomp'] = false;
    if(str_contains(strtolower($this->a_mech['s_equipment']), 'artemisIV'))
      $this->a_mech['has_artemisIV'] = true;
    else
      $this->a_mech['has_artemisIV'] = false;
    if(str_contains(strtolower($this->a_mech['s_equipment']), 'artemisV'))
      $this->a_mech['has_artemisV'] = true;
    else
      $this->a_mech['has_artemisV'] = false;
    if(str_contains(strtolower($this->a_mech['s_equipment']), 'partialwing'))
    {
      $this->a_mech['has_partial_wing'] = true;
      if($this->a_mech['i_mass'] >= 60 && $this->a_mech['i_jump'] > 0)
        $this->a_mech['i_jump'] += 1;
      else
        $this->a_mech['i_jump'] += 2;
      $this->a_mech['i_heatsink'] += 3;
    }
    else
      $this->a_mech['has_partial_wing'] = false;
  }

  /**
   * Gets a mech's calculated factors.
   *
   * @return array - The mech's calculated factors.
   */
  public function getCalc(): array
  {
    return $this->a_mech['a_calc'];
  }

  /**
   * Parses a mech's weapon list.
   *
   * @param string $s_weapon - A text listing of the mech's weapons.
   * @return array - A list of a mech's weapons.
   */
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
      if(str_contains($s_weapon,'OS)') ||
        str_contains($s_weapon,'Rocket Launcher') ||
        str_contains($s_weapon,'RocketLauncher') ||
        str_contains($s_weapon,'(R)')
      )
        continue;

      preg_match('/^[0-9]*/', $s_weapon, $a_match);
      $i_multi = intval($a_match[0])?intval($a_match[0]):1;

      $s_weapon = preg_replace('/^[0-9]* /','',$s_weapon);
      $a_split = explode(',',$s_weapon);
      $s_location = trim($a_split[1]??'UNSET');
      $x_result = WeaponData::getWeapon($s_weapon);
      if(!$x_result)
      {
        echo $s_weapon.PHP_EOL;
        exit(); // On error, exit;
      }
      else
      {
        $x_result['s_location'] = $s_location;
        for($i_count=1;$i_count<=$i_multi;$i_count++)
          $a_data[] = $x_result;
      }
    }
    return $a_data;
  }

  /**
   * Gather offensive information.
   *
   * @param array $a_row
   * @return void
   */
  public function offense(array $a_row)
  {
    $this->a_mech['a_weapon_list'] = $this->getWeaponData($a_row['Weapon']);
    $this->checkArms();
    $this->checkMelee();
  }

  /**
   * Sets the mech value in the database and prints to console.
   *
   * @param array $a_row - The mech's information from the database.
   * @return void
   */
  public function setMech(array $a_row)
  {
    $this->defense($a_row);
    $this->offense($a_row);
  }

  /**
   * Output processed mech information. Some combination of printing to the console and saving to the DB.
   *
   * @param array $a_row - Basic information for the mech from the database.
   * @param object $o_mysqli - The initialized database object.
   * @param int $i_test_level - The test level. If `0`, perform the action. If `1` or greater, log to console.
   * @return void
   */
  public function submit(array $a_row, object $o_mysqli, int $i_test_level=0): void
  {
    $this->setMech($a_row);
    $o_role_calc = new \BT_Analysis\RoleCalc();
    $o_role_calc->roleAll($this->a_mech);
    $a_calc_collection = $o_role_calc->submit($this->a_mech);

    if(!empty($i_test_level))
    {
      if(!$this->a_mech['is_unsporting'] && !$this->a_mech['is_invalid'])
      {
        printf("%-30s %-20s -   %4d   %4d   %4d    %2s    %4d\n",
          $this->a_mech['s_chassis'],
          $this->a_mech['s_model'],
          $a_calc_collection['Brawler']['i_defence'],
          $a_calc_collection['Brawler']['i_offence'],
          $a_calc_collection['Brawler']['i_total'],
          !empty($this->a_mech['has_tarcomp'])?'TC':'',
          $a_calc_collection['Brawler']['i_ratio']
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
      if($i_test_level >= 2)
      {
        foreach($a_calc_collection as $s_calc => $a_calc)
        {
          printf("%-30s %-20s -   %4d   %4d   %4d    %2s    %4d\n",
            '',
            $s_calc,
            $a_calc['i_defence'],
            $a_calc['i_offence'],
            $a_calc['i_total'],
            !empty($this->a_mech['has_tarcomp'])?'TC':'',
            $a_calc['i_ratio']
          );
        }
//        var_dump($a_calc_collection);
      }
    }
    else
    {
      foreach($a_calc_collection as $s_role => $a_calc)
      {
        if(empty($a_calc))
          continue;

        $s_insert = '"'.implode('","',[
            $a_row['k_mtf'],
            $a_row['Id'],
            $a_calc['i_defence'],
            $a_calc['i_offence'],
            $a_calc['i_ratio'],
            $a_calc['i_total'],
            $s_role
          ]).'"';
        $s_insert = "insert into calc_tag (k_mtf,Id,i_defence,i_offence,i_ratio,i_total,s_role)
          values (".$s_insert.")";

        $o_mysqli->query($s_insert);
      }
    }
  }

  /**
   * Update additional columns in mtf_parse.
   *
   * @param array $a_row - Basic information for the mech from the database.
   * @param object $o_mysqli - The initialized database object.
   * @param int $i_test_level - The test level. If `0`, perform the action. If `1` or greater, log to console.
   * @return void
   */
  public function updateMtfParse(array $a_row, object $o_mysqli, int $i_test_level=0): void
  {
    $this->setMech($a_row);

    $a_equipment_list = [];
    foreach(WeaponData::getElectronics() as $s_name => $a_equipment)
    {
      if(str_contains($this->a_mech['s_critical'], $s_name) ||
        str_contains($this->a_mech['s_critical'], $a_equipment['s_display_name'])
      )
        $a_equipment_list[$a_equipment['s_display_name']] = true;
    }

    $s_insert = sprintf("
      update 
        mtf_parse
      set 
        ArmorType = '%s',
        ArmorTotal = %d,
        StructureTotal = %d,
        Special = '%s'
      where
        k_mtf = %d
    ",trim($this->a_mech['s_armour']),
      $this->a_mech['i_armour'],
      $this->a_mech['i_struct'],
      implode(",\n",array_keys($a_equipment_list)),
      $this->a_mech['k_mtf']
    );

    if($i_test_level > 0)
      echo $s_insert.PHP_EOL;
    else
    {
      $o_mysqli->query($s_insert);
    }
  }
}


?>