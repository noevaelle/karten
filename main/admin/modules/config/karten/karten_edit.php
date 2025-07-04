<?php
if(!defined('IN_MYBB') || !defined('IN_ADMINCP')) {
    die('Kein Zugriff.');
}

global $db, $mybb, $page, $lang;
$lang->load('karten');

// ID prüfen
$id = (int)$mybb->get_input('id');
if(!$id) {
    flash_message('Keine Bereichs-ID übergeben','error');
    admin_redirect('index.php?module=config-karten');
}

// POST: Speichern
if($mybb->request_method==='post') {
    if($mybb->get_input('my_post_key')!==$mybb->post_code) {
        flash_message('Ungültiger Autorisierungscode','error');
        admin_redirect("index.php?module=config-karten_edit&amp;id={$id}");
    }

    // Werte
    $region_value   = $db->escape_string(trim($mybb->get_input('region_value')));
    $location_value = $db->escape_string(trim($mybb->get_input('location_value')));
    $image_file     = $db->escape_string(trim($mybb->get_input('image_file')));
	
	$allow_home    = $mybb->get_input('allow_home', MyBB::INPUT_INT) ? 1 : 0;
	$allow_work    = $mybb->get_input('allow_work', MyBB::INPUT_INT) ? 1 : 0;
	$cap_home_max  = max(0,(int)$mybb->get_input('cap_home_max'));
	$cap_work_max  = max(0,(int)$mybb->get_input('cap_work_max'));
	$icon_home     = $db->escape_string(trim($mybb->get_input('icon_home')));
	$icon_work     = $db->escape_string(trim($mybb->get_input('icon_work')));
	$icon_category = $db->escape_string(trim($mybb->get_input('icon_category')));
	
    $category       = $db->escape_string(
                         trim($mybb->get_input('category_custom'))
                         ?: trim($mybb->get_input('category'))
                       );
    $marker_title   = $db->escape_string(trim($mybb->get_input('marker_title')));

    // Mapping aus DB
    $fid_region     = (int)$mybb->settings['karten_region_fid'];
    $row_map        = $db->fetch_array(
        $db->simple_select(
            'karte_region_map','fid_location',
            "region_value='{$db->escape_string($region_value)}'"
        )
    );
    $fid_location   = (int)($row_map['fid_location'] ?? 0);

    // Crop-Daten
    $x      = (int)$mybb->get_input('x');
    $y      = (int)$mybb->get_input('y');
    $width  = (int)$mybb->get_input('width');
    $height = (int)$mybb->get_input('height');

    // Updaten
    $db->update_query('map_areas', [
        'fid_region'     => $fid_region,
        'region_value'   => $region_value,
        'fid_location'   => $fid_location,
        'location_value' => $location_value,
        'category'       => $category,
        'marker_title'   => $marker_title,
        'x'              => $x,
        'y'              => $y,
        'width'          => $width,
        'height'         => $height,
        'image_file'     => $image_file,
		'allow_home'    => $allow_home,
		'allow_work'    => $allow_work,
		'cap_home_max'  => $cap_home_max,
		'cap_work_max'  => $cap_work_max,
		'icon_home'     => $icon_home,
		'icon_work'     => $icon_work,
		'icon_category' => $icon_category,
		
    ], "id={$id}");

    flash_message('Kartenbereich aktualisiert','success');
    admin_redirect('index.php?module=config-karten');
}

// Datensatz laden
$area = $db->fetch_array($db->simple_select('map_areas','*',"id={$id}"));
if(!$area) {
    flash_message('Datensatz nicht gefunden','error');
    admin_redirect('index.php?module=config-karten');
}

// Header + Tabs
$page->add_breadcrumb_item('Karten verwalten','index.php?module=config-karten');
$page->add_breadcrumb_item('Bereich bearbeiten','index.php?module=config-karten_edit&amp;id='.$id);
$page->output_header('Kartenbereich bearbeiten');

$sub_tabs = [
    'übersicht'  => ['title'=>'Übersicht',     'link'=>'index.php?module=config-karten'],
    'hinzufügen' => ['title'=>'Neuen Bereich', 'link'=>'index.php?module=config-karten_add'],
    'upload'     => ['title'=>'Karte hochladen','link'=>'index.php?module=config-karten_upload']
];
$page->output_nav_tabs($sub_tabs, 'übersicht');

// Stylesheet
echo '<link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css"
 />';

// Bild-URL
$imgUrl = rtrim($mybb->settings['bburl'],'/').'/'.trim($mybb->settings['karten_image_path'],'/').'/';

