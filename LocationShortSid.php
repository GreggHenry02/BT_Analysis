<?php

namespace BT_Analysis;

require_once "Sid.php";
require_once "LocationSid.php";

class LocationShortSid extends Sid
{
  /**
   * Cached values from the reflection call, since the values are constants they should not change in a run.
   *
   * @var array
   */
  public const LA = LocationSid::LEFT_ARM;
  public const RA = LocationSid::RIGHT_ARM;
  public const LT = LocationSid::LEFT_TORSO;
  public const RT = LocationSid::RIGHT_TORSO;
  public const CT = LocationSid::CENTER_TORSO;
  public const HD = LocationSid::HEAD;
  public const LL = LocationSid::LEFT_LEG;
  public const RL = LocationSid::RIGHT_LEG;
  public const RTL = LocationSid::REAR_LEFT_TORSO;
  public const RTR = LocationSid::REAR_RIGHT_TORSO;
  public const RTC = LocationSid::REAR_CENTER_TORSO;
  public const RAND = LocationSid::RANDOM;
  public const FLOAT = LocationSid::FLOAT;
  public const CRIT = LocationSid::CRITICAL;
}
?>