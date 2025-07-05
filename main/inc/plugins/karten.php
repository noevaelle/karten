<?php
/**
 * Plugin Name: Kartenverwaltung
 * Beschreibung: Dynamische Kartenverwaltung mit automatischer Standortzuordnung und Bild-Upload.
 * Autor:      noe
 * Version:     2.2
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
        'version'       => '2.2',
        'compatibility' => '18*'
    ];
}

function karten_activate() {
    global $db;
    require_once MYBB_ROOT . 'inc/functions.php';

    // 1) karte_region_map anlegen
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

    // 2) map_images anlegen
    if(!$db->table_exists('map_images')) {
        $db->write_query("
            CREATE TABLE ".TABLE_PREFIX."map_images (
              image_file  VARCHAR(255) NOT NULL,
              image_title VARCHAR(255) NOT NULL DEFAULT '',
              PRIMARY KEY (image_file)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }

    // 3) Spalten in map_areas ergänzen
    if(!$db->field_exists('category', 'map_areas')) {
        $db->write_query("
            ALTER TABLE ".TABLE_PREFIX."map_areas
            ADD category VARCHAR(100) NOT NULL DEFAULT '' AFTER location_value
        ");
    }
    if(!$db->field_exists('marker_title', 'map_areas')) {
        $db->write_query("
            ALTER TABLE ".TABLE_PREFIX."map_areas
            ADD marker_title VARCHAR(50) NOT NULL DEFAULT '' AFTER category
        ");
    }

    // 4) Settings‐Gruppe "karten" anlegen (falls noch nicht existiert)
    $gid = $db->fetch_field(
        $db->simple_select('settinggroups','gid',"name='karten'"),
        'gid'
    );
    if(!$gid) {
        $gid = $db->insert_query('settinggroups', [
            'name'        => 'karten',
            'title'       => 'Kartenverwaltung',
            'description' => 'Einstellungen für das Kartenverwaltungs-Plugin',
            'disporder'   => 100,
            'isdefault'   => 0
        ]);
    }

	
	
    // 5) Bild-Pfad Setting
    if(!$db->fetch_field($db->simple_select('settings','sid',"name='karten_image_path'"),'sid')) {
        $db->insert_query('settings', [
            'name'        => 'karten_image_path',
            'title'       => 'Verzeichnis für Kartenbilder',
            'description' => 'Pfad relativ zur Board-URL, in dem Kartengrafiken liegen.',
            'optionscode' => 'text',
            'value'       => 'images/aml_karten/',
            'disporder'   => 1,
            'gid'         => $gid
        ]);
    }

    // 6) Freitext: Profilfeld-FID für Regionen
    if(!$db->fetch_field($db->simple_select('settings','sid',"name='karten_region_fid'"),'sid')) {
        $db->insert_query('settings', [
            'name'        => 'karten_region_fid',
            'title'       => 'Profilfeld für Regionen (FID)',
            'description' => 'Trage hier die FID des Profilfelds ein, das die Regionen enthält.',
            'optionscode' => 'text',
            'value'       => '',
            'disporder'   => 2,
            'gid'         => $gid
        ]);
    }

    // 7) Mehrzeiliges Textarea: Profilfeld-FIDs für Standorte
    if(!$db->fetch_field($db->simple_select('settings','sid',"name='karten_region_fids'"),'sid')) {
        $db->insert_query('settings', [
            'name'        => 'karten_region_fids',
            'title'       => 'Profilfelder für Standorte (FIDs)',
            'description' => "Trage hier die FIDs aller Profilfelder ein, die als Standorte dienen.\nKomma- oder zeilengetrennt.",
            'optionscode' => 'textarea',
            'value'       => '',
            'disporder'   => 3,
            'gid'         => $gid
        ]);
    }
	
	// 8) Zusätzliche Attribute in map_areas
	if(!$db->field_exists('allow_home', 'map_areas')) {
		$db->write_query("
			ALTER TABLE ".TABLE_PREFIX."map_areas
			ADD allow_home    TINYINT(1)  NOT NULL DEFAULT 0 AFTER marker_title,
			ADD allow_work    TINYINT(1)  NOT NULL DEFAULT 0,
			ADD cap_home_max  INT UNSIGNED NOT NULL DEFAULT 0,
			ADD cap_work_max  INT UNSIGNED NOT NULL DEFAULT 0,
			ADD icon_home     VARCHAR(255) NOT NULL DEFAULT '',
			ADD icon_work     VARCHAR(255) NOT NULL DEFAULT ''
		");
	}
	
	// 8.1) Erlaubte Usergroups (Freitext oder textarea)
	if(!$db->fetch_field($db->simple_select('settings','sid',"name='karten_allowed_groups'"),'sid')) {
		$db->insert_query('settings', [
			'name'        => 'karten_allowed_groups',
			'title'       => 'Usergruppen mit Wohn-/Arbeitswahl',
			'description' => "Liste der Gruppen-IDs, die ihren Home/Work-Ort wählen dürfen.\nKomma- oder zeilengetrennt.",
			'optionscode' => 'textarea',
			'value'       => '',
			'disporder'   => 4,
			'gid'         => $gid
		]);
	}

	// 9) Tabelle für User‐Wahl: home/work
	if(!$db->table_exists('user_locations')) {
		$db->write_query("
		CREATE TABLE ".TABLE_PREFIX."user_locations (
		  uid     INT UNSIGNED     NOT NULL,
		  area_id INT UNSIGNED     NOT NULL,
		  type    ENUM('home','work') NOT NULL,
		  set_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  PRIMARY KEY(uid,type)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
		");
	}
	
	// 10) Default-Icons für home/work
	$defaults = [
	  'icon_home' => '<i class="far fa-home-heart"></i>',
	  'icon_work' => '<i class="fa-regular fa-briefcase"></i>'
	];
	foreach(['icon_home','icon_work'] as $setting) {
	  if(!$db->fetch_field($db->simple_select('settings','sid',"name='{$setting}'"), 'sid')) {
		$db->insert_query('settings', [
		  'name'      => $setting,
		  'title'     => ($setting=='icon_home'?'Icon (Home)':'Icon (Work)'),
		  'optionscode'=>'text',
		  'value'     => $defaults[$setting],
		  'disporder' => 20,
		  'gid'       => $gid
		]);
		}
	}
	

    rebuild_settings();
}

function karten_deactivate() {
    // Hier nichts entfernen
}

function karten_uninstall() {
    global $db;
    require_once MYBB_ROOT . 'inc/functions.php';

    // 1) Spalten in map_areas entfernen
    if($db->field_exists('marker_title', 'map_areas')) {
        $db->write_query("
            ALTER TABLE ".TABLE_PREFIX."map_areas
            DROP COLUMN marker_title
        ");
    }
    if($db->field_exists('category', 'map_areas')) {
        $db->write_query("
            ALTER TABLE ".TABLE_PREFIX."map_areas
            DROP COLUMN category
        ");
    }

    // 2) map_images löschen
    if($db->table_exists('map_images')) {
        $db->write_query("DROP TABLE ".TABLE_PREFIX."map_images");
    }

    // 3) karte_region_map löschen
    if($db->table_exists('karte_region_map')) {
        $db->write_query("DROP TABLE ".TABLE_PREFIX."karte_region_map");
    }

    // 4) Settings & Gruppe löschen
    $db->delete_query('settings', "name IN
        ('karten_image_path','karten_region_fid','karten_region_fids')
    ");
    $db->delete_query('settinggroups', "name='karten'");
    rebuild_settings();
	
	// Attribute wieder entfernen
	foreach(['allow_home','allow_work','cap_home_max','cap_work_max','icon_home','icon_work'] as $col) {
		if($db->field_exists($col,'map_areas')) {
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."map_areas DROP COLUMN {$col}");
		}
	}
	
	// user_locations löschen
	if($db->table_exists('user_locations')) {
		$db->write_query("DROP TABLE ".TABLE_PREFIX."user_locations");
	}
	
	// Karten > erlaubte Usergruppen
	$db->delete_query('settings', "name='karten_allowed_groups'");
	
}

function karten_admin_menu(&$sub_menu) {
    global $lang;
    $lang->load('karten');
    $sub_menu[] = ['id'=>'karten','title'=>'Karten verwalten','link'=>'index.php?module=config-karten'];
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
 * 1) Admin‐Add/Edit: zusätzliche Felder aus map_areas
 */
