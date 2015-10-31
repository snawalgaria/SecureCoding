<?php
/**
 * Created by IntelliJ IDEA.
 * User: Ralph Oliver Schaumann
 * Date: 10/31/15
 * Time: 2:30 PM
 */

/**
 * function by which a simple checksum of a given integer value is calculated.
 * @param string $int_value, the integer value whose sum is to be calculated
 * @return int, -1 iff $int_value is not an integer, the checksum else
 */
function checksum($int_value){
    if(!is_numeric($int_value))
        return -1;
    $xsum = 0;
    for($i = 0, $tmp = $int_value . ""; $i < strlen($tmp); ++$i)
        $xsum += $tmp{$i};
    return $xsum;
}

function get_verification_values($value){

}

function generate_captcha(){
    $a = rand(1,5);
    $b = rand(1,5);
    $c = rand(0,10);
    return array("value" => ($a . " * " . $b . " + " . $c) . "", "xsum" => (checksum(($a * $b + $c) . "")));
}

?>