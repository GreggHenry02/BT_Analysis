<?php

namespace BT_Analysis;

require_once "WeaponTypeSid.php";

/**
 * Get amd present weapon data from a csv file.
 */
class WeaponData
{
  private const CLUSTER_HITS_TABLE = [
    [0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0,	0],
    [1,	1,	1,	1,	1,	1,	1,	1,	1,	1,	1,	1,	1],
    [1,	1,	1,	1,	1,	1,	1,	1,	2,	2,	2,	2,	2],
    [1,	1,	1,	1,	1,	2,	2,	2,	2,	2,	3,	3,	3],
    [1,	1,	1,	2,	2,	2,	2,	3,	3,	3,	3,	4,	4],
    [1,	1,	1,	2,	2,	3,	3,	3,	3,	4,	4,	5,	5],
    [1,	1,	2,	2,	3,	3,	4,	4,	4,	5,	5,	6,	6],
    [1,	1,	2,	2,	3,	4,	4,	4,	4,	6,	6,	7,	7],
    [1,	1,	3,	3,	4,	4,	5,	5,	5,	6,	6,	8,	8],
    [1,	1,	3,	3,	4,	5,	5,	5,	5,	7,	7,	9,	9],
    [1,	1,	3,	3,	4,	6,	6,	6,	6,	8,	8,	10,	10],
    [1,	1,	4,	4,	5,	7,	7,	7,	7,	9,	9,	11,	11],
    [1,	1,	4,	4,	5,	8,	8,	8,	8,	10,	10,	12,	12],
    [1,	1,	4,	4,	5,	8,	8,	8,	8,	11,	11,	13,	13],
    [1,	1,	5,	5,	6,	9,	9,	9,	9,	11,	11,	14,	14],
    [1,	1,	5,	5,	6,	9,	9,	9,	9,	12,	12,	15,	15],
    [1,	1,	5,	5,	7,	10,	10,	10,	10,	13,	13,	16,	16],
    [1,	1,	5,	5,	7,	10,	10,	10,	10,	14,	14,	17,	17],
    [1,	1,	6,	6,	8,	11,	11,	11,	11,	14,	14,	18,	18],
    [1,	1,	6,	6,	8,	11,	11,	11,	11,	15,	15,	19,	19],
    [1,	1,	6,	6,	9,	12,	12,	12,	12,	16,	16,	20,	20],
    [1,	1,	7,	7,	9,	13,	13,	13,	13,	17,	17,	21,	21],
    [1,	1,	7,	7,	9,	14,	14,	14,	14,	18,	18,	22,	22],
    [1,	1,	7,	7,	10,	15,	15,	15,	15,	19,	19,	23,	23],
    [1,	1,	8,	8,	10,	16,	16,	16,	16,	20,	20,	24,	24],
    [1,	1,	8,	8,	10,	16,	16,	16,	16,	21,	21,	25,	25],
    [1,	1,	9,	9,	11,	17,	17,	17,	17,	21,	21,	26,	26],
    [1,	1,	9,	9,	11,	17,	17,	17,	17,	22,	22,	27,	27],
    [1,	1,	9,	9,	11,	17,	17,	17,	17,	23,	23,	28,	28],
    [1,	1,	10,	10,	12,	18,	18,	18,	18,	23,	23,	29,	29],
    [1,	1,	10,	10,	12,	18,	18,	18,	18,	24,	24,	30,	30],
    [1,	1,	12,	12,	18,	24,	24,	24,	24,	32,	32,	40,	40]];

  /**
   * List of all available ammo aliases.
   * Not all ammunition will have an alias.
   *
   * @var array|null
   */
  public static ?array $a_ammo_alias = null;

  /**
   * List of all available weapon types.
   * The key is the weapon's name.
   *
   * @var array|null
   */
  public static ?array $a_weapon = null;

  /**
   * List of all available weapon types.
   * The key is the weapon's display name.
   *
   * @var array|null
   */
  public static ?array $a_weapon_display_name = null;

  /**
   * Checks if the weapon uses ammunition.
   *
   * @param string $s_name - The weapon name.
   * @return bool - True if the weapon uses ammo, false if it does not.
   */
  public static function checkAmmoUse(string $s_name): bool
  {
    return (bool) self::getWeapon($s_name)['i_ammo_per_ton'];
  }

  /**
   * Checks if a given weapon is recognized.
   *
   * @param string $s_name - The name of the weapon to check, in MTF format such as 'ISMediumLaser'.
   * @return bool - Returns true if weapon is found.
   */
  public static function checkWeapon(string $s_name): bool
  {
    if(empty($s_name))
      return false;
    if(isset(self::$a_weapon[$s_name]))
      return true;
    return false;
  }