function kartenext_admin_fields_end(&$form) {
    global $mybb, $db, $settings;
    if(THIS_SCRIPT=='index.php'
       && $mybb->input['module']==='config-karten'
       && in_array($mybb->input['action'],['karten_add','karten_edit'],true)
    ) {
        // Kategorie-Select + Custom
        $cats = preg_split('/\r?\n/',$settings['karte_ext_categories']);
        $q    = $db->simple_select('map_areas','DISTINCT category',"category!=''");
        while($r=$db->fetch_array($q)) {
            if(!in_array($r['category'],$cats,true)) {
                $cats[] = $r['category'];
            }
        }
        $opt='';
        foreach($cats as $c) {
            $sel = ($mybb->input['category']===$c)?' selected':'';
            $opt .= "<option value=\"".htmlspecialchars($c)."\"$sel>"
                  .htmlspecialchars($c)."</option>";
        }
        $form->output_row('Kategorie', "<select name=\"category\">$opt</select><br>
          <small>oder eigene eingeben:</small> <input type=\"text\" name=\"category_custom\" size=\"20\" />",
          'category'
        );
        // Marker-Title
        $form->output_row('Markierungstitel',
            '<input type="text" name="marker_title" size="30" '
           .'value="'.htmlspecialchars($mybb->input['marker_title']??'',ENT_QUOTES).'" />',
            'marker_title'
        );
    }
}

/**
 * 2) Admin‐Save: $_POST mit Kategorie+Marker füllen
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
 * 3) Frontend JSON: Kategorie & Marker-Title mergen
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
        } else {
            $reg['category']     = '';
            $reg['marker_title'] = '';
        }
    }
    unset($reg);
    $json = json_encode($regions);
}