// Form
echo '<form method="post" action="index.php?module=config-karten_edit&amp;id='.$id.'" style="margin:10px;">';
echo '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code,ENT_QUOTES).'" />';
echo '<table cellpadding="5" cellspacing="0" width="100%">';

// Bild
echo '<tr><td class="trow1">Kartenbild</td><td class="trow2">';
echo '<select id="image_file" name="image_file"><option value="">-- wählen --</option>';
$folder = rtrim(MYBB_ROOT,'/').'/'.trim($mybb->settings['karten_image_path'],'/').'/';
if(is_dir($folder)) {
    foreach(scandir($folder) as $f) {
        if(preg_match('/\.(png|jpe?g|gif|svg)$/i',$f)) {
            $sel = ($area['image_file']=== $f)?' selected':'';
            echo '<option value="'.htmlspecialchars($f,ENT_QUOTES).'"'.$sel.'>'.$f.'</option>';
        }
    }
}
echo '</select></td></tr>';

// Region
echo '<tr><td class="trow1">Region</td><td class="trow2">';
echo '<select id="region_value" name="region_value"><option value="">-- wählen --</option>';
$fid = (int)$mybb->settings['karten_region_fid'];
$q   = $db->simple_select('profilefields','type',"fid={$fid}");
$raw = $db->fetch_field($q,'type');
$opts= array_filter(array_map('trim', explode("\n",$raw)));
array_shift($opts);
foreach($opts as $v) {
       $sel = ($area['region_value'] === $v) ? ' selected' : '';
    echo '<option value="'.htmlspecialchars($v,ENT_QUOTES).'"'.$sel.'>'
         .   htmlspecialchars_uni($v)
         .'</option>';
}
echo '</select></td></tr>';

// Standort
echo '<tr><td class="trow1">Standort</td><td class="trow2">';
echo '<select id="location_value" name="location_value">';
if($area['region_value']) {
    // Mapping aus DB
    $row_map      = $db->fetch_array(
        $db->simple_select(
            'karte_region_map','fid_location',
            "region_value='{$db->escape_string($area['region_value'])}'"
        )
    );
    $fid_loc      = (int)($row_map['fid_location'] ?? 0);
    if($fid_loc) {
        $qr   = $db->simple_select('profilefields','type',"fid={$fid_loc}");
        $def  = $db->fetch_field($qr,'type');
        $locs = array_filter(array_map('trim', explode("\n",$def)));
        array_shift($locs);
        echo '<option value="">-- wählen --</option>';
        foreach($locs as $loc) {
            $sel = ($area['location_value']===$loc)?' selected':'';
            echo '<option value="'.htmlspecialchars($loc,ENT_QUOTES).'"'.$sel.'>'.$loc.'</option>';
        }
    } else {
        echo '<option value="">-- keine Standorte --</option>';
    }
} else {
    echo '<option value="">-- erst Region wählen --</option>';
}
echo '</select></td></tr>';

// Kategorie
echo '<tr><td class="trow1">Kategorie</td><td class="trow2">';
$cats = preg_split('/\r?\n/',$mybb->settings['karte_ext_categories']);
$q    = $db->simple_select('map_areas','DISTINCT category',"category!=''");
while($r=$db->fetch_array($q)) {
    if(!in_array($r['category'],$cats,true)) {
        $cats[] = $r['category'];
    }
}
echo '<select name="category">';
foreach($cats as $c) {
    $sel = ($area['category']===$c)?' selected':'';
    echo '<option value="'.htmlspecialchars_uni($c).'"'.$sel.'>'.htmlspecialchars_uni($c).'</option>';
}
echo '</select><br><small>oder eigene:</small> ';
echo '<input type="text" name="category_custom" size="20" '
   .'value="'.htmlspecialchars_uni($area['category']).'" />';
echo '</td></tr>';

// Marker-Title
echo '<tr><td class="trow1">Markierungstitel</td><td class="trow2">';
echo '<input type="text" name="marker_title" size="30" '
   .'value="'.htmlspecialchars_uni($area['marker_title']).'" />';
echo '</td></tr>';

// — Wohnort / Arbeitsplatz erlauben —
echo '<tr><td class="trow1">Erlaube Wohnort</td><td class="trow2">'
   .'<input type="checkbox" name="allow_home" value="1" '
   .($area['allow_home']?'checked':'').' /></td></tr>';
echo '<tr><td class="trow1">Erlaube Arbeitsplatz</td><td class="trow2">'
   .'<input type="checkbox" name="allow_work" value="1" '
   .($area['allow_work']?'checked':'').' /></td></tr>';

