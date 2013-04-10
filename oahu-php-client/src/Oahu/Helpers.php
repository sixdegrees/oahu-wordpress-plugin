<?php

function rglob($pattern='*', $flags = 0, $path='') {
    $paths=glob($path.'*', GLOB_MARK|GLOB_ONLYDIR|GLOB_NOSORT);
    $files=glob($path.$pattern, $flags);
    foreach ($paths as $path) { $files=array_merge($files,rglob($pattern, $flags, $path)); }
    return $files;
}


class Oahu_Helpers {
  public static function includeTemplates($path) {
    if ($path[count($path) - 1] != "/") {
      $path = $path . "/";
    }
    $ret = array();
    $tpls = rglob("*.hbs", 0, $path);
    foreach ($tpls as $filename){
      $tpl_name = str_replace($path, "", $filename);
      $tpl_name = str_replace(".hbs", "", $tpl_name);
      $tpl_name = str_replace("/", "_", $tpl_name);
      $tpl = "<script type='text/template' data-oahu-template='$tpl_name'>";
      $tpl .= str_replace("", "", file_get_contents($filename));
      $tpl .= "</script>";
      $ret[] = $tpl;
    }
    return implode("\n", $ret);
  }

  public static function includeWidgets($path) {
    global $baseUri;
    $ret = array();
    foreach (glob($path."/*.js") as $filename){
      // $ret[] = "<script type='text/javascript' src='" . $baseUri . "/assets/widgets/" . basename($filename) . "'></script>";
      $ret[] = file_get_contents($filename);
    }
    return '<script>' . implode("\n;", $ret) . '</script>';
  }

  public function readAppFile($fname) {
    $path = dirname(dirname(dirname(__FILE__)));
    return htmlspecialchars(file_get_contents($path . "/app/" . $fname . ".php"));
  }

  public function readFile($file){
    return htmlspecialchars(file_get_contents($file));
  }
}
