<?php

if (extension_loaded('gd') && function_exists('gd_info')) {
    echo "<h1>Success!</h1>";
    echo "<p>The GD library is enabled on your server.</p>";
    echo "<pre>";
    print_r(gd_info());
    echo "</pre>";
} else {
    echo "<h1>Error</h1>";
    echo "<p>The GD library is NOT enabled. Please enable it in your php.ini file by uncommenting (removing the ';') from the line: <code>extension=gd</code></p>";
}