// — Kapazitäten —  
echo '<tr><td class="trow1">Max. Bewohner</td><td class="trow2">'
   .'<input type="number" name="cap_home_max" value="'.(int)$area['cap_home_max'].'" min="0" />'
   .'<br><small>0 = unbegrenzt</small></td></tr>';
echo '<tr><td class="trow1">Max. Arbeitende</td><td class="trow2">'
   .'<input type="number" name="cap_work_max" value="'.(int)$area['cap_work_max'].'" min="0" />'
   .'<br><small>0 = unbegrenzt</small></td></tr>';

// — Icons —  
echo '<tr><td class="trow1">Icon (Home)</td><td class="trow2">'
   .'<input type="text" name="icon_home" value="'.htmlspecialchars_uni($area['icon_home']).'" size="50" />'
   .'</td></tr>';
echo '<tr><td class="trow1">Icon (Work)</td><td class="trow2">'
   .'<input type="text" name="icon_work" value="'.htmlspecialchars_uni($area['icon_work']).'" size="50" />'
   .'</td></tr>';

// Formular-Tpl:
echo '<tr><td class="trow1">Icon (Kategorie)</td><td class="trow2">'
   .'<input type="text" name="icon_category" id="icon_category"'
   .' value="'.htmlspecialchars_uni($area['icon_category']).'" size="50" />'
   .'</td></tr>';

// Cropper
echo '<tr><td class="trow1">Crop-Bereich</td><td class="trow2">';
echo '<img id="mapImage" src="'.htmlspecialchars($imgUrl.$area['image_file'],ENT_QUOTES).'" '
   .'style="max-width:500px;display:none;" />';
echo '<input type="hidden" id="areaX" name="x"     value="'.(int)$area['x'].'" />';
echo '<input type="hidden" id="areaY" name="y"     value="'.(int)$area['y'].'" />';
echo '<input type="hidden" id="areaW" name="width" value="'.(int)$area['width'].'" />';
echo '<input type="hidden" id="areaH" name="height"value="'.(int)$area['height'].'" />';
echo '</td></tr>';

// Speichern
echo '<tr><td class="trow2" colspan="2" style="text-align:center;">'
   .'<input type="submit" class="button" value="Speichern" /></td></tr>';

echo '</table></form>';

// Cropper.js
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>';
?>
<script type="text/javascript">
jQuery(function($){
  // Ajax-Standorte im „Bearbeiten“-Formular
  $('#region_value').on('change', function(){
    var region = $(this).val(),
        loc    = $('#location_value');
    loc.html('<option value="">Lade…</option>');
    $.ajax({
      url: '<?php echo $mybb->settings['bburl']; ?>/admin/modules/config/karten/karten_get_locations.php',
      type: 'GET',
      data: { region: region },
      dataType: 'html',
      success: function(data){
        loc.html(data);
        loc.val('<?php echo addslashes($area['location_value']); ?>');
      },
      error: function(){
        loc.html('<option value="">Fehler beim Laden</option>');
      }
    });
  }).trigger('change');

  // Cropper initialisieren
  var img     = document.getElementById('mapImage'),
      cropper = null,
      inputX  = $('#areaX'),
      inputY  = $('#areaY'),
      inputW  = $('#areaW'),
      inputH  = $('#areaH');

  function updateInputs(data) {
    inputX.val(Math.round(data.x));
    inputY.val(Math.round(data.y));
    inputW.val(Math.round(data.width));
    inputH.val(Math.round(data.height));
  }

  function initCropper(x, y, w, h) {
    if(cropper) {
      cropper.destroy();
    }
    cropper = new Cropper(img, {
      viewMode:    1,
      movable:     true,
      zoomable:    true,
      zoomOnWheel: true,
      zoomOnTouch: true,
      background:  false,
      data: {
        x:      x,
        y:      y,
        width:  w,
        height: h
      },
      ready: function() {
        updateInputs(this.getData());
      },
      crop: function(e) {
        updateInputs(e.detail);
      },
      zoom: function() {
        updateInputs(this.getData());
      }
    });
  }

  $('#image_file').on('change', function(){
    var file = this.value;
    if(cropper){
      cropper.destroy();
      cropper = null;
    }
    if(!file){
      img.style.display = 'none';
      return;
    }
    img.src = '<?php echo $imgUrl; ?>' + encodeURIComponent(file);
    img.onload = function(){
      img.style.display = 'block';
      initCropper(
        <?php echo (int)$area['x']; ?>,
        <?php echo (int)$area['y']; ?>,
        <?php echo (int)$area['width']; ?>,
        <?php echo (int)$area['height']; ?>
      );
    };
  }).trigger('change');
});
</script>
<?php
$page->output_footer();
