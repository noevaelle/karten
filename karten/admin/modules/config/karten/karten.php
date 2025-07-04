<?php
if(!defined('IN_MYBB') || !defined('IN_ADMINCP')) die('Kein Zugriff.');

global $db, $mybb, $page, $lang;
$lang->load('karten');

// ganz oben
global $plugins;
$plugins->add_hook('admin_config_action_handler',   'karten_admin_action');
$plugins->add_hook('admin_config_menu',             'karten_admin_menu');
$plugins->add_hook('admin_load',                    'karten_admin_hooks');

function karten_admin_hooks()
{
    global $page, $mybb, $db;
    if($mybb->input['module']=='config-karten')
    {
        // im Bearbeiten-Formular
        $page->add_breadcrumb_item('Titel', 'javascript:;');
        $page->extra_footer .= '<script>
          // optional: JS-Showing für "Titel"
        </script>';
        // Hook für Feld einfügen
        global $templates;
        $title = $db->fetch_field(
          $db->simple_select('map_images','image_title',
            "image_file='".$db->escape_string($mybb->input['image'])."'"
          ), 'image_title'
        );
        eval("\$title_row = \"".$templates->get('karten_admin_title_row')."\";");
        $templates->cache['karten_admin_edit'] =
          str_replace('{$submit_row}','{$title_row}{$submit_row}',$templates->cache['karten_admin_edit']);
    }
}


// 1) Breadcrumb + Header
$page->add_breadcrumb_item('Karten verwalten','index.php?module=config-karten');
$page->output_header('Karten verwalten');

// 2) Sub-Tabs
$sub_tabs = [
    'übersicht'  => ['title'=>'Übersicht',       'link'=>'index.php?module=config-karten'],
    'hinzufügen' => ['title'=>'Neuen Bereich',   'link'=>'index.php?module=config-karten_add'],
    'upload'     => ['title'=>'Karte hochladen', 'link'=>'index.php?module=config-karten_upload'],
];
$page->output_nav_tabs($sub_tabs, 'übersicht');

// 3) Bild-Pfad
$imgUrl = rtrim($mybb->settings['bburl'], '/') 
        .'/'. trim($mybb->settings['karten_image_path'], '/') .'/';

// 4) Parameter ?image=
$image = isset($_GET['image']) ? trim($_GET['image']) : '';

// 5) Übersicht aller Kartenbilder
if($image === '') {
    echo '<h2>Verfügbare Karten</h2>';
    $sql = "
        SELECT image_file, COUNT(*) AS cnt
        FROM {$db->table_prefix}map_areas
        GROUP BY image_file
        ORDER BY cnt DESC, image_file ASC
    ";
    $query = $db->query($sql);

    echo '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
    echo '<tr><th>Vorschau</th><th>Dateiname</th><th>Anzahl Bereiche</th><th>Aktion</th></tr>';

    while($row = $db->fetch_array($query)) {
        $file  = htmlspecialchars($row['image_file'], ENT_QUOTES);
        $cnt   = (int)$row['cnt'];
        $thumb = "<img src=\"{$imgUrl}{$file}\" style=\"max-width:80px;\" />";
        $link  = 'index.php?module=config-karten&amp;image='.urlencode($row['image_file']);

        echo "<tr>
                <td>{$thumb}</td>
                <td><a href=\"{$link}\">{$file}</a></td>
                <td style=\"text-align:center;\">{$cnt}</td>
                <td><a href=\"{$link}\">Anzeigen</a></td>
              </tr>";
    }
    echo '</table>';
}
// 6) Detail-Ansicht für ein Bild
else {
    $esc = $db->escape_string($image);
    echo '<h2>Kartenbereiche für „'.htmlspecialchars($image,ENT_QUOTES).'“</h2>';
    echo '<div style="margin-bottom:10px;">'
        . '<a href="index.php?module=config-karten" class="button">← Zurück zur Übersicht</a>'
        . '</div>';

    // Bereiche laden
    $query = $db->simple_select(
        'map_areas','*',"image_file='{$esc}'",
        ['order_by'=>'id','order_dir'=>'DESC']
    );

    $rows = [];
    while($r = $db->fetch_array($query)) {
        $rows[] = $r;
    }

    if(empty($rows)) {
        echo '<p>Für dieses Bild wurden keine Bereiche gefunden.</p>';
    } else {
        echo '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
        // Header mit Kategorie & Markierung
        echo '<tr>'
           . '<th>ID</th>'
           . '<th>Region</th>'
           . '<th>Standort</th>'
           . '<th>Kategorie</th>'
           . '<th>Markierung</th>'
           . '<th>Bild</th>'
           . '<th>Aktionen</th>'
           . '</tr>';

        foreach($rows as $r) {
            $id    = (int)$r['id'];
            $reg   = htmlspecialchars($r['region_value'],   ENT_QUOTES);
            $loc   = htmlspecialchars($r['location_value'], ENT_QUOTES);
            $cat   = htmlspecialchars($r['category'],       ENT_QUOTES);
            $mark  = htmlspecialchars($r['marker_title'],   ENT_QUOTES);
            $img   = htmlspecialchars($r['image_file'],     ENT_QUOTES);
            $edit  = "index.php?module=config-karten_edit&amp;id={$id}";
            $del   = "index.php?module=config-karten_delete&amp;id={$id}";

            echo "<tr>
                    <td>{$id}</td>
                    <td>{$reg}</td>
                    <td>{$loc}</td>
                    <td>{$cat}</td>
                    <td>{$mark}</td>
                    <td>{$img}</td>
                    <td><a href=\"{$edit}\">Bearbeiten</a> • <a href=\"{$del}\">Löschen</a></td>
                  </tr>";
        }
        echo '</table>';
    }
}

$page->output_footer();
