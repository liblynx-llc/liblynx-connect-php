<?php
//------------------------------------------------------------------------------------
// Common support functions for example scripts
//------------------------------------------------------------------------------------

function ask($prompt, $regex, $default = '')
{
    $msg = $prompt;
    if (!empty($default)) {
        $msg .= " ($default)";
    }
    $msg .= ": ";

    $valid = false;
    $result = '';
    while (!$valid) {
        $result = readline($msg);
        if (empty($result)) {
            $result = $default;
        }
        $valid = preg_match($regex, $result) !== false;
        if (!$valid) {
            echo "$result is not valid - please re-enter...\n";
        }
    }
    return $result;
}

function heading($title)
{
    echo "\n$title:\n";
    echo str_repeat('-', 70) . "\n";
}