  /**
   * Gets ammo information for the current slot.
   *
   * @param string $s_line
   * @return array Ammunition informatiom.
   */
  public static function getAmmo(string $s_line): array
  {
    $a_split = explode('Ammo',$s_line);
    if(count($a_split) == 1)
      return [];
    // Helpful for sorting through some types of munitions.
    if(str_contains($a_split[0],' '))
    {
      $s_alias = WeaponData::getAmmoAlias(trim($a_split[0]));
      if($s_alias)
        $a_split[0] = $s_alias;
      else
        $a_split[0] = explode(' ',$a_split[0])[0];
    }

    $a_weapon = self::getWeapon(trim($a_split[0]));
    if(!$a_weapon)
      $a_weapon = self::getWeapon(self::getAmmoAlias(trim($a_split[0])));

    $i_count = intval(trim($a_split[1],'()'));
    $i_count = $i_count > 0 ? $i_count : $a_weapon['i_ammo_per_ton'];

    $id_weapon = 0;
    $s_weapon_name = '';
    if($a_weapon)
    {
      $id_weapon = $a_weapon['id_weapon'];
      $s_weapon_name = $a_weapon['s_name'];
    }

    return [
      'i_damage' => $a_weapon?$a_weapon['i_damage']*WeaponTypeSid::getDamageMultiplier($a_weapon['sid_type']):0,
      'i_count' => $i_count,
      'id_weapon' => $id_weapon,
      'is_explode' => true,
      's_weapon_name' => $s_weapon_name
    ];
  }

  /**
   * Returns the MTF tag for a weapon if the ammo alias is determined.
   *
   * @param string $s_alias
   * @return string|bool
   */
  public static function getAmmoAlias(string $s_alias): string|bool
  {
    if(!isset(self::$a_ammo_alias))
      self::setAmmoAlias();

    if(isset(self::$a_ammo_alias[$s_alias]))
      return self::$a_ammo_alias[$s_alias];
    else
      return false;
  }

  /**
   * Returns the ammunition alias for a MTF tag.
   *
   * @param string $s_name The name of the weapon.
   * @return string - The ammunition alias.
   */
  public static function getAmmoName(string $s_name): string
  {
    if(!isset(self::$a_ammo_alias))
      self::setAmmoAlias();

    $a_ammo_name = array_flip(self::$a_ammo_alias);
    if(isset($a_ammo_name[$s_name]))
      return $a_ammo_name[$s_name];
    else
      return '';
  }

  /**
   * Gets the number of cluster hits.
   *
   * @param int $i_size - The size of the cluster table to check.
   * @param int $i_roll - The result of the 2d6 roll on the cluster table. Modifiers should be applied before this call.
   * @return int
   */
  public static function getClusterHits(int $i_size, int $i_roll): int
  {
    if ($i_size < 0) return 1;
    if ($i_size > 31) $i_size = 31; // 31 is the 40 cluster size.
    if ($i_roll < 1) $i_roll = 1;
    if ($i_roll > 12) $i_roll = 12;
    return self::CLUSTER_HITS_TABLE[$i_size][$i_roll];
  }

  /**
   * Returns a weapon where all properties are 0.
   *
   * @return array
   */
  public static function getEmptyWeapon(): array
  {
    $s_first = array_key_first(self::$a_weapon);
    $a_empty = [];
    foreach(self::$a_weapon[$s_first] as $s_key => $x_value)
      $a_empty[$s_key] = 0;
    return $a_empty;
  }

  /**
   * Gets the weapon range modifier.
   *
   * @param int $i_range - The range of the attack.
   * @param string $s_name - The name of the weapon.
   * @param ?BattleMech $o_mech - (Optional) The BattleMech using the weapon.
   * @return int|null - The range modifier or null if not valid.
   */
  public static function getRangeModifier(int $i_range, string $s_name, ?BattleMech $o_mech=null): int|null
  {
    $a_weapon = self::getWeapon($s_name);
    $i_modifier = intval($a_weapon['i_accuracy']) ?? 0;

    if($i_range > $a_weapon['i_long'] || $i_range < 1)
      return null;
    if($i_range <= $a_weapon['i_long'] && $i_range > $a_weapon['i_medium'])
      return $i_modifier+4;
    if($i_range <= $a_weapon['i_medium'] && $i_range > $a_weapon['i_short'])
      return $i_modifier+2;

    // Short range, check for minimum range.
    if($a_weapon['i_min_range'])
      $i_modifier = 1 + $a_weapon['i_min_range'] - $i_range;

    $id_type = WeaponTypeSid::id($a_weapon['sid_type']);
    if($id_type == WeaponTypeSid::LBX && $o_mech && !$o_mech->getParameter('use_lbx_slug'))
      $i_modifier --; // LBX Cluster rounds have a bonus to hit.

    return $i_modifier;
  }

