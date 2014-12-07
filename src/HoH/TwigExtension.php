<?php
namespace HoH;


class TwigExtension extends \Twig_Extension {
  public function getName() {
    return 'HoH';
  }

  public function getFilters() {
    return array(
      new \Twig_SimpleFilter('time_ago', array($this, 'timeAgo')),
      new \Twig_SimpleFilter('human_time', array($this, 'humanTime')),
    );
  }
  
  public function getFunctions() {
    return array(
      new \Twig_SimpleFunction('now', array($this, 'now')),
      new \Twig_SimpleFunction('getConfig', array($this, 'getConfig'))
    );
  }
  
  public function now() {
    return time();
  }
  
  public function timeAgo($ptime) {
    return time_ago($ptime);
  }

  public function humanTime($secs) {
    return human_time($secs);
  }
} 