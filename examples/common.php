<?php
//------------------------------------------------------------------------------------
// Common support functions for example scripts
//------------------------------------------------------------------------------------

function ask($prompt, $regex, $default = '')
{
    $msg = $prompt . (empty($default) ? ': ' : " ($default): ");

    $valid = false;
    $result = '';
    while (!$valid) {
        $result = readline($msg);
        if (empty($result)) {
            $result = $default;
        }
        $valid = preg_match($regex, $result) === 1;
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

function askSecret(string $prompt): string
{
    //prompt for a value without echoing it to the terminal
    $hide = stream_isatty(STDIN);
    do {
        echo "$prompt: ";
        if ($hide) {
            shell_exec('stty -echo');
        }
        $value = trim((string)fgets(STDIN));
        if ($hide) {
            shell_exec('stty echo');
            echo "\n";
        }
    } while ($value === '');
    return $value;
}
