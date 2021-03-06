<?php
require_once('../functions.inc.php');

// Load status
$status = get_status();

// Load image details
$image = get_image_info();
if (is_string($image)) $error = $image;
$status->image = $image;

// Parse request to build src->target map
$status->type = preg_replace('/[^a-z]/', '', $_REQUEST['type']);
$status->parts = array();
$error = '';
switch ($status->type) {
case 'baremetal':
	foreach ($_REQUEST['baremetal_parts'] as $part) {
		$part = sane_dev($part);
		// Target partitions remapped to target drive + part number
		$part_num = preg_replace('/[^0-9]/', '', $part);
		$status->parts[$part] = $status->drive.$part_num;
	}
	$size_diff = $status->drive_bytes - $status->image->drive_bytes;
	if ($size_diff < 0)
		$error = 'Target drive is '.number_format(abs($size_diff / 1024**2)).'MB smaller than original';
	break;
case 'selective':
	foreach ($_REQUEST['selective_parts'] as $part) {
		$src = trim(sane_dev($part));
		$dst = trim(sane_dev($_REQUEST['map_'.$src]));
		if (empty($src)) {
			if (empty($error)) $error = 'Invalid partition specified';
			continue;
		}
		if (empty($dst)) {
			if (empty($error)) $error = 'Partition '.$src.' selected but no target specified';
			continue;
		}
		$status->parts[$src] = $dst;
		$size_diff = get_dev_bytes($dst) - $status->image->parts->$src->bytes;
		if ($size_diff < 0)
			if (empty($error)) $error = 'Target partition '.$dst.' is '.number_format(abs($size_diff / 1024**2)).'MB smaller than original';
	}
	break;
default:
	$error = 'Invalid restore type requested';
	break;
}

// Make sure a partition is selected for restore
if ( sizeof( (array) $status->parts ) == 0 )
	if (empty($error)) $error = 'No partitions selected to restore';

// Stop if there was an error
if (!empty($error)) {
	print json_encode(array(
		'status' => FALSE,
		'error' => $error,
	));
	exit;
}

// Success
set_status($status);
print json_encode(array(
	'status' => TRUE,
));

?>