  /**
   * Gets cluster informaiton for a weapon.
   *
   * @param string $s_name - The weapon name.
   * @param ?BattleMech $o_mech - (Optional) The BattleMech using the weapon.
   * @return array - Volley information, if applicable.
   * @throws \ReflectionException
   */
  public static function getCluster(string $s_name, ?BattleMech $o_mech=null): array
  {
    $a_weapon = self::getWeapon($s_name);
    $id_type = WeaponTypeSid::id($a_weapon['sid_type']);
    $a_cluster = [];
    if($id_type)
    {
      if($id_type == WeaponTypeSid::LRM || $id_type == WeaponTypeSid::SRM)
      {
        $a_cluster['i_cluster_size'] = WeaponTypeSid::getClusterSize(WeaponTypeSid::sid($id_type));
        $a_cluster['i_damage_multiplier'] = WeaponTypeSid::getDamageMultiplier(WeaponTypeSid::sid($id_type));
        $a_cluster['i_launcher_size'] = $a_weapon['i_damage'];
      }
      elseif($id_type == WeaponTypeSid::LBX)
      {
        if($o_mech->getParameter('use_lbx_slug'))
          return [];
        $a_cluster['i_cluster_size'] = 1;
        $a_cluster['i_damage_multiplier'] = 1;
        $a_cluster['i_launcher_size'] = $a_weapon['i_damage'];
      }
      elseif($id_type == WeaponTypeSid::ULTRA)
      {
        $a_cluster['i_cluster_size'] = $a_weapon['i_damage'];
        $a_cluster['i_damage_multiplier'] = $a_weapon['i_damage'];
        $a_cluster['i_launcher_size'] = 2;
      }
    }
    return $a_cluster;
  }

  /**
   * Gets weapon data.
   *
   * @param string $s_name - The name of the weapon to check, in MTF format such as 'ISMediumLaser'.
   * @return array|bool - Returns array of weapon information if found, or false if weapon is not found.
   */
  public static function getWeapon(string $s_name): array|bool
  {
    if(isset(self::$a_weapon[$s_name]))
    {
      return self::$a_weapon[$s_name];
    }
    foreach(self::$a_weapon as $a_weapon)
    {
      if(strtolower($a_weapon['s_display_name']) == strtolower($s_name))
        return $a_weapon;
    }
    return false;
  }

  /**
   * Gets all weapon names.
   *
   * @return array|bool - List of weapon names.
   */
  public static function getWeaponList(): array
  {
    if(isset(self::$a_weapon))
      return array_keys(self::$a_weapon);
    return [];
  }

  /**
   * Returns header information from the CSV.
   *
   * @param string $s_line - The header line from the .csv.
   * @return array|null - Header information, key is the header name and the value its place in the array.
   */
  private static function procHeader(string $s_line): ?array
  {
    $s_element = explode(',',$s_line);

    return array_flip($s_element);
  }

  /**
   * Read the contents of the file.
   */
  public static function readFile(): void
  {
    $s_filename='WeaponData.csv';
    if(!file_exists($s_filename))
    {
      echo "File ".$s_filename." does not exist.\n";
      return;
    }
    $s_content = file_get_contents($s_filename);
    $a_content = explode(PHP_EOL,$s_content);
    $is_header = true;
    $id_weapon = 1;
    foreach($a_content as $s_line)
    {
      $s_empty = str_replace(',','',$s_line);
      if(empty(str_replace(',','',$s_line)))
        continue;
      if($is_header)
      {
        $a_header = self::procHeader($s_line);
        $is_header = false;
      }
      else
      {
        $a_element = explode(',',$s_line);
        if(count($a_element) >= count($a_header) && !empty($a_element[$a_header['Name']]))
        {
          $a_weapon = [
            'i_accuracy' => intval($a_element[$a_header['Accuracy']]),
            'i_ammo_per_ton' => intval($a_element[$a_header['AmmoPerTon']]),
            'i_critical' => intval($a_element[$a_header['Criticals']]),
            'i_damage' => intval($a_element[$a_header['Damage']]),
            'i_heat' => intval($a_element[$a_header['Heat']]),
            'i_medium' => intval($a_element[$a_header['Medium']]),
            'i_min_range' => intval($a_element[$a_header['Min.Range']]),
            'i_long' => intval($a_element[$a_header['Long']]),
            'i_short' => intval($a_element[$a_header['Short']]),
            'id_weapon' => $id_weapon++,
            's_ammo_alias' => $a_element[$a_header['AmmoAlias']],
            's_display_name' => trim($a_element[$a_header['Display Name']]),
            's_name' => trim($a_element[$a_header['Name']]),
            's_subtype' => $a_element[$a_header['SubType']],
            'sid_type' => $a_element[$a_header['Type']],
          ];

          self::$a_weapon[$a_weapon['s_name']] = $a_weapon;
          self::$a_weapon_display_name[$a_weapon['s_display_name']] = $a_weapon;
        }
      }
    }
  }

  /**
   * Calculates the correspondence between ammo alias and weapon name.
   */
  private static function setAmmoAlias(): void
  {
    if(!isset(self::$a_weapon))
      return;
    self::$a_ammo_alias = [];
    foreach(self::$a_weapon as $s_name => $a_weapon)
    {
      if(!empty($a_weapon['s_ammo_alias']))
        self::$a_ammo_alias[trim($a_weapon['s_ammo_alias'])] = trim($s_name);
    }
  }

  /**
   * Determines if a weapon benefits from a targeting computer.
   *
   * @param string $sid_type
   * @param string $s_subtype
   * @return bool
   */
  public static function usesTargetingComputer(string $sid_type, string $s_subtype): bool
  {
    if(in_array($sid_type,[
      'Energy',
      'Ballistic',
      ]) && !in_array($s_subtype,[
      'Flamer',
      'MG'
    ]))
      return true;
    return false;
  }
}

?>