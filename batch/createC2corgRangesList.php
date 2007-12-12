<?php
/**
 * Generates ranges config list for c2corg.
 *
 * @version $Id: createC2corgRangesList.php 2234 2007-10-31 16:14:39Z alex $
 */

define('SF_ROOT_DIR',    realpath(dirname(__FILE__).'/..'));
define('SF_APP',         'frontend');
define('SF_ENVIRONMENT', 'prod');
define('SF_DEBUG',       false);

require_once(SF_ROOT_DIR.DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR.SF_APP.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.php');

// needed for doctrine connection to work
sfContext::getInstance();

$sql = 'SELECT id, name, external_region_id FROM regions WHERE system_id = 1 ORDER BY id ASC';
$res = sfDoctrine::connection()->standaloneQuery($sql)->fetchAll();

$output = '';

foreach ($res as $r)
{
    $output .= sprintf("      %d: %d # %s\n", $r['external_region_id'], $r['id'], $r['name']);
}

echo $output;
//file_put_contents("c2c_ranges.txt", $output);
