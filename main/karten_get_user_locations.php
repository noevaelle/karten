<?php
define('IN_MYBB', 1);
require_once __DIR__ . '/global.php';
global $db, $mybb, $cache;

// 0) Jobliste aktiv?
$active = $cache->read('active_plugins');
$use_job = !empty($active['active'])
         && in_array('jobliste', $active['active']);

// 1) Area-ID einlesen
$area_id = (int)$mybb->get_input('area', MyBB::INPUT_INT);
header('Content-Type: application/json');
// kein gültiger Bereich? Leeres JSON zurück
if(!$area_id) {
    echo json_encode(['home'=>[], 'work'=>[]]);
    exit;
}

// 2) Existiert die Tabelle user_locations?
if(!$db->table_exists('user_locations')) {
    echo json_encode(['home'=>[], 'work'=>[]]);
    exit;
}

// 3) Home immer aus user_locations
$home = [];
$q1 = $db->simple_select(
    'user_locations',      // hier kein Präfix voranstellen!
    'uid',
    "area_id={$area_id} AND type='home'"
);
while($r = $db->fetch_array($q1)) {
    $u = get_user($r['uid']);
    $home[] = [
        'uid'      => $r['uid'],
        'username' => $u['username'],
        'link'     => 'member.php?action=profile&uid='.$r['uid']
    ];
}

// 4) Work: Jobliste-Plugin oder eigene Tabelle
$work = [];
if($use_job) {
    // Jobs-Tabelle mit korrektem Präfix ansteuern
    $q2 = $db->query("
      SELECT u.uid, u.username
      FROM {$db->table_prefix}users u
      JOIN {$db->table_prefix}jobs j ON u.jid=j.jid
      WHERE j.location='{$area_id}' AND j.type='work'
    ");
    while($u = $db->fetch_array($q2)) {
        $work[] = [
            'uid'      => $u['uid'],
            'username' => $u['username'],
            'link'     => 'member.php?action=profile&uid='.$u['uid']
        ];
    }
} else {
    $q3 = $db->simple_select(
        'user_locations',    // wieder ohne Präfix
        'uid',
        "area_id={$area_id} AND type='work'"
    );
    while($r = $db->fetch_array($q3)) {
        $u = get_user($r['uid']);
        $work[] = [
            'uid'      => $r['uid'],
            'username' => $u['username'],
            'link'     => 'member.php?action=profile&uid='.$r['uid']
        ];
    }
}

// 5) JSON ausgeben
echo json_encode(['home'=>$home, 'work'=>$work]);
exit;
