<?php

/**
 * Inspect a value(s)
 *
 * @param mixed $value
 * @return void
 */
function inspect($value)
{
    echo '
<pre>';
    var_dump($value);
    echo '</pre>';
}


/**
 * Inspect a value(s) and die
 *
 * @param mixed $value
 * @return void
 */
function inspectAndDie($value)
{
    echo '
<pre>';
    die(var_dump($value));
    echo '</pre>';
}
