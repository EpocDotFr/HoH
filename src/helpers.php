<?php
/**
 * CrowdMixer
 *
 * Helpers 
 */

/**
 * Retourne sous forme textuelle le temps passé depuis une cetaine date
 */
function time_ago($ptime) {
  if (is_string($ptime)) {
    $ptime = strtotime($ptime);
  }
  
  $etime = time() - $ptime;

  if ($etime < 1) {
    return '0 secondes';
  }

  $a = array( 12 * 30 * 24 * 60 * 60  =>  'année',
    30 * 24 * 60 * 60       =>  'mois',
    24 * 60 * 60            =>  'jour',
    60 * 60                 =>  'heure',
    60                      =>  'minute',
    1                       =>  'seconde'
  );

  foreach ($a as $secs => $str) {
    $d = $etime / $secs;
    
    if ($d >= 1) {
      $r = round($d);
      return $r . ' ' . $str . ($r > 1 ? 's' : '');
    }
  }
}

/**
 * Retourne sous forme textuelle une certaine durée
 */
function human_time($secs) {
  $units = array(
          "semaine"   => 7*24*3600,
          "jour"    =>   24*3600,
          "heure"   =>      3600,
          "minute" =>        60,
          "seconde" =>         1,
  );


  if ( $secs == 0 ) return "0 secondes";

  $s = "";

  foreach ($units as $name => $divisor) {
    if ($quot = intval($secs / $divisor)) {
      $s .= "$quot $name";
      $s .= (abs($quot) > 1 ? "s" : "") . ", ";
      $secs -= $quot * $divisor;
    }
  }

  return substr($s, 0, -2);
}