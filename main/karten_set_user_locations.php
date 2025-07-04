<?php
define('IN_MYBB', 1);
require_once 'global.php';
global $db, $mybb;

// Eingaben
$area = (int)$mybb->get_input('area', MyBB::INPUT_INT);
$type = $db->escape_string($mybb->get_input('type'));
$uid  = (int)$mybb->user['uid'];

// Hole aus map_areas die Profile-Feld-Info
$a = $db->fetch_array(
    $db->simple_select(
      'map_areas',
      'location_value, fid_location',
      "id={$area}"
    )
);
$locValue = $db->escape_string($a['location_value']);
$fidLoc   = (int)$a['fid_location'];

// JSON-Header
header('Content-Type: application/json');

if($type === 'home' && $fidLoc) {
    // 1) Profilfeld setzen
    $db->update_query(
      'userfields',
      ["fid{$fidLoc}" => $locValue],
      "ufid={$uid}"
    );
    // 2) Alte Home-Einträge aus eigener Tabelle löschen
    $db->delete_query(
      'user_locations',
      "uid={$uid} AND type='home'"
    );
    echo json_encode(['success'=>1]);
    exit;
}

// Dein bestehender Work-Logic hier:
if($type === 'work') {
    // ggf. vorher alte work-Einträge löschen …
    $db->delete_query(
      'user_locations',
      "uid={$uid} AND type='work'"
    );
    // dann neuen Eintrag anlegen
    $db->insert_query('user_locations', [
      'uid'      => $uid,
      'area_id'  => $area,
      'type'     => 'work'
    ]);
    echo json_encode(['success'=>1]);
    exit;
}

if($type === 'remove_home') {
    $fid = (int)$db->fetch_field($db->simple_select('karte_region_map','fid_location',"region_value != ''"), 'fid_location');
    if($fid) {
        $db->update_query('userfields', ["fid{$fid}" => ''], "ufid={$uid}");
        echo json_encode(['success'=>1]); exit;
    }
}

if($type === 'remove_work') {
    $db->delete_query('user_locations', "uid={$uid} AND type='work'");
    echo json_encode(['success'=>1]); exit;
}


// Fallback
echo json_encode(['error'=>'Ungültiger Typ']);
exit;
