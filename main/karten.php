<?php
define('IN_MYBB', 1);
require_once 'global.php';
global $db, $mybb, $lang, $templates, $plugins, $cache;

// 0) Sprachdatei & Haupt‐Breadcrumbs
$lang->load('karten');
add_breadcrumb('Listen', 'listen.php');
add_breadcrumb($lang->karten, 'karten.php');

$path      = trim($mybb->settings['karten_image_path']) ?: 'images/karten';
$imageBase = rtrim($mybb->settings['bburl'], '/') . '/' . trim($path, '/') . '/';

// ======================================================================
// 1) LIST‐VIEW → Übersicht aller Karten
// ======================================================================
if (empty($mybb->input['image'])) {
    $q = $db->query("
        SELECT DISTINCT a.image_file,
               COALESCE(i.image_title, '') AS image_title
        FROM {$db->table_prefix}map_areas a
        LEFT JOIN {$db->table_prefix}map_images i
          ON i.image_file = a.image_file
        ORDER BY a.image_file
    ");

    if ($db->num_rows($q) == 0) {
        $cards_output = '<p>' . $lang->karten_no_areas . '</p>';
    } else {
        $cards_output = '<div class="karten-overview">';
        while ($r = $db->fetch_array($q)) {
            $fileRaw  = $r['image_file'];
            $bn       = pathinfo($fileRaw, PATHINFO_FILENAME);
            $titleRaw = $r['image_title']
                      ?: ucwords(str_replace(['_','-'], ' ', $bn));

            $fEsc     = htmlspecialchars($fileRaw, ENT_QUOTES);
            $tEsc     = htmlspecialchars($titleRaw, ENT_QUOTES);
            $url      = 'karten.php?image=' . urlencode($fileRaw);

            $cards_output .= "
            <div class=\"karte-tile\">
              <a href=\"{$url}\">
                <img src=\"{$imageBase}{$fEsc}\"
                     class=\"karte-thumbnail\"
                     alt=\"{$tEsc}\"/>
                <div class=\"tile-title\">{$tEsc}</div>
              </a>
            </div>";
        }
        $cards_output .= '</div>';
    }

    eval("\$page = \"".$templates->get('karten_list')."\";");
    output_page($page);
    exit;
}

// ======================================================================
// 2) DETAIL‐VIEW → eine einzelne Karte + Hotspots
// ======================================================================
$imageRaw = basename(rawurldecode($mybb->input['image']));
$bn       = pathinfo($imageRaw, PATHINFO_FILENAME);

// Titel aus map_images oder Fallback
$titleRaw = $db->fetch_field(
    $db->simple_select('map_images','image_title',
        "image_file='".$db->escape_string($imageRaw)."'"
    ),
    'image_title'
) ?: ucwords(str_replace(['_','-'],' ',$bn));

add_breadcrumb($titleRaw, '');

$imageTitle = htmlspecialchars($titleRaw, ENT_QUOTES);
$imageUrl   = htmlspecialchars($imageBase.$imageRaw, ENT_QUOTES);

// Mapping Region → Standort-FID
$map   = [];
$qmap  = $db->simple_select('karte_region_map','region_value,fid_location');
while ($m = $db->fetch_array($qmap)) {
    $map[$m['region_value']] = (int)$m['fid_location'];
}

// Home-/Work-Zuweisungen
$defaultHome = $mybb->settings['icon_home'];
$defaultWork = $mybb->settings['icon_work'];

$homeAssign = [];
$qh = $db->simple_select('user_locations','area_id,uid',"type='home'");
while ($r = $db->fetch_array($qh)) {
    $homeAssign[(int)$r['area_id']][] = (int)$r['uid'];
}

$workAssign = [];
$active     = $cache->read('active_plugins');
$useJob     = !empty($active['active']) && in_array('jobliste',$active['active']);
if ($useJob) {
    $qj = $db->query("
      SELECT j.location AS area_id, u.uid
      FROM {$db->table_prefix}users u
      JOIN {$db->table_prefix}jobs j ON u.jid=j.jid
      WHERE j.type='work'
    ");
    while ($r = $db->fetch_array($qj)) {
        $workAssign[(int)$r['area_id']][] = (int)$r['uid'];
    }
} else {
    $qw = $db->simple_select('user_locations','area_id,uid',"type='work'");
    while ($r = $db->fetch_array($qw)) {
        $workAssign[(int)$r['area_id']][] = (int)$r['uid'];
    }
}

// Regionen aus map_areas laden
$q2 = $db->simple_select(
    'map_areas','*',
    "image_file='".$db->escape_string($imageRaw)."'"
);

$regions = [];
while ($area = $db->fetch_array($q2)) {
    $locVal    = $area['location_value'];
    $regionVal = $area['region_value'];
    $fidLoc    = $map[$regionVal] ?? 0;

    // Profilfelder / Userfetch
    $usersArr = [];
    if ($fidLoc && $locVal !== '') {
        $qu = $db->query("
          SELECT u.uid, u.username, u.usergroup, u.displaygroup
          FROM {$db->table_prefix}users u
          JOIN {$db->table_prefix}userfields uf ON uf.ufid=u.uid
          WHERE uf.fid{$fidLoc} = '".$db->escape_string($locVal)."'
          ORDER BY u.username
        ");
        while ($u = $db->fetch_array($qu)) {
            $usersArr[] = [
                'link' => 'member.php?action=profile&uid='.$u['uid'],
                'name' => format_name(
                    htmlspecialchars_uni($u['username']),
                    $u['usergroup'], $u['displaygroup']
                )
            ];
        }
    }

    // Home‐Liste
    $homeList = [];
    foreach ($homeAssign[$area['id']] ?? [] as $uid) {
        $u = get_user($uid);
        $homeList[] = [
            'link' => 'member.php?action=profile&uid='.$uid,
            'name' => format_name(
                htmlspecialchars_uni($u['username']),
                $u['usergroup'], $u['displaygroup']
            )
        ];
    }

    // Work‐Liste
    $workList = [];
    foreach ($workAssign[$area['id']] ?? [] as $uid) {
        $u = get_user($uid);
        $workList[] = [
            'link' => 'member.php?action=profile&uid='.$uid,
            'name' => format_name(
                htmlspecialchars_uni($u['username']),
                $u['usergroup'], $u['displaygroup']
            )
        ];
    }

    // Kapazitäten
    $capHome = (int)$area['cap_home_max'];
    $capWork = (int)$area['cap_work_max'];

    $regions[] = [
        'id'               => (int)$area['id'],
        'location'         => $locVal,
        'x'                => (int)$area['x'],
        'y'                => (int)$area['y'],
        'width'            => (int)$area['width'],
        'height'           => (int)$area['height'],
        'users'            => $usersArr,
        'category'         => $area['category'],
        'marker_title'     => $area['marker_title'],
        'initialUsers'     => $usersArr,
        'home'             => $homeList,
        'work'             => $workList,
        'icon_home_class'  => $area['icon_home_class'] ?: $defaultHome,
        'icon_work_class'  => $area['icon_work_class'] ?: $defaultWork,
		'icon_category'    => $area['icon_category'],
        'cap_home_max'     => $capHome,
        'cap_work_max'     => $capWork,
    ];
}

// ----------------------------------------------------------------------
// JSON für Frontend (jetzt wieder mit htmlspecialchars für Attribut-Sicherheit)
// ----------------------------------------------------------------------
$regions_json = json_encode($regions);
$regions_json = $plugins->run_hooks('karte_regions_json', $regions_json);
$regions_json = htmlspecialchars($regions_json, ENT_QUOTES);

// Button-Templates via Headerinclude injizieren
$homeTpl    = $templates->get('karten_button_home');
$workTpl    = $templates->get('karten_button_work');
$delHomeTpl = $templates->get('karten_button_delete_home');
$delWorkTpl = $templates->get('karten_button_delete_work');

$headerinclude .= '<script type="text/javascript">'
  . 'window.tplBtnHome='    . json_encode($homeTpl)    . ';'
  . 'window.tplBtnWork='    . json_encode($workTpl)    . ';'
  . 'window.tplBtnDelHome=' . json_encode($delHomeTpl) . ';'
  . 'window.tplBtnDelWork=' . json_encode($delWorkTpl) . ';'
.'</script>';

// Rendern
eval("\$page = \"".$templates->get('karten_detail')."\";");
output_page($page);
exit;
