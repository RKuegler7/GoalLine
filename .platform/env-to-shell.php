<?php

/*
 * Turns Elastic Beanstalk's environment properties (JSON on stdin) into a shell file of exports, so
 * command-line tasks on the server get the same settings as the website. Values are single-quoted,
 * so passwords with spaces, quotes, or dollar signs come through exactly.
 */

$properties = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);

foreach ($properties as $key => $value) {
    if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key)) {
        continue;
    }

    echo 'export ', $key, "='", str_replace("'", "'\''", (string) $value), "'\n";
}
