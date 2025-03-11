<?php

namespace BT_Analysis;

/**
 * Methods for managing string IDs (SIDs).
 */
abstract class Sid
{
  /**
   * A list of classes corresponding to IDs.
   * Each key will be the ID, value will be the class reference.
   *
   * @var array
   */
  public static $a_class = [];

  /**
   * Cached values from the reflection call, since the values are constants they should not change in a run.
   * Each array refers to a different class.
   *
   * @var array
   */
  public static array $a_constant = [];

  /**
   * The path to the ID classes.
   *
   * @var string
   */
  public static $s_class_path = '';

  /**
   * Checks that the cache is initialized, if not initialize it.
   *
   * @return array List of constants.
   * @throws \ReflectionException
   */
  public static function cacheConstants(): array
  {
    $s_class = get_called_class();
    if(!isset(static::$a_constant[$s_class]))
    {
      $o_reflection = new \ReflectionClass($s_class);
      $a_constant = $o_reflection->getConstants();
      static::$a_constant[$s_class] = $a_constant;
    }
    return static::$a_constant[$s_class];
  }

  /*
   * Returns list of constants.
   * @returns Associative array, Name as index, constant as value.
   */
  public static function getConstants(): array
  {
    $o_reflection = new \ReflectionClass(static::class);
    return $o_reflection->getConstants();
  }

  /*
   * Returns list of constants and the constant's name.
   * @param $is_space - If true return values with spaces between capitalized words.
   * @returns Associative array, constant as index, name as value.
   */
  public static function getLookUp($is_space = true): array
  {
    $a_constant = static::getConstants();
    $a_constant = array_flip($a_constant);
    if($is_space)
    {
      foreach($a_constant as &$s_name)
      {
        // This code doesn't recognize numbers.
        $a_name = str_split($s_name);
        $s_name = '';
        foreach($a_name as $s_char)
        {
          if($s_name && (ctype_upper($s_char) || ctype_digit($s_char)))
            $s_name.=' ';
          $s_name.=$s_char;
        }

      }
    }
    return $a_constant;
  }

  /**
   * Get an object instantiated from a class matching the ID.
   * This method will have static::$a_class and static::$s_class_path instantiated if they are not set.
   *
   * @param $s_class_path - The path which contains the classes. Trailing slash and * will be added.
   * @param $id_match - The ID value to match.
   * @return Object corresponding to the ID specified.
   */
  public static function getMatchingClass(string $s_class_path, int $id_match)
  {
    if(!static::$a_class || !static::$s_class_path || static::$s_class_path != $s_class_path)
      static::initClass($s_class_path);

    if(isset(static::$a_class[$id_match]))
      $o_match = new static::$a_class[$id_match];
    else
      $o_match = null;

    return $o_match;
  }

  /**
   * Takes a constant name and finds the matching ID number.
   * Should accept names in either literal or non-literal configurations.
   *
   * @param string $s_name The name of the constant.
   * @return int|null The ID matching the given name, null if no match found.
   * @throws \ReflectionException
   */
  public static function id(string $s_name): ?int
  {
    $a_constant = static::cacheConstants();

    foreach($a_constant as $s_constant => $i_id)
    {
      if($s_constant == $s_name)
        return $i_id;
      elseif($s_constant)
      {
        $s_title_case = str_replace('_',' ',$s_constant);
        $s_title_case = strtolower($s_title_case);
        $s_title_case = ucwords($s_title_case);
      }

      if($s_title_case == $s_name)
        return $i_id;
    }

    return null;
  }

  /**
   * Get a class featuring the matching ID.
   *
   * @param $s_class_path - The path which contains the classes. Trailing slash and * will be added.
   */
  public static function initClass(string $s_class_path): void
  {
    $s_class_path_full = $s_class_path;
    if(strpos($s_class_path_full,'\\') !== 0)
      $s_class_path_full = '\\'.$s_class_path_full;
    if(strpos($s_class_path_full,'\\*') === false)
      $s_class_path_full = $s_class_path_full.'\\*';

    // Require once all the classes in $s_class_path.
    foreach (scandir(dirname(__DIR__.$s_class_path_full)) as $s_filename) {
      $s_path = dirname(__DIR__.'/'.$s_class_path) . '/' . $s_filename;
      if (is_file($s_path)) {
        require_once $s_filename;
      }
    }

    // Filter for just the classes in $s_class_path.
    $a_class = get_declared_classes();
    $a_class_sid = [];
    foreach($a_class as $s_class)
    {
      if(is_subclass_of($s_class, 'Sfb\\'.$s_class_path))
      {
        $a_class_sid[] = $s_class;
      }
    }

    // Create the mapping of IDs to class name.
    foreach($a_class_sid as $s_class)
    {
      $o_reflection = new \ReflectionClass($s_class);
      $a_constant = $o_reflection->getConstants();
      $id_match = $a_constant['ID'];
      static::$a_class[$id_match] = $s_class;
    }

    static::$s_class_path = $s_class_path;
  }

  /*
   * Returns the name of the constant.
   *
   * @param $x_search Constant value to search for.
   * @param $is_space - If true return values with spaces between capitalized words.
   * @returns string|null The name of the constant.
   */
  public static function lookUp($x_search, $is_space=false)
  {
    $a_constant = self::getLookUp($is_space);
    $x_constant = null;
    foreach($a_constant as $x_key => $x_value)
    {
      if($x_key == $x_search)
        $x_constant = $x_value;
    }
    return $x_constant;
  }

  /**
   * Converts a numeric ID into its string representation.
   *
   * @param int $id - The numeric ID.
   * @param bool $is_literal - If <tt>true</tt> then return the string of the constant exactly.
   *   If <tt>false</tt> then return the string in title case.
   * @return string|null - The constant's name, <tt>null</tt> if not found.
   * @throws \ReflectionException
   */
  public static function sid(int $id, bool $is_literal=true): ?string
  {
    $a_constant = static::cacheConstants();

    $a_id = [];
    foreach($a_constant as $s_constant => $i_id)
    {
      if(is_int($i_id) && !isset($a_id[$i_id]))
      {
        $a_id[$i_id] = $s_constant;
      }
    }

    $s_result = $a_id[$id] ?? null;
    if($s_result && !$is_literal)
    {
      $s_result = str_replace('_',' ',$s_result);
      $s_result = strtolower($s_result);
      $s_result = ucwords($s_result);
    }
    return $s_result;
  }
}

?>
