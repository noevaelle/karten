<?php
if(!defined('IN_MYBB') || !defined('IN_ADMINCP')) die('Kein Zugriff.');

global $db, $mybb, $page, $lang;
$lang->load('karten');

// Einstellungen aus dem ACP-Setting
$fid_region    = (int)$mybb->settings['karten_region_fid'];           // Regions-Feld (57)
$fid_locations = array_map('intval', preg_split('/[\r\n,]+/', $mybb->settings['karten_region_fids'])
); // erlaubte Standort-Felder

// Request-Parameter
$action     = $mybb->get_input('action', '');
$sort_dir   = strtoupper($mybb->get_input('sort_dir','ASC'))==='DESC'?'DESC':'ASC';
$toggle_dir = $sort_dir==='ASC'?'DESC':'ASC';

// FID-Filter, Default = all
$fid_filter = $mybb->get_input('fid_filter', '');
if($fid_filter===''||$fid_filter===null) {
    $fid_filter = 'all';
} elseif($fid_filter!=='all') {
    $fid_filter = (int)$fid_filter;
}

// Sub‐Tabs
$sub_tabs = [
    'list'=>['title'=>'Übersicht',          'link'=>'index.php?module=config-karten_map'],
    'add' =>['title'=>'Mapping hinzufügen', 'link'=>'index.php?module=config-karten_map&amp;action=add']
];

//
// 1) Standort-Werte (Untermenü „locations“)
//
if($action==='locations') {
    $fid = (int)$mybb->get_input('fid');
    // Label der Region
    $region = $db->fetch_field(
        $db->simple_select('karte_region_map','region_value',"fid_location={$fid}",['limit'=>1]),
        'region_value'
    );
    // Profilfeld-Typ auslesen
    $raw = $db->fetch_field(
        $db->simple_select('profilefields','type',"fid={$fid}"),
        'type'
    );
    $vals = array_filter(array_map('trim', preg_split('/\r?\n/', $raw)));
    array_shift($vals); // Label entfernen
    // Sortierung
    natcasesort($vals);
    if($sort_dir==='DESC') {
        $vals = array_reverse($vals, true);
    }
    // Breadcrumbs + Header
    $page->add_breadcrumb_item('Karten verwalten','index.php?module=config-karten');
    $page->add_breadcrumb_item('Mapping verwalten','index.php?module=config-karten_map');
    $page->add_breadcrumb_item($region,'');
    $page->output_header("Standort-Werte für „{$region}“");
    // Filter-Input
    echo '<div style="margin-bottom:10px"><input type="text" id="filter" placeholder="Filter…"></div>';
    // Tabelle
    echo '<table id="vals_table" class="tborder" cellpadding="5" cellspacing="1" width="100%">';
    echo '<thead><tr>'
       ."<th><a href=\"index.php?module=config-karten_map&amp;action=locations&amp;fid={$fid}&amp;sort_dir={$toggle_dir}\">"
       ."Wert".($sort_dir==='ASC'?' ↑':' ↓')."</a></th>"
       ."<th>Aktion</th></tr></thead><tbody>";
    foreach($vals as $v) {
        $e = htmlspecialchars_uni($v);
        $uR = "index.php?module=config-karten_map&amp;action=rename_val&amp;fid={$fid}&amp;val=".urlencode($v);
        $uD = "index.php?module=config-karten_map&amp;action=delete_val&amp;fid={$fid}&amp;val=".urlencode($v);
        echo "<tr><td>{$e}</td><td><a href=\"{$uR}\">Umbenennen</a> • <a href=\"{$uD}\">Löschen</a></td></tr>";
    }
    echo '</tbody></table>';
    // JS-Filter
    echo <<<JS
<script>
jQuery(function($){
  $('#filter').on('keyup', function(){
    var s=$(this).val().toLowerCase();
    $('#vals_table tbody tr').each(function(){
      $(this).toggle($(this).find('td').first().text().toLowerCase().indexOf(s)!==-1);
    });
  });
});
</script>
JS;
    $page->output_footer();
    exit;
}

//
// 2) Umbenennen / Löschen Standort-Wert (rename_val, delete_val)
// (Dazu sind keine Änderungen nötig, das war korrekt.)
//

