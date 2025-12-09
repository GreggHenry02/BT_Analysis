<?php

namespace BT_Analysis;

class AlphaStrikeCalcRoleCalc
{
  /**
   * Calculates the defence total for a unit.
   *
   * @param array $a_unit
   * @param array $a_restrict
   * @return int - The defence total for the unit.
   */
  public function calculateDefence(array $a_unit, array $a_restrict): int
  {
    $i_defence = intval(round($a_unit['Armor'] * 1.5));
    $i_defence += intval(round($a_unit['Structure'] * 1.25));
    $i_tmm = $a_unit['TMM'];
    if(!empty($a_restrict['i_tmm_bonus']))
      $i_tmm += $a_restrict['i_tmm_bonus'];
    if(!empty($a_restrict['is_jump']))
      $i_tmm += 1;
    if(!empty($a_restrict['is_stationary']))
      $i_tmm = 0;
    $i_defence = intval(ceil($i_defence * (1+($i_tmm * .40))));

    return $i_defence;
  }

  /**
   * Calculates the offence total for a unit.
   *
   * @param array $a_unit
   * @param array $a_restrict
   * @return int - The offence total for the unit.
   */
  public function calculateOffence(array $a_unit, array $a_restrict): int
  {
    $i_short = $a_unit['Short'];
    if(!empty($a_restrict['r_short']))
      $i_short = intval(floor($i_short*$a_restrict['r_short']));

    $i_medium = $a_unit['Medium'];
    if(!empty($a_restrict['r_medium']))
      $i_medium = intval(floor($i_medium*$a_restrict['r_medium']));

    $i_long = $a_unit['Long'];
    if(!empty($a_restrict['r_long']))
      $i_long = intval(floor($i_long*$a_restrict['r_long']));

    $i_offence = $i_short + $i_medium + $i_long;

    $i_skill = 4;
    if(!empty($a_restrict['i_skill']))
      $i_skill = $a_restrict['i_skill'];

    if(!empty($a_restrict['is_jump']))
      $i_skill += 1;

    $i_offence = intval(ceil($i_offence * (1+((4-$i_skill) * .40))));


    return $i_offence;
  }

  /**
   * Gets the point value to battle value ratio.
   *
   * @param array $a_unit - The array of Unit information.
   * @param int $i_defence - The total defence value.
   * @param int $i_offence - The total offence value.
   * @param int $i_skill - The .
   * @return int - The ratio of points to BV, times 1000. Will equal the point total if no BV is set.
   */
  public function getRatio(array $a_unit, int $i_defence, int $i_offence, int $i_skill=4): int
  {
    $i_pv = 0;
    if(!empty($a_unit['PV']) && $a_unit['PV'] > 1)
      $i_pv = $a_unit['PV'];
    if($i_pv && $i_skill)
      $i_pv = intval($i_pv + floor($i_pv*((4-$i_skill)*0.40)));
    if($i_pv)
      return intval((($i_defence + $i_offence) / $i_pv)*1000);
    return ($i_defence + $i_offence);
  }

  /**
   * Calculates the values for all roles.
   *
   * @param array $a_unit
   * @return array - A collection of calculations for each role
   */
  public function roleAll(array $a_unit): array
  {
    return [
      'Brawler' => $this->roleBrawler($a_unit),
      'Cavalry' => $this->roleCavalry($a_unit),
//      'Harasser' => 0,
      'Hunter' => $this->roleHunter($a_unit),
      'Sniper' => $this->roleSniper($a_unit),
    ];
  }

  /**
   * Calculates values for the brawler role.
   *
   * @param array $a_unit
   * @return array
   */
  public function roleBrawler(array $a_unit): array
  {
    $i_defence = $this->calculateDefence($a_unit, []);
    $i_offence = $this->calculateOffence($a_unit, [
      'r_short' => 2,
      'r_medium' => 2,
      'r_long' => 2,
    ]);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_unit,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Calculates values for the sniper role.
   *
   * @param array $a_unit
   * @return array
   */
  public function roleCavalry(array $a_unit): array
  {
    $i_defence = $this->calculateDefence($a_unit, [
      'i_tmm_bonus' => 1,
    ]);
    $i_offence = $this->calculateOffence($a_unit, [
      'r_short' => 1,
      'r_medium' => 3,
      'r_long' => 2,
    ]);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_unit,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Calculates values for the sniper role.
   *
   * @param array $a_unit
   * @return array
   */
  public function roleHunter(array $a_unit): array
  {
    $i_defence = $this->calculateDefence($a_unit, [
    ]);
    $i_skill = 2;
    $i_offence = $this->calculateOffence($a_unit, [
      'i_skill' => $i_skill,
      'r_short' => 3,
      'r_medium' => 2,
      'r_long' => 1,
    ]);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_unit,$i_defence,$i_offence,$i_skill),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Calculates values for the sniper role.
   *
   * @param array $a_unit
   * @return array
   */
  public function roleSniper(array $a_unit): array
  {
    $i_defence = $this->calculateDefence($a_unit, [
      'is_stationary' => true
    ]);
    $i_offence = $this->calculateOffence($a_unit, [
      'r_short' => 1,
      'r_medium' => 3,
      'r_long' => 3,
    ]);

    return [
      'i_defence' => $i_defence,
      'i_offence' => $i_offence,
      'i_ratio' => $this->getRatio($a_unit,$i_defence,$i_offence),
      'i_total' => $i_defence + $i_offence,
    ];
  }

  /**
   * Set the values for the AlphaStrikeCalc table.
   *
   * @param array $a_row - Basic information for the unit from the database.
   * @param object $o_mysqli - The initialized database object.
   * @param int $i_test_level - The test level. If `0`, perform the action. If `1` or greater, log to console.
   * @return void
   */
  public function submit(array $a_row, object $o_mysqli, int $i_test_level=0): void
  {
    $a_calc_collection = $this->roleAll($a_row);
    foreach($a_calc_collection as $s_role => $a_calc)
    {
      if(empty($a_calc))
        continue;

//      var_dump($a_calc);
      if($i_test_level >= 1)
      {
        printf("%-30s %-8s %2d %2d %4d %4d\n",
          $a_row['Model'].' '.$a_row['Name'],
          $s_role,
          $a_calc['i_defence'],
          $a_calc['i_offence'],
          $a_calc['i_ratio'],
          $a_calc['i_total']
        );
      }
      $s_insert = '"'.implode('","',[
          $a_row['k_mtf']??0,
          $a_row['Id']??0,
          $a_calc['i_defence'],
          $a_calc['i_offence'],
          $a_calc['i_ratio'],
          $a_calc['i_total'],
          $s_role
        ]).'"';
      $s_insert = "insert into AlphaStrikeCalc (k_mtf,Id,i_defence,i_offence,i_ratio,i_total,s_role)
          values (".$s_insert.")";

      $o_mysqli->query($s_insert);
    }
  }
}

?>