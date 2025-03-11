<?php

namespace BT_Analysis;

require_once "Sid.php";

class LocationSid extends Sid
{
  /**
   * The number of internal structure points for a given tonnage of BattleMech.
   * Values retrieved from the Solaris Skunk Werks Github:
   * https://github.com/Solaris-Skunk-Werks/solarisskunkwerks/blob/9b11e34ed5892caa8e34249967ade7bd3e220ede/sswlib/src/main/java/states/stChassisREBP.java
   *
   * Format given in [ Center Torso, Side Torso, Arm, Leg ]
   */
  public const INTERNAL_STRUCTURE = [
    '10' => [ 4, 3, 1, 2 ],
    '15' => [ 5, 4, 2, 3 ],
    '20' => [ 6, 5, 3, 4 ],
    '25' => [ 8, 6, 4, 6 ],
    '30' => [ 10, 7, 5, 7 ],
    '35' => [ 11, 8, 6, 8 ],
    '40' => [ 12, 10, 6, 10 ],
    '45' => [ 14, 11, 7, 11 ],
    '50' => [ 16, 12, 8, 12 ],
    '55' => [ 18, 13, 9, 13 ],
    '60' => [ 20, 14, 10, 14 ],
    '65' => [ 21, 15, 10, 15 ],
    '70' => [ 22, 15, 11, 15 ],
    '75' => [ 23, 16, 12, 16 ],
    '80' => [ 25, 17, 13, 17 ],
    '85' => [ 27, 18, 14, 18 ],
    '90' => [ 29, 19, 15, 19 ],
    '95' => [ 30, 20, 16, 20 ],
    '100' => [ 31, 21, 17, 21 ]
  ];

  public const INVALID = 0;
  public const LEFT_ARM = 1;
  public const RIGHT_ARM = 2;
  public const LEFT_TORSO = 3;
  public const RIGHT_TORSO = 4;
  public const CENTER_TORSO = 5;
  public const CENTRE_TORSO = 5;
  public const HEAD = 6;
  public const LEFT_LEG = 7;
  public const RIGHT_LEG = 8;
  public const REAR_LEFT_TORSO = 11;
  public const REAR_RIGHT_TORSO = 12;
  public const REAR_CENTER_TORSO = 13;
  public const REAR_CENTRE_TORSO = 13;
  public const RANDOM = 14;
  public const FLOAT = 15;
  public const CRITICAL = 16;
  public const WEAPONS = 17; // Special, not a true location

  public $short = [
    'LA' => self::LEFT_ARM,
    'RA' => self::RIGHT_ARM,
    'LT' => self::LEFT_TORSO,
    'RT' => self::RIGHT_TORSO,
    'CT' => self::CENTER_TORSO,
    'HD' => self::HEAD,
    'LL' => self::LEFT_LEG,
    'RL' => self::RIGHT_LEG,
    'RTL' => self::REAR_LEFT_TORSO,
    'RTR' => self::REAR_RIGHT_TORSO,
    'RTC' => self::REAR_CENTER_TORSO,
  ];
}
?>