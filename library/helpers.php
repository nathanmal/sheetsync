<?php 

/**
 * Pretty Printer
 */
if( ! function_exists('print_p') ) {

  function print_p($var) 
  {
    echo '<pre>';
    if( is_array($var) ) {
      print_r($var);
    } else{
      var_dump($var);
    }
    echo '</pre>';
  }

}