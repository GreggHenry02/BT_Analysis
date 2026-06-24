<?php

namespace BT_Analysis;

require_once "LocationSid.php";
require_once "Tables.php";

use BT_Analysis\LocationSid;
use BT_Analysis\Tables;

class RoleCalc
{
  /**
   * The fraction of a mech's run speed to give as a speed boost in calculations.
   */
  const RUN_MODIFIER = 0.75;

  private array $a_weapon;
  private array $a_brawler;
  private array $a_cavalry;
  private array $a_fast_hunter;
  private array $a_harasser;
  private array $a_hunter;
  private array $a_mech;
  private array $a_melee;
  private array $a_sniper;

  /**
   * Calculates defence values.
   *
   * @param array $a_mech
   * @param array $a_restrict
   * @return int
   */
  private function calculateDefence(array $a_mech, array $a_restrict = []): int
  {
    $i_tmm = 0;
    if(!empty($a_restrict['use_slow_move']))
    {
      $i_run_slow = intval(floor($this->a_mech['i_walk']*1.25));
      $i_tmm_run = \BT_Analysis\Tables::getTMM($i_run_slow);
      $i_jump_slow = intval(floor($this->a_mech['i_jump']*0.85));
      $i_tmm_jump = \BT_Analysis\Tables::getTMM($i_jump_slow);
      $i_tmm = max($i_tmm_run,$i_tmm_jump);
    }
    else if(empty($a_restrict['is_stationary']))
    {
      $i_tmm = max($a_mech['i_run_tmm'],$a_mech['i_jump_tmm']);
      // Half again your best TMM, limit +2;
      if(!empty($a_restrict['i_tmm_bonus']))
        $i_tmm += $a_restrict['i_tmm_bonus'];
      $i_tmm += !empty($a_mech['has_stealth'])?1:0;
    }

    $i_defence = ($a_mech['i_armour'] * 1.5) + $a_mech['i_struct'];
    $i_defence = intval(ceil($i_defence * (1+($i_tmm * .40))));

    // Light and XL engines reduce mech longevity by 10% and 25% respectively.
    // A mech with a standard engine is very diminished by losing a torso, so don't apply the full penalty.
    if(str_contains($a_mech['s_engine'],'xxl'))
      $i_defence = intval(ceil($i_defence * .80));
    else if(str_contains($a_mech['s_engine'],'xl'))
      $i_defence = intval(ceil($i_defence * .85));
    else if(str_contains($a_mech['s_engine'],'light'))
      $i_defence = intval(ceil($i_defence * .95));

    return $i_defence;
  }

  /**
   * Calculates offensive values.
   *
   * @param array $a_mech
   * @param $a_restrict
   * @return int
   */
  private function calculateOffence(array $a_mech, $a_restrict): int
  {
    $this->initWeapon($a_mech);
    $i_end = $a_restrict['i_end'] ?? 0;
    $i_start = $a_restrict['i_start'] ?? 0;
    $i_modifier = $a_restrict['i_modifier'] ?? 0;
    $i_speed_boost = $a_restrict['i_speed_boost'] ?? 0;

    $a_range_weapon = [];

    $i_weapon = 1;
    foreach($this->a_weapon as $a_weapon)
    {
      foreach($a_weapon['a_range'] as $i_range => $a_range)
      {
        if($i_range < $i_start)
          continue;
        if($i_end && $i_range > $i_end)
          break;

        $i_range_modifier = $a_range['i_modifier'] + $i_modifier;

        $r_damage = $a_range['i_damage'] * self::get2d6BellCurve($i_range_modifier);
        $r_damage_per_heat = $r_damage / max($a_range['i_heat'],0.1);
        $a_range_weapon[$i_range][$i_weapon] = [
          'i_heat' => $a_range['i_heat'],
          'r_damage' => $r_damage,
          'r_damage_per_heat' => $r_damage_per_heat
        ];
      }
      $i_weapon ++;
    }

    $i_heat_max = $a_mech['i_heatsink'];
    $i_heat_reset = 0;
    $r_offence = 0.0;
    if($a_mech['has_stealth'])
      $i_heat_reset = 10;
    foreach($a_range_weapon as $i_range => $a_range)
    {
      usort($a_range, function($a, $b)
      {
        if($a['r_damage_per_heat'] < $b['r_damage_per_heat'])
          return 1;
        else if($a['r_damage_per_heat'] > $b['r_damage_per_heat'])
          return -1;
        else
          return 0;
      });

      $i_heat_total = $i_heat_reset;
      $r_damage = 0.0;
      foreach($a_range as $i_weapon => $a_weapon_at_range)
      {
        if($i_heat_total <= $i_heat_max)
        {
          $i_heat_total += $a_weapon_at_range['i_heat'];
          $r_damage += $a_weapon_at_range['r_damage'];
        }
      }

      $r_offence += $r_damage;
      if($i_speed_boost)
      {
        $r_offence += $r_damage*$i_speed_boost;
        $i_speed_boost = 0;
      }
    }

    return intval(ceil($r_offence));
  }

