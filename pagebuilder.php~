<?php

$pb_string = "";
$pb_error = "";

/**
 * Loads the chosen template
 * Should be the first function of this file to be called
 */
function pb_init($mainfile = "default.html")
{
    global $pb_string;
    global $pb_error;
    
    $mainfile = "html/" . $mainfile;
    if (file_exists($mainfile))
        $pb_string = file_get_contents($mainfile);
    else
        $pb_string = "ERROR: " . $mainfile . " doesn't exist!\n";
}

/**
 * Replaces the first occurence of %%$target%% in the current output string with $with
 
 * e.g. pb_replace("date", "1.1.98")
 * transforms "_______%%date%%_______%%date%%"
 * to         "_______1.1.98_______%%date%%"
 
 * returns true iff the substring was found
 */
function pb_replace_with($target, $with)
{
    global $pb_string;
    global $pb_error;

    // %% signifies special strings
    $target = "%%" . $target . "%%";
    
    // There's no simple function to replace the first occurence of a substring
    $pos = strpos($pb_string,$target);
    if ($pos !== false) {
        $pb_string = substr_replace($pb_string,$with,$pos,strlen($target));
        return true;
    }
    return false;
}

/**
 * Replaces ALL occurences of %%$target%% in the current output string with the file associated with it
 * e.g. pb_replace("date")
 * transforms "_______%%date%%_______%%date%%"
 * to         "_______1.1.98_______1.1.98"
 * if there is a file called html/date.html containing only "1.1.98"
 */
function pb_replace_all($target, $file = "")
{
    global $pb_string;
    global $pb_error;

    // First get the target file's content if it exists
    if ($file == "")
    $file = $target;
    $file = "html/" . $file;
    if (!file_exists($file))
    {
        $pb_error .= "ERROR: " . $file . " doesn't exist!\n";
        return;
    }
    $with = file_get_contents($file);
    
    // Now we can actually replace the substrings
    $target = "%%" . $target . "%%";
    $pb_string = str_replace($target, $with, $pb_string);
}

function pb_print()
{
    global $pb_string;
    global $pb_error;

    if ($pb_error != "")
        echo $pb_error;
    else
        echo $pb_string;
}

?>