<?php
// Fehler sichtbar machen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// MyBB-Konstante setzen und Core laden
define('IN_MYBB', 1);
// Hier up four levels von /admin/modules/config/karten → MyBB-Root
require_once dirname(__DIR__, 4) . '/global.php';

global $db, $mybb;

// 1) Region aus GET holen
$region = trim($_GET['region'] ?? '');
if($region === '') {
    echo '<option value="">-- erst Region wählen --</option>';
    exit;
}

// 2) FID_LOCATION aus karte_region_map
$escaped = $db->escape_string($region);
$qMap = $db->simple_select(
    'karte_region_map',
    'fid_location',
    "region_value='{$escaped}'",
    ['limit' => 1]
);
$fid_location = (int)$db->fetch_field($qMap, 'fid_location');
if(!$fid_location) {
    echo '<option value="">-- keine Standorte --</option>';
    exit;
}

// 3) Profilfeld‐Definition aus profilefields lesen
$qField = $db->simple_select(
    'profilefields',
    'type',
    "fid={$fid_location}",
    ['limit' => 1]
);
$typeRaw = $db->fetch_field($qField, 'type');
$lines = array_filter(array_map('trim', preg_split('/\r?\n/', $typeRaw)));
array_shift($lines); // Überschrift entfernen

// 4) Optionen ausgeben
echo '<option value="">-- wählen --</option>';
foreach($lines as $opt) {
    $val = htmlspecialchars_uni($opt);
    echo "<option value=\"{$val}\">{$val}</option>\n";
}
