<?php
/**
 * Plugin Name: Kartenverwaltung
 * Beschreibung: Dynamische Kartenverwaltung mit Benutzer-Standorten, Bild-Upload, Kategorien & Legende.
 * Autor:      noe
 * Version:     2.2.1 - NEU - NOCHT NICHT GETESTET
 * Compatibility: 18*
 */

if(!defined('IN_MYBB')) {
    die('Direkter Zugriff nicht erlaubt.');
}

function karten_info() {
    return [
        'name'          => 'Kartenverwaltung',
        'description'   => 'Interaktive Karten mit Regionen-/Standort-Hotspots, Crop-Tool, Icons und Nutzerzuordnung.',
        'author'        => 'noe',
        'version'       => '2.2.1' - NEU - NOCH NICHT GETESTET,
        'compatibility' => '18*'
    ];
}

function karten_activate() {
    global $db;
    require_once MYBB_ROOT.'inc/functions.php';
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    // 1) Tabellen anlegen
    if(!$db->table_exists('karte_region_map')) {
        $db->write_query("
            CREATE TABLE ".TABLE_PREFIX."karte_region_map (
              id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
              region_value VARCHAR(100)    NOT NULL,
              fid_location INT UNSIGNED    NOT NULL,
              PRIMARY KEY(id),
              UNIQUE KEY(region_value, fid_location)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }
    if(!$db->table_exists('map_images')) {
        $db->write_query("
            CREATE TABLE ".TABLE_PREFIX."map_images (
              image_file  VARCHAR(255) NOT NULL,
              image_title VARCHAR(255) NOT NULL DEFAULT '',
              PRIMARY KEY (image_file)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }
    if(!$db->table_exists('user_locations')) {
        $db->write_query("
            CREATE TABLE ".TABLE_PREFIX."user_locations (
              uid        INT UNSIGNED NOT NULL,
              area_id    INT UNSIGNED NOT NULL,
              type       ENUM('home','work') NOT NULL,
              set_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY(uid,type)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }
    // 2) Spalten in map_areas ergänzen
    $cols = [
      'category VARCHAR(100) NOT NULL DEFAULT \'\' AFTER location_value',
      'marker_title VARCHAR(50) NOT NULL DEFAULT \'\' AFTER category',
      'allow_home TINYINT(1) NOT NULL DEFAULT 0 AFTER marker_title',
      'allow_work TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_home',
      'cap_home_max INT UNSIGNED NOT NULL DEFAULT 0 AFTER allow_work',
      'cap_work_max INT UNSIGNED NOT NULL DEFAULT 0 AFTER cap_home_max',
      'icon_home VARCHAR(255) NOT NULL DEFAULT \'\' AFTER cap_work_max',
      'icon_work VARCHAR(255) NOT NULL DEFAULT \'\' AFTER icon_home',
      'icon_category VARCHAR(255) NOT NULL DEFAULT \'\' AFTER icon_work'
    ];
    foreach($cols as $colDef) {
        $col = preg_replace('/ .*/','', $colDef);
        if(!$db->field_exists($col,'map_areas')) {
            $db->write_query("ALTER TABLE ".TABLE_PREFIX."map_areas ADD {$colDef};");
        }
    }

    // 3) Einstellungen anlegen
    // Gruppe
    $gid = $db->fetch_field($db->simple_select('settinggroups','gid',"name='karten'"), 'gid');
    if(!$gid) {
        $gid = $db->insert_query('settinggroups', [
            'name'        => 'karten',
            'title'       => 'Kartenverwaltung',
            'description' => 'Einstellungen für das Karten-Plugin',
            'disporder'   => 100,
            'isdefault'   => 0
        ]);
    }
    // Felder
    $settings = [
      ['name'=>'karten_image_path','title'=>'Verzeichnis für Kartenbilder',
       'description'=>'Pfad relativ zur Board-URL, z.B. images/karten','optionscode'=>'text','value'=>'images/karten/','disporder'=>1],
      ['name'=>'karten_region_fid','title'=>'Profilfeld für Regionen (FID)',
       'description'=>'FID des Profilfeldes mit Regionswerten','optionscode'=>'text','value'=>'','disporder'=>2],
      ['name'=>'karten_region_fids','title'=>'Profilfelder für Standorte (FIDs)',
       'description'=>"Komma/zeilengetrennt\ndeine Standort-Profile","optionscode'=>'textarea','value'=>'','disporder'=>3],
      ['name'=>'karten_allowed_groups','title'=>'Gruppen mit Home/Work-Recht',
       'description'=>"Gruppen-IDs kommagetrennt","optionscode'=>'textarea','value'=>'','disporder'=>4],
      ['name'=>'icon_home','title'=>'Icon (Home)',
       'description'=>'FontAwesome-Klasse oder Pfad','optionscode'=>'text','value'=>'far fa-home','disporder'=>20],
      ['name'=>'icon_work','title'=>'Icon (Work)',
       'description'=>'FontAwesome-Klasse oder Pfad','optionscode'=>'text','value'=>'fas fa-briefcase','disporder'=>21],
      ['name'=>'karte_ext_categories','title'=>'Zusätzliche Kategorien',
       'description'=>'Je Kategorie eine Zeile','optionscode'=>'textarea','value'=>'','disporder'=>5]
    ];
    foreach($settings as $s) {
        if(!$db->fetch_field($db->simple_select('settings','sid',"name='{$s['name']}'"),'sid')) {
            $db->insert_query('settings', array_merge($s, ['gid'=>$gid]));
        }
    }
    rebuild_settings();

    // 4) Templates anlegen
    $tpl_group = 'karten';
    $new_templates = [
      // key ⇒ [title, template content, disporder]
      'karten_list'      => ['Karten-Übersicht', file_get_contents(MYBB_ROOT.'inc/plugins/karten_templates/karten_list.tpl'), 1],
      'karten_detail'    => ['Karten-Detail',     file_get_contents(MYBB_ROOT.'inc/plugins/karten_templates/karten_detail.tpl'),2],
      'karten_button_home'=>['Button Wohnort',   file_get_contents(MYBB_ROOT.'inc/plugins/karten_templates/karten_button_home.tpl'),3],
      'karten_button_work'=>['Button Arbeit',    file_get_contents(MYBB_ROOT.'inc/plugins/karten_templates/karten_button_work.tpl'),4],
      'karten_button_delete_home'=>['Löschen Wohnort',file_get_contents(MYBB_ROOT.'inc/plugins/karten_templates/karten_button_delete_home.tpl'),5],
      'karten_button_delete_work'=>['Löschen Arbeit', file_get_contents(MYBB_ROOT.'inc/plugins/karten_templates/karten_button_delete_work.tpl'),6],
      'karten_admin_title_row'   =>['Admin Titel-Row', file_get_contents(MYBB_ROOT.'inc/plugins/karten_templates/karten_admin_title_row.tpl'),7]
    ];
    foreach($new_templates as $title => $data) {
        list($titleText,$template,$order) = $data;
        find_replace_templatesets(
            "global_header", // dummy – wird in plugin aufgerufen
            '', ''
        );
        // insert if not exists
        if(!db_fetch_field($db->simple_select('templates','COUNT(*) AS cnt',"title='{$title}'"),'cnt')) {
            $db->insert_query('templates', [
                'title'     => $title,
                'template'  => $template,
                'sid'       => -1,
                'version'   => '1801',
                'dateline'  => TIME_NOW
            ]);
        }
    }
    build_templates();

    // 5) CSS-Datei anlegen
    $cssDir = MYBB_ROOT.'css/';
    $cssFile = $cssDir.'karten.css';
    if(!file_exists($cssFile)) {
        file_put_contents($cssFile, file_get_contents(MYBB_ROOT.'inc/plugins/karten_templates/karten.css'));
    }
}

function karten_deactivate() {
    // leer – keine Löschung bei Deaktivierung
}

function karten_uninstall() {
    global $db;
    require_once MYBB_ROOT.'inc/functions.php';
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    // 1) Tabellen löschen
    foreach(['karte_region_map','map_images','user_locations'] as $tbl) {
        if($db->table_exists($tbl)) {
            $db->write_query("DROP TABLE ".TABLE_PREFIX.$tbl);
        }
    }
    // 2) Spalten in map_areas entfernen
    foreach(['category','marker_title','allow_home','allow_work','cap_home_max','cap_work_max','icon_home','icon_work','icon_category'] as $col) {
        if($db->field_exists($col,'map_areas')) {
            $db->write_query("ALTER TABLE ".TABLE_PREFIX."map_areas DROP COLUMN {$col}");
        }
    }
    // 3) Settings & Gruppe löschen
    $db->delete_query('settings', "name IN (
      'karten_image_path','karten_region_fid','karten_region_fids',
      'karten_allowed_groups','icon_home','icon_work','karte_ext_categories'
    )");
    $db->delete_query('settinggroups', "name='karten'");
    rebuild_settings();
    // 4) Templates löschen
    $titles = [
      'karten_list','karten_detail',
      'karten_button_home','karten_button_work',
      'karten_button_delete_home','karten_button_delete_work',
      'karten_admin_title_row'
    ];
    foreach($titles as $t) {
        $db->delete_query('templates', "title='{$t}'");
    }
    build_templates();
    // 5) CSS-Datei entfernen
    $cssFile = MYBB_ROOT.'css/karten.css';
    if(file_exists($cssFile)) {
        @unlink($cssFile);
    }
}

$plugins->add_hook('admin_config_menu',           'karten_admin_menu');
$plugins->add_hook('admin_config_action_handler', 'karten_admin_action');

function karten_admin_menu(&$sub_menu) {
    global $lang;
    $lang->load('karten');
    $sub_menu[] = ['id'=>'karten','title'=>$lang->karten,'link'=>'index.php?module=config-karten'];
    $sub_menu[] = ['id'=>'karten_map','title'=>'Region→Location Mapping','link'=>'index.php?module=config-karten_map'];
}

function karten_admin_action(&$actions) {
    $actions['karten']        = ['active'=>'karten','file'=>'karten/karten.php'];
    $actions['karten_add']    = ['active'=>'karten_add','file'=>'karten/karten_add.php'];
    $actions['karten_edit']   = ['active'=>'karten_edit','file'=>'karten/karten_edit.php'];
    $actions['karten_delete'] = ['active'=>'karten_delete','file'=>'karten/karten_delete.php'];
    $actions['karten_upload'] = ['active'=>'karten_upload','file'=>'karten/karten_upload.php'];
    $actions['karten_map']    = ['active'=>'karten_map','file'=>'karten/karten_mapping.php'];
}