  /**
   * Returns the probability of a 2d6 roll succeeding for the given target number.
   *
   * @param int $i_target - The target number for a 2d6 roll.
   * @return float - The probability of success.
   */
  public function get2d6BellCurve(int $i_target): float
  {
    switch($i_target)
    {
      case 12: return 1.0/36.0;
      case 11: return 3.0/36.0;
      case 10: return 6.0/36.0;
      case 9: return 10.0/36.0;
      case 8: return 15.0/36.0;
      case 7: return 21.0/36.0;
      case 6: return 26.0/36.0;
      case 5: return 30.0/36.0;
      case 4: return 33.0/36.0;
      case 3: return 35.0/36.0;
      case 2: return 36.0/36.0;
    }
    if($i_target > 12)
      return 0.0;
    return 1.0;
  }
  /**
   * Gets weapon parameters per weapon and mech.
   *
   * @param array $a_mech
   * @param array $a_weapon
   * @return array
   */
  private function getWeaponProperties(array $a_mech, array $a_weapon): array
  {
    $i_damage = intval($a_weapon['i_damage']);
    $i_heat = $a_weapon['i_heat'];
    $i_modifier = 0;

    if($a_weapon['sid_type'] == 'Missile')
    {
      $i_cluster_bonus = 0;
      if($a_mech['has_artemisIV'] || $a_mech['has_artemisV'])
      {
        $use_artemis = WeaponData::usesArtemis($a_weapon['sid_type'],$a_weapon['s_subtype']);
        if($a_mech['has_artemisIV'] && $use_artemis)
          $i_cluster_bonus = 2;
        if($a_mech['has_artemisIV'] && $use_artemis)
        {
          $i_modifier -= 1;
          $i_cluster_bonus = 3;
        }
      }

      if($a_weapon['s_subtype'] == 'Streak')
      {
        $i_cluster = $a_weapon['i_damage'];
        $i_heat = intval(ceil($i_heat/2));
      }
      else
        $i_cluster = WeaponData::getClusterHits($a_weapon['i_damage'],7+$i_cluster_bonus);

      if($a_weapon['s_subtype'] == 'Streak' || $a_weapon['s_subtype'] == 'SRM' || $a_weapon['s_subtype'] == 'MML')
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
    else if($a_weapon['sid_type'] == 'Melee' && $a_weapon['s_subtype'] == 'Variable' && $a_weapon['i_damage'] != 0)
    {
      $i_damage = intval($a_mech['i_mass'] / $a_weapon['i_damage']);
      $a_weapon['i_long'] = 1;
      $i_heat = 0;
    }

    $is_VSP = ($a_weapon['s_subtype'] == 'VSP')?true:false;

    if($a_mech['has_tarcomp'])
      $i_modifier = WeaponData::usesTargetingComputer($a_weapon['sid_type'],$a_weapon['s_subtype'])?-1:0;
    else if($a_mech['has_AES_left'] && !empty($a_weapon['s_location']) && $a_weapon['s_location'] == 'Left Arm')
      $i_modifier = -1;
    else if($a_mech['has_AES_right'] && !empty($a_weapon['s_location']) && $a_weapon['s_location'] == 'Right Arm')
      $i_modifier = -1;

    $i_modifier += $a_weapon['i_accuracy'];

    $a_range = [];

    $i_encroachment = $a_weapon['i_min_range'];
    $s_range_bracket = 'Short';
    for($i_range=1;$i_range<=$a_weapon['i_long'];$i_range++)
    {
      if($s_range_bracket == 'Short' && $i_range > $a_weapon['i_short'] && $a_weapon['i_medium'])
      {
        $s_range_bracket = 'Medium';
        if(!empty($a_weapon['a_damage'][1]))
          $i_damage = $a_weapon['a_damage'][1];
      }
      else if($s_range_bracket == 'Short' && $i_range > $a_weapon['i_short'] && $a_weapon['i_long'])
      {
        $s_range_bracket = 'Long';
        if(!empty($a_weapon['a_damage'][2]))
          $i_damage = $a_weapon['a_damage'][2];
      }
      if($s_range_bracket == 'Medium' && $i_range > $a_weapon['i_medium'] && $a_weapon['i_long'])
      {
        $s_range_bracket = 'Long';
        if(!empty($a_weapon['a_damage'][2]))
          $i_damage = $a_weapon['a_damage'][2];
      }

//      $i_range_bracket = match($s_range_bracket)
//      {
//        'Long' => 4 + ($is_VSP?2:0),
//        'Medium' => 2 + ($is_VSP?1:0),
//        'Short' => 0,
//        default => 0
//      };

      switch($s_range_bracket)
      {
        case 'Long': $i_range_bracket = 4 + ($is_VSP?2:0); break;
        case 'Medium': $i_range_bracket = 2 + ($is_VSP?1:0); break;
        case 'Short': $i_range_bracket = 0; break;
        default: $i_range_bracket = 0; break;
      }

      $a_range[$i_range] = [
        'i_damage' => $i_damage,
        'i_heat' => $i_heat,
        'i_modifier' => $i_modifier + $i_range_bracket + $i_encroachment,
      ];

      if($i_encroachment > 0)
        $i_encroachment --;
    }

    return $a_range;
  }

  /**
   * Gets the point value to battle value ratio.
   *
   * @param array $a_mech - The array of Mech information.
   * @param int $i_defence - The total defence value.
   * @param int $i_offence - The total offence value.
   * @return int - The ratio of points to BV, times 1000. Will equal the point total if no BV is set.
   */
  public function getRatio(array $a_mech, int $i_defence, int $i_offence): int
  {
    if(!empty($a_mech['i_bv']) && $a_mech['i_bv'] > 1)
      return intval((($i_defence + $i_offence) / $a_mech['i_bv'])*1000);
    return ($i_defence + $i_offence);
  }

  /**
   * Sets all weapons parameters for this particular mech.
   *
   * @param $a_mech
   * @return void
   */
  public function initWeapon($a_mech)
  {
    if(!empty($this->a_weapon) && count($this->a_weapon) != 0)
      return;
    $a_weapon_list = $a_mech['a_weapon_list'];
    $a_melee_select = $a_mech['a_melee_select'];

    $a_weapon_list[] = $a_melee_select;
    foreach($a_weapon_list as $a_weapon)
    {
      $a_weapon['a_range'] = $this->getWeaponProperties($a_mech, $a_weapon);
      $this->a_weapon[] = $a_weapon;
    }
  }

  /**
   * Calculates the values for all roles.
   *
   * @param array $a_mech
   * @return void
   */
  public function roleAll(array $a_mech): void
  {
    $this->a_mech = $a_mech;
    $this->a_brawler = $this->roleBrawler($a_mech);
    $this->a_cavalry = $this->roleCavalry($a_mech);
    $this->a_fast_hunter = $this->roleFastHunter($a_mech);
    $this->a_harasser = $this->roleHarasser($a_mech);
    $this->a_hunter = $this->roleHunter($a_mech);
    $this->a_melee = $this->roleMelee($a_mech);
    $this->a_sniper = $this->roleSniper($a_mech);
  }

  /**
   * Calculates values for the brawler role.
   * A brawler is a mech that gets in close and tries to dominate with superior firepower.
   * Example mechs: Hunchback, Warhammer
   *
   * @param array $a_mech
   * @return array
   */
  public function roleBrawler(array $a_mech)
  {
    $is_jump = $a_mech['i_jump_tmm'] > $a_mech['i_run_tmm'];
    $i_tmm = max($a_mech['i_run_tmm'],$a_mech['i_jump_tmm']);
    $i_defence = $this->calculateDefence($a_mech, [
      'use_slow_move' => true
    ]);
    $i_offence = $this->calculateOffence($a_mech, [
      'i_modifier' => 7 + ($is_jump?1:0)
    ]);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_mech,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Calculates values for the cavalry role.
   * Uses speed and long range weapons to get better trades.
   * Example mechs: Stormcrow, Lancelot
   *
   * @param array $a_mech
   * @return array
   */
  public function roleCavalry(array $a_mech)
  {
    $is_jump = $a_mech['i_jump_tmm'] > $a_mech['i_run_tmm'];
    $i_tmm = max($a_mech['i_run_tmm'],$a_mech['i_jump_tmm']);
    $i_defence = $this->calculateDefence($a_mech, [
      'i_tmm_bonus' => intval(
        min(round($i_tmm*1.24)-$i_tmm,2)
      ),
      'is_stationary' => false,
    ]);
    $i_offence = $this->calculateOffence($a_mech, [
      'i_modifier' => 1 + ($is_jump?1:0),
      'i_start' => 6,
      'i_speed_boost' => $is_jump?$a_mech['i_jump']:$a_mech['i_run']*self::RUN_MODIFIER
    ]);

    // Better for comparisons.
    $i_offence = intval($i_offence * 1.5);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_mech,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Calculates values for the fast hunter role.
   * An hunter is like a harasser except it is optimized for engaging with
   * fast moving targets. A fast hunter is for even more extreme targets.
   * Example mechs: Cicada 4A, Spider 9M
   *
   * @param array $a_mech
   * @return array
   */
  public function roleFastHunter(array $a_mech): array
  {
    $is_jump = $a_mech['i_jump_tmm'] > $a_mech['i_run_tmm'];
    $i_tmm = max($a_mech['i_run_tmm'],$a_mech['i_jump_tmm']);
    $i_defence = $this->calculateDefence($a_mech, [
      'use_slow_move' => false,
    ]);
    $i_offence = $this->calculateOffence($a_mech, [
      'i_end' => 3,
      'i_modifier' => 11+($is_jump?1:0),
      'i_speed_boost' => $is_jump?$a_mech['i_jump']:$a_mech['i_run']*self::RUN_MODIFIER
    ]);

    // Better for comparisons.
    $i_offence = intval($i_offence * 3.5);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_mech,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Calculates values for the harasser role.
   * Harassers use high speed to get close to their target.
   * This lets them use short range weaponry more effectively.
   * Example Mechs: Jenner-F, Blitzkrieg
   *
   * @param array $a_mech
   * @return array
   */
  public function roleHarasser(array $a_mech): array
  {
    $is_jump = $a_mech['i_jump_tmm'] > $a_mech['i_run_tmm'];
    $i_tmm = max($a_mech['i_run_tmm'],$a_mech['i_jump_tmm']);
    $i_defence = $this->calculateDefence($a_mech, [
      'use_slow_move' => true,
    ]);
    $i_offence = $this->calculateOffence($a_mech, [
      'i_end' => 3,
      'i_modifier' => 8+($is_jump?1:0),
      'i_speed_boost' => $is_jump?$a_mech['i_jump']:$a_mech['i_run']*self::RUN_MODIFIER
    ]);

    // Better for comparisons.
    $i_offence = intval($i_offence * 1.5);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_mech,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Calculates values for the hunter role.
   * An hunter is like a harasser except it is optimized for engaging with
   * fast moving targets.
   * Example mechs: Wraith, Nova-S
   *
   * @param array $a_mech
   * @return array
   */
  public function roleHunter(array $a_mech): array
  {
    $is_jump = $a_mech['i_jump_tmm'] > $a_mech['i_run_tmm'];
    $i_tmm = max($a_mech['i_run_tmm'],$a_mech['i_jump_tmm']);
    $i_defence = $this->calculateDefence($a_mech, [
      'use_slow_move' => true,
    ]);
    $i_offence = $this->calculateOffence($a_mech, [
      'i_end' => 3,
      'i_modifier' => 8+($is_jump?1:0),
      'i_speed_boost' => $is_jump?$a_mech['i_jump']:$a_mech['i_run']*self::RUN_MODIFIER
    ]);

    // Better for comparisons.
    $i_offence = intval($i_offence * 1.5);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_mech,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Calculates values for the melee role.
   * This is a special role, it solely looks at a mech's ability to cause damage in melee.
   * Example mechs: Ostsol-8M, Axeman
   *
   * @param array $a_mech
   * @return array
   */
  public function roleMelee(array $a_mech)
  {
    $i_defence = $this->calculateDefence($a_mech, [
      'use_slow_move' => false
    ]);
    $i_offence = $this->calculateOffence($a_mech, []);
    // TODO
    // Add flag for TSM
    // Add flag for Melee damage only

    // Better for comparisons.
    $i_offence = intval($i_offence * 2.5);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_mech,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Calculates values for the sniper role.
   * Snipers rely on range and position for defence.
   * Example mechs: Archer, Marauder
   *
   * @param array $a_mech
   * @return array
   */
  public function roleSniper(array $a_mech)
  {
    $i_defence = $this->calculateDefence($a_mech, [
      'is_stationary' => true
    ]);
    // Snipers stay at range, so calculate ranges 1-5 as range 6.
    $i_offence = $this->calculateOffence($a_mech, [
      'i_modifier' => 7,
      'i_speed_boost' => 3, // This starts at range 6. Compensates for the start range being 6.
      'i_start' => 6,
    ]);

    // Better for comparisons.
    $i_offence = intval($i_offence * 1.1);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_mech,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

//  public function roleTest(array $a_mech, string $s_role): array
//  {
//    $a_return = [];
//    switch($s_role)
//    {
//      case 'Brawler': $a_return = self::roleBrawler($a_mech); break;
//      case 'Fast Hunter': $a_return = self::roleFastHunter($a_mech); break;
//      case 'Harasser': $a_return = self::roleHarasser($a_mech); break;
//      case 'Hunter': $a_return = self::roleHunter($a_mech); break;
//      case 'Sniper': $a_return = self::roleSniper($a_mech); break;
//      default: break;
//    }
//
//    return $a_return;
//  }

  /**
   * Calculates analysis for a specific range.
   *
   * @param array $a_mech
   * @param int $i_range
   * @return array
   */
  public function specificRange(array $a_mech, int $i_range): array
  {
    $i_defence = $this->calculateDefence($a_mech, [
      'is_stationary' => false
    ]);

    $i_offence = $this->calculateOffence($a_mech, [
      'i_end' => $i_range,
      'i_start' => $i_range,
    ]);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_mech,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Gets a collection of mech role values.
   *
   * @return array - The values for each role.
   */
  public function submit(): array
  {
    return [
      'Brawler' => $this->a_brawler,
      'Cavalry' => $this->a_cavalry,
      'Fast Hunter' => $this->a_fast_hunter,
      'Harasser' => $this->a_harasser,
      'Hunter' => $this->a_hunter,
//      'Melee' => $this->a_melee,
      'Sniper' => $this->a_sniper,
    ];
  }
}

?>