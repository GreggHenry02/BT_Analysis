<?php

namespace BT_Analysis;

/**
 * Weapon type information.
 */
class WeaponTypeSid extends Sid
{
  /**
   * Direct fire energy weapons like the medium laser.
   */
  public const ENERGY = 1;

  /**
   * Direct fire ballistic weapons like the AutoCannon 5.
   */
  public const BALLISTIC = 2;

  /**
   * LBX autocannons.
   */
  public const LBX = 6;

  /**
   * Long Range Missiles.
   */
  public const LRM = 3;

  /**
   * Short Range Missiles.
   */
  public const SRM = 4;

  /**
   * Ultra autocannons.
   */
  public const ULTRA = 5;

  public const TYPE = [
    'Energy' => [
      'has_ammo' => false,
    ],
    'Ballistic' => [
      'has_ammo' => true,
    ],
    'LBX' => [
      'i_cluster_size' => 1,
      'has_ammo' => true,
    ],
    'LRM' => [
      'i_cluster_size' => 5,
      'has_ammo' => true,
    ],
    'SRM' => [
      'i_cluster_size' => 2,
      'i_damage_multiplier' => 2,
      'has_ammo' => true,
    ],
    'STREAKSRM' => [
      'i_cluster_size' => 2,
      'i_damage_multiplier' => 2,
      'is_streak' => true,
      'has_ammo' => true,
    ],
    'Ultra' => [
      'is_ultra' => true,
      'has_ammo' => true
    ]
  ];

  /**
   * Gets the cluster size for a weapon type. For instance each LRMs cluster damage in groups of 5.
   *
   * @param string $sid_type - The string indentifier for the weapon type.
   * @return int - The cluster size.
   */
  public static function getClusterSize(string $sid_type): int
  {
    if(isset(self::TYPE[$sid_type]))
      if(isset(self::TYPE[$sid_type]['i_cluster_size']))
        return self::TYPE[$sid_type]['i_cluster_size'];
    return 1;
  }

  /**
   * Gets the ammunition damage multiplier for a weapon type. For instance each SRMs does 2 damage.
   *
   * @param string $sid_type - The string indentifier for the weapon type.
   * @return int - The damage multiplier.
   */
  public static function getDamageMultiplier(string $sid_type): int
  {
    if(isset(self::TYPE[$sid_type]))
      if(isset(self::TYPE[$sid_type]['i_damage_multiplier']))
        return self::TYPE[$sid_type]['i_damage_multiplier'];
    return 1;
  }
}

?>