//
// 3) Mapping hinzufügen
//
if($action==='add') {
    if($mybb->request_method==='post') {
        // Security
        if($mybb->get_input('my_post_key')!==$mybb->post_code) {
            flash_message('Ungültiger Autorisierungscode','error');
            admin_redirect('index.php?module=config-karten_map');
        }
        $region = trim($mybb->get_input('region_value'));
        $fid_loc = (int)$mybb->get_input('fid_location');
        // nur erlaubte FIDs
        if(!in_array($fid_loc,$fid_locations,true)) {
            $fid_loc = $fid_locations[0];
        }
        if($region==='') {
            flash_message('Region-Wert darf nicht leer sein','error');
            admin_redirect('index.php?module=config-karten_map&amp;action=add');
        }
        // Insert map
        $esc = $db->escape_string($region);
        $db->query("
            INSERT INTO `{$db->table_prefix}karte_region_map`
               (`region_value`,`fid_location`)
            VALUES('{$esc}',{$fid_loc})
            ON DUPLICATE KEY UPDATE `region_value`=`region_value`
        ");
        // Jetzt das **Regions-Profilfeld** (#57) erweitern:
        $rawR   = $db->fetch_field(
          $db->simple_select('profilefields','type',"fid={$fid_region}"),
          'type'
        );
        $linesR = array_filter(preg_split('/\r?\n/', $rawR),'strlen');
        if(!in_array($region, $linesR, true)) {
            $linesR[] = $region;
            $db->update_query('profilefields',
              ['type'=>implode("\n",$linesR)],
              "fid={$fid_region}"
            );
        }
        flash_message('Mapping hinzugefügt','success');
        admin_redirect('index.php?module=config-karten_map');
    }
    // Form-Header
    $page->add_breadcrumb_item('Karten verwalten','index.php?module=config-karten');
    $page->add_breadcrumb_item('Mapping verwalten','index.php?module=config-karten_map');
    $page->add_breadcrumb_item('Mapping hinzufügen','');
    $page->output_header('Mapping hinzufügen');
    $page->output_nav_tabs($sub_tabs,'add');
    // Formular
    echo '<form method="post" action="index.php?module=config-karten_map&amp;action=add">';
    echo '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code,ENT_QUOTES).'" />';
    echo '<table class="tborder" cellpadding="5" cellspacing="1" width="100%">';
    // Region-Wert
    echo '<tr><td class="trow1">Region-Wert</td><td class="trow2">'
       .'<input type="text" name="region_value" size="30" /></td></tr>';
    // Dropdown nur erlaubter Standort-Felder
    echo '<tr><td class="trow1">FID des Standort-Felds</td><td class="trow2">';
    echo '<select name="fid_location">';
    $pf = $db->simple_select(
      'profilefields','fid,name',
      'fid IN('.implode(',',$fid_locations).')'
    );
    while($f = $db->fetch_array($pf)) {
        $sel = ($f['fid']===$fid_region)?' selected':'';
        echo '<option value="'.$f['fid'].'"'.$sel.'>'
           .htmlspecialchars_uni($f['name']).' (FID '.$f['fid'].')</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><td class="trow2" colspan="2" align="center">'
       .'<input type="submit" class="button" value="Speichern" /></td></tr>';
    echo '</table></form>';
    $page->output_footer();
    exit;
}

//
// 4) Mapping bearbeiten / löschen
// ──────────────────── ACTION: Mapping bearbeiten ────────────────────────
if($action === 'edit') {
    $id   = (int)$mybb->get_input('id');
    $row  = $db->fetch_array($db->simple_select('karte_region_map','*',"id={$id}"));
    if(!$row) {
        flash_message('Ungültige Mapping-ID','error');
        admin_redirect('index.php?module=config-karten_map');
    }
    $oldVal = $row['region_value'];
    $oldFid = $row['fid_location'];

    if($mybb->request_method === 'post') {
        if($mybb->get_input('my_post_key') !== $mybb->post_code) {
            flash_message('Ungültiger Code','error');
            admin_redirect('index.php?module=config-karten_map');
        }
        $newVal = trim($mybb->get_input('region_value'));
        $newFid = (int)$mybb->get_input('fid_location');
        if($newVal === '') {
            flash_message('Region-Wert darf nicht leer sein','error');
            admin_redirect("index.php?module=config-karten_map&amp;action=edit&amp;id={$id}");
        }
        // 1) Mapping updaten
        $db->update_query('karte_region_map', [
            'region_value' => $db->escape_string($newVal),
            'fid_location' => $newFid
        ], "id={$id}");
        // 2) altes Profilfeld säubern
        $rawO   = $db->fetch_field($db->simple_select('profilefields','type',"fid={$oldFid}"), 'type');
        $linesO = array_filter(preg_split('/\r?\n/', $rawO), 'strlen');
        $linesO = array_filter($linesO, function($l) use($oldVal){
            return trim($l) !== $oldVal;
        });
        $db->update_query('profilefields', ['type'=>implode("\n",$linesO)], "fid={$oldFid}");
        // 3) neues Profilfeld ergänzen
        $rawN   = $db->fetch_field($db->simple_select('profilefields','type',"fid={$newFid}"), 'type');
        $linesN = array_filter(preg_split('/\r?\n/', $rawN), 'strlen');
        if(!in_array($newVal, $linesN, true)) {
            $linesN[] = $newVal;
            $db->update_query('profilefields', ['type'=>implode("\n",$linesN)], "fid={$newFid}");
        }
        flash_message('Mapping aktualisiert','success');
        admin_redirect('index.php?module=config-karten_map');
        exit;
    }
    // Formular ausgeben
    $page->add_breadcrumb_item('Karten verwalten','index.php?module=config-karten');
    $page->add_breadcrumb_item('Mapping verwalten','index.php?module=config-karten_map');
    $page->add_breadcrumb_item('Mapping bearbeiten','');
    $page->output_header('Mapping bearbeiten');
    $page->output_nav_tabs($sub_tabs, 'add');

    echo '<form method="post" action="index.php?module=config-karten_map&amp;action=edit&amp;id='.$id.'">';
    echo '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code,ENT_QUOTES).'" />';
    echo '<table class="tborder" cellpadding="5" cellspacing="1" width="100%">';
    // Region-Wert
    echo '<tr><td class="trow1">Region-Wert</td><td class="trow2">'
       .'<input type="text" name="region_value" size="30" value="'.htmlspecialchars_uni($oldVal).'" /></td></tr>';
    // Dropdown erlaubter FIDs
    echo '<tr><td class="trow1">FID des Standort-Felds</td><td class="trow2"><select name="fid_location">';
    foreach($fid_locations as $fl) {
        $name = $db->fetch_field(
            $db->simple_select('profilefields','name',"fid={$fl}"),
            'name'
        );
        $sel = $fl === $oldFid ? ' selected' : '';
        echo '<option value="'.$fl.'"'.$sel.'>'.htmlspecialchars_uni($name).' (FID '.$fl.')</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><td class="trow2" colspan="2" align="center">'
       .'<input type="submit" class="button" value="Speichern" /></td></tr>';
    echo '</table></form>';
    $page->output_footer();
    exit;
}

// ──────────────────── ACTION: Mapping löschen ────────────────────────────
if($action === 'delete') {
    $id = (int)$mybb->get_input('id');
    $row = $db->fetch_array($db->simple_select('karte_region_map','*',"id={$id}"));
    if(!$row) {
        flash_message('Ungültige Mapping-ID','error');
        admin_redirect('index.php?module=config-karten_map');
    }
    $reg  = $row['region_value'];
    $fidO = $row['fid_location'];

    if(!$mybb->get_input('confirm_delete')) {
        $page->add_breadcrumb_item('Karten verwalten','index.php?module=config-karten');
        $page->add_breadcrumb_item('Mapping verwalten','index.php?module=config-karten_map');
        $page->add_breadcrumb_item('Mapping löschen','');
        $page->output_header('Mapping löschen');
        echo '<form method="post" action="index.php?module=config-karten_map&amp;action=delete&amp;id='.$id.'">';
        echo '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code,ENT_QUOTES).'" />';
        echo '<div class="confirm_message"><p>Soll das Mapping „'.htmlspecialchars_uni($reg).'“ wirklich gelöscht werden?</p>';
        echo '<div class="buttons"><input type="submit" name="confirm_delete" class="button" value="Ja, löschen" /> '
           .'<a href="index.php?module=config-karten_map" class="button">Abbrechen</a></div></div>';
        echo '</form>';
        $page->output_footer();
        exit;
    }
    // tatsächliches Löschen
    $db->delete_query('karte_region_map', "id={$id}");
    // Profilfeld bereinigen
    $raw   = $db->fetch_field($db->simple_select('profilefields','type',"fid={$fidO}"), 'type');
    $lines = array_filter(preg_split('/\r?\n/',$raw),'strlen');
    $label = array_shift($lines);
    $lines = array_filter($lines, function($l) use($reg){ return trim($l)!==$reg; });
    array_unshift($lines, $label);
    $db->update_query('profilefields',['type'=>implode("\n",$lines)],"fid={$fidO}");
    flash_message('Mapping gelöscht','success');
    admin_redirect('index.php?module=config-karten_map');
    exit;
}


//
// 5) Übersicht, Filter und Auto-Sync Region → Mapping
//
$page->add_breadcrumb_item('Karten verwalten','index.php?module=config-karten');
$page->add_breadcrumb_item('Mapping verwalten','index.php?module=config-karten_map');
$page->output_header('Region → Location Mapping');
$page->output_nav_tabs($sub_tabs,'list');

// Auto-Sync aller Regionen aus Profilfeld (#57)
$rawR   = $db->fetch_field(
    $db->simple_select('profilefields','type',"fid={$fid_region}"),
    'type'
);
$linesR = array_filter(array_map('trim', preg_split('/\r?\n/',$rawR)));
array_shift($linesR);
foreach($linesR as $valR) {
    $escR = $db->escape_string($valR);
    $db->query("
      INSERT INTO `{$db->table_prefix}karte_region_map`
        (`region_value`,`fid_location`)
      VALUES('{$escR}',{$fid_region})
      ON DUPLICATE KEY UPDATE `region_value`=`region_value`
    ");
}
// Cleanup verwaister Einträge
$qDel = $db->simple_select('karte_region_map','region_value',
    "fid_location={$fid_region}"
);
while($rDel = $db->fetch_array($qDel)) {
    if(!in_array($rDel['region_value'],$linesR,true)) {
        $db->delete_query('karte_region_map',
            "fid_location={$fid_region} AND region_value='"
            .$db->escape_string($rDel['region_value'])."'"
        );
    }
}

// Filter-Form (Default = all wird jetzt korrekt vorausgewählt)
echo '<form method="get" action="index.php" style="margin-bottom:10px;">';
echo '<input type="hidden" name="module" value="config-karten_map" />';
echo '<input type="hidden" name="sort_dir" value="'.htmlspecialchars($sort_dir,ENT_QUOTES).'" />';
echo '<select name="fid_filter">';
echo '<option value="all"'.($fid_filter==='all'?' selected':'').'>Alle FIDs</option>';
$fids2 = $db->simple_select('karte_region_map','DISTINCT fid_location');
while($f2=$db->fetch_array($fids2)) {
    $v2   = (int)$f2['fid_location'];
    $sel2 = ($fid_filter===$v2?' selected':'');
    echo '<option value="'.$v2.'"'.$sel2.'>FID '.$v2.'</option>';
}
echo '</select> <input type="submit" class="button" value="Filtern" />';
echo '</form>';

// Abfrage mit optionalem WHERE
$where = ($fid_filter==='all')?'':'fid_location='.(int)$fid_filter;
$qList = $db->simple_select('karte_region_map','*',$where,
         ['order_by'=>'region_value','order_dir'=>$sort_dir]);

// Ausgabe
echo '<table class="tborder" cellpadding="5" cellspacing="1" width="100%">';
echo '<tr>'
   .'<th><a href="index.php?module=config-karten_map&amp;fid_filter='.urlencode($fid_filter)
     .'&amp;sort_dir='.$toggle.'">Region-Wert'.($sort_dir==='ASC'?' ↑':' ↓').'</a></th>'
   .'<th>FID</th><th>Aktionen</th></tr>';
while($rL=$db->fetch_array($qList)) {
    $locs = 'index.php?module=config-karten_map&amp;action=locations&amp;fid='.$rL['fid_location'];
    $edit = 'index.php?module=config-karten_map&amp;action=edit&amp;id='.$rL['id'];
    $del  = 'index.php?module=config-karten_map&amp;action=delete&amp;id='.$rL['id'];
    echo '<tr><td>'.htmlspecialchars_uni($rL['region_value']).'</td>'
       .'<td>'.(int)$rL['fid_location'].'</td>'
       .'<td><a href="'.$locs.'">Standort-Werte</a> • '
       .'<a href="'.$edit.'">Bearbeiten</a> • <a href="'.$del.'">Löschen</a></td></tr>';
}
echo '</table>';

$page->output_footer();
