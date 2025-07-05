<?php
/**
 * Plugin Name: Kartenverwaltung
 * Beschreibung: Dynamische Kartenverwaltung mit automatischer Standortzuordnung und Bild-Upload.
 * Autor:      noe
 * Version:     2.2.3 - nur teilweise getestet
 * Compatibility: 18*
 */

if(!defined('IN_MYBB')) {
    die('Direkter Zugriff nicht erlaubt.');
}

function karten_info() {
    return [
        'name'          => 'Kartenverwaltung',
        'description'   => 'Verwalte Kartenbereiche, ordne Standorte zu und lade eigene Kartenbilder hoch.',
        'author'        => 'noe',
        'version'       => '2.2.3'- nur teilweise getestet,
        'compatibility' => '18*'
    ];
}

function karten_activate() {
    global $db;
    require_once MYBB_ROOT . 'inc/functions.php';
    require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

    // 1) Tabelle karte_region_map
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

    // 2) Tabelle map_images
    if(!$db->table_exists('map_images')) {
        $db->write_query("
            CREATE TABLE ".TABLE_PREFIX."map_images (
              image_file  VARCHAR(255) NOT NULL,
              image_title VARCHAR(255) NOT NULL DEFAULT '',
              PRIMARY KEY (image_file)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }

    // 3) Tabelle user_locations
    if(!$db->table_exists('user_locations')) {
        $db->write_query("
            CREATE TABLE ".TABLE_PREFIX."user_locations (
              uid        INT UNSIGNED     NOT NULL,
              area_id    INT UNSIGNED     NOT NULL,
              type       ENUM('home','work') NOT NULL,
              set_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY(uid,type)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }

    // 4) Spalten in map_areas
    $cols = [
      "category VARCHAR(100) NOT NULL DEFAULT '' AFTER location_value",
      "marker_title VARCHAR(50) NOT NULL DEFAULT '' AFTER category",
      "allow_home TINYINT(1) NOT NULL DEFAULT 0 AFTER marker_title",
      "allow_work TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_home",
      "cap_home_max INT UNSIGNED NOT NULL DEFAULT 0 AFTER allow_work",
      "cap_work_max INT UNSIGNED NOT NULL DEFAULT 0 AFTER cap_home_max",
      "icon_home VARCHAR(255) NOT NULL DEFAULT '' AFTER cap_work_max",
      "icon_work VARCHAR(255) NOT NULL DEFAULT '' AFTER icon_home",
      "icon_category VARCHAR(255) NOT NULL DEFAULT '' AFTER icon_work"
    ];
    foreach($cols as $def) {
        $name = preg_split('/\s+/', $def, 2)[0];
        if(!$db->field_exists($name, 'map_areas')) {
            $db->write_query("ALTER TABLE ".TABLE_PREFIX."map_areas ADD {$def};");
        }
    }

    // 5) Settinggroup karten
    $gid = $db->fetch_field(
        $db->simple_select('settinggroups','gid',"name='karten'"),
        'gid'
    );
    if(!$gid) {
        $gid = $db->insert_query('settinggroups', [
            'name'        => 'karten',
            'title'       => 'Kartenverwaltung',
            'description' => 'Einstellungen für das Karten-Verwaltungs-Plugin',
            'disporder'   => 100,
            'isdefault'   => 0
        ]);
    }

    // 6) Settings anlegen
    $settings = [
      [
        'name'=>'karten_image_path','title'=>'Verzeichnis für Kartenbilder',
        'description'=>'Relativer Pfad zur Board-URL, z.B. images/karten','optionscode'=>'text','value'=>'images/karten/','disporder'=>1
      ],
      [
        'name'=>'karten_region_fid','title'=>'Profilfeld für Regionen (FID)',
        'description'=>'Trage hier die FID des Profilfelds ein, das die Regionen enthält.','optionscode'=>'text','value'=>'','disporder'=>2
      ],
      [
        'name'=>'karten_region_fids','title'=>'Profilfelder für Standorte (FIDs)',
        'description'=>"Komma- oder zeilengetrennt\nFIDs der Profilfelder mit Standortwerten",'optionscode'=>'textarea','value'=>'','disporder'=>3
      ],
      [
        'name'=>'karten_allowed_groups','title'=>'Gruppen mit Home/Work-Recht',
        'description'=>"Gruppen-IDs kommagetrennt",'optionscode'=>'textarea','value'=>'','disporder'=>4
      ],
      [
        'name'=>'icon_home','title'=>'Icon (Home)',
        'description'=>'FontAwesome-Klasse oder Bild-Pfad','optionscode'=>'text','value'=>'far fa-home','disporder'=>20
      ],
      [
        'name'=>'icon_work','title'=>'Icon (Work)',
        'description'=>'FontAwesome-Klasse oder Bild-Pfad','optionscode'=>'text','value'=>'fas fa-briefcase','disporder'=>21
      ],
      [
        'name'=>'karte_ext_categories','title'=>'Zusätzliche Kategorien',
        'description'=>'Je Kategorie in eine eigene Zeile','optionscode'=>'textarea','value'=>'','disporder'=>5
      ]
    ];
    foreach($settings as $s) {
        if(!$db->fetch_field($db->simple_select('settings','sid',"name='{$s['name']}'"),'sid')) {
            $s['gid'] = $gid;
            $db->insert_query('settings', $s);
        }
    }
    rebuild_settings();

    // 7) CSS in MyBB-Stylesheets (Theme-ID = 1 initial)
    $cssName = 'karten.css';
    $exists = $db->fetch_field(
        $db->simple_select('themestylesheets','sid',"name='{$cssName}'"),
        'sid'
    );
    if(!$exists) {
        $content = file_get_contents(MYBB_ROOT.'css/karten.css');
        $sid     = $db->insert_query('themestylesheets', [
            'name'         => $cssName,
            'tid'          => 1,
            'stylesheet'   => $db->escape_string($content),
            'cachefile'    => $cssName,
            'lastmodified' => TIME_NOW,
            'attachedto'   => ''
        ]);
        $db->update_query('themestylesheets', [
            'cachefile' => "css.php?stylesheet={$sid}"
        ], "sid={$sid}");
        $tids = $db->simple_select('themes','tid');
        while($t = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($t['tid']);
        }
    }
}

function karten_deactivate() {
    // Deaktivierung belässt alle Daten unangetastet
}

function karten_uninstall() {
    global $db;
    require_once MYBB_ROOT . 'inc/functions.php';
    require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

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

    // 4) Stylesheet löschen
    $db->delete_query('themestylesheets', "name='karten.css'");
    $tids = $db->simple_select('themes','tid');
    while($t = $db->fetch_array($tids)) {
        update_theme_stylesheet_list($t['tid']);
    }

    // 5) Templates löschen
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
}

function karten_admin_menu(&$sub_menu) {
    global $lang;
    $lang->load('karten');
    $sub_menu[] = ['id'=>'karten','title'=>$lang->karten,'link'=>'index.php?module=config-karten'];
    $sub_menu[] = ['id'=>'karten_map','title'=>'Region→Location Mapping','link'=>'index.php?module=config-karten_map'];
}

function karten_admin_action(&$actions) {
    $actions['karten']        = ['active'=>'karten',        'file'=>'karten/karten.php'];
    $actions['karten_add']    = ['active'=>'karten_add',    'file'=>'karten/karten_add.php'];
    $actions['karten_edit']   = ['active'=>'karten_edit',   'file'=>'karten/karten_edit.php'];
    $actions['karten_delete'] = ['active'=>'karten_delete','file'=>'karten/karten_delete.php'];
    $actions['karten_upload'] = ['active'=>'karten_upload','file'=>'karten/karten_upload.php'];
    $actions['karten_map']    = ['active'=>'karten_map',    'file'=>'karten/karten_mapping.php'];
}

$plugins->add_hook('admin_config_menu',           'karten_admin_menu');
$plugins->add_hook('admin_config_action_handler', 'karten_admin_action');
$plugins->add_hook('admin_formcontainer_end',     'kartenext_admin_fields_end');
$plugins->add_hook('admin_user_action_handler',   'kartenext_save_maparea');
$plugins->add_hook('karte_regions_json',          'kartenext_extend_regions');

/**
 * 1) Admin‐Add/Edit: zusätzliche Felder
 */
function kartenext_admin_fields_end(&$form) {
    global $mybb, $db, $settings;
    if(THIS_SCRIPT=='index.php'
       && $mybb->input['module']==='config-karten'
       && in_array($mybb->input['action'],['karten_add','karten_edit'],true)
    ) {
        $cats = preg_split('/\r?\n/',$settings['karte_ext_categories']);
        $q    = $db->simple_select('map_areas','DISTINCT category',"category!=''");
        while($r=$db->fetch_array($q)) {
            if(!in_array($r['category'],$cats,true)) {
                $cats[] = $r['category'];
            }
        }
        $opt = '';
        foreach($cats as $c) {
            $sel = ($mybb->input['category']===$c)?' selected':'';
            $opt .= "<option value=\"".htmlspecialchars($c)."\"$sel>".htmlspecialchars($c)."</option>";
        }
        $form->output_row('Kategorie',
            "<select name=\"category\">{$opt}</select><br><small>oder eigene eingeben:</small> "
            ."<input type=\"text\" name=\"category_custom\" size=\"20\" />",
            'category'
        );
        $form->output_row('Markierungstitel',
            '<input type="text" name="marker_title" size="30" '
            .'value="'.htmlspecialchars($mybb->input['marker_title']??'',ENT_QUOTES).'" />',
            'marker_title'
        );
    }
}

/**
 * 2) Admin‐Save: Kategorie+Marker weiterreichen
 */
function kartenext_save_maparea(&$action) {
    global $mybb, $_POST;
    if(THIS_SCRIPT==='index.php'
       && in_array($action,['do_karten_add','do_karten_edit'],true)
    ) {
        $cat = trim($mybb->input['category_custom'])!=='' 
             ? $mybb->input['category_custom'] 
             : $mybb->input['category'];
        $_POST['category']     = $cat;
        $_POST['marker_title'] = $mybb->input['marker_title'];
    }
}

/**
 * 3) Frontend JSON-Extension: Kategorie + Marker-Title
 */
function kartenext_extend_regions(&$json) {
    global $db, $imageRaw;
    $regions = json_decode($json,true);
    if(!is_array($regions)) return;
    $extras = [];
    $q = $db->simple_select('map_areas','location_value,category,marker_title',
                           "image_file='".$db->escape_string($imageRaw)."'");
    while($r=$db->fetch_array($q)) {
        $extras[$r['location_value']] = [
            'category'     => $r['category'],
            'marker_title' => $r['marker_title']
        ];
    }
    foreach($regions as &$reg) {
        $loc = $reg['location'];
        if(isset($extras[$loc])) {
            $reg['category']     = $extras[$loc]['category'];
            $reg['marker_title'] = $extras[$loc]['marker_title'];
        }
    }
    $json = json_encode($regions);
}
