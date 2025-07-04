<?php
if(!defined('IN_MYBB') || !defined('IN_ADMINCP')) {
    die('Kein Zugriff.');
}

global $db, $mybb, $page, $lang;
$lang->load('karten');

// ─── POST-Handler: Speichern ────────────────────────────────────────────
if($mybb->request_method === 'post') {
    if($mybb->get_input('my_post_key') !== $mybb->post_code) {
        flash_message('Ungültiger Autorisierungscode','error');
        admin_redirect('index.php?module=config-karten_add');
    }
    // Werte sammeln
    $region_value   = $db->escape_string($mybb->get_input('region_value'));
    $location_value = $db->escape_string($mybb->get_input('location_value'));
    $image_file     = $db->escape_string($mybb->get_input('image_file'));
	
	$allow_home    = $mybb->get_input('allow_home', MyBB::INPUT_INT) ? 1 : 0;
	$allow_work    = $mybb->get_input('allow_work', MyBB::INPUT_INT) ? 1 : 0;
	$cap_home_max  = max(0,(int)$mybb->get_input('cap_home_max'));
	$cap_work_max  = max(0,(int)$mybb->get_input('cap_work_max'));
	$icon_home     = $db->escape_string(trim($mybb->get_input('icon_home')));
	$icon_work     = $db->escape_string(trim($mybb->get_input('icon_work')));
	$icon_category = $db->escape_string(trim($mybb->get_input('icon_category')));

    // Kategorie & Markierungstitel
    $category       = $db->escape_string(
                         $mybb->get_input('category_custom')
                         ?: $mybb->get_input('category')
                       );
    $marker_title   = $db->escape_string($mybb->get_input('marker_title'));

    // Neue Mapping-Logik: aus DB
    $fid_region     = (int)$mybb->settings['karten_region_fid'];
    $row_map        = $db->fetch_array(
        $db->simple_select(
            'karte_region_map','fid_location',
            "region_value='{$db->escape_string($region_value)}'"
        )
    );
    $fid_location   = (int)($row_map['fid_location'] ?? 0);

    // Crop-Koordinaten
    $x      = (int)$mybb->get_input('x');
    $y      = (int)$mybb->get_input('y');
    $width  = (int)$mybb->get_input('width');
    $height = (int)$mybb->get_input('height');

    // Datensatz speichern
    $db->insert_query('map_areas', [
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

	]);

    flash_message('Kartenbereich gespeichert','success');
    admin_redirect('index.php?module=config-karten');
}

// ─── Formular-Ausgabe ──────────────────────────────────────────────────
$page->add_breadcrumb_item('Karten verwalten','index.php?module=config-karten');
$page->output_header('Neuen Kartenbereich anlegen');


// Cropper-Stylesheet
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css"/>';

$sub_tabs = [
    'übersicht'  => ['title'=>'Übersicht',       'link'=>'index.php?module=config-karten'],
    'hinzufügen' => ['title'=>'Neuen Bereich',   'link'=>'index.php?module=config-karten_add'],
    'upload'     => ['title'=>'Karte hochladen','link'=>'index.php?module=config-karten_upload']
];
$page->output_nav_tabs($sub_tabs, 'hinzufügen');

// Bildpfad
$imgUrl = rtrim($mybb->settings['bburl'],'/').'/'.trim($mybb->settings['karten_image_path'],'/').'/';

echo '<form method="post" action="index.php?module=config-karten_add" style="margin:10px;">';
echo '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code,ENT_QUOTES).'" />';
echo '<table cellpadding="5" cellspacing="0" width="100%">';

// Kartenbild
echo '<tr><td class="thead" width="20%">Kartenbild</td><td class="trow1">';
echo '<select id="image_file" name="image_file"><option value="">-- wählen --</option>';
$folder = rtrim(MYBB_ROOT,'/').'/'.trim($mybb->settings['karten_image_path'],'/').'/';
if(is_dir($folder)) {
    foreach(scandir($folder) as $f) {
        if(preg_match('/\.(png|jpe?g|gif|svg)$/i',$f)) {
            $sel = ($mybb->get_input('image_file')===$f)?' selected':'';
            echo '<option value="'.htmlspecialchars($f,ENT_QUOTES).'"'.$sel.'>'.$f.'</option>';
        }
    }
}
echo '</select></td></tr>';

// Region
echo '<tr><td class="thead">Region</td><td class="trow1">';
echo '<select id="region_value" name="region_value"><option value="">-- wählen --</option>';
$fid = (int)$mybb->settings['karten_region_fid'];
$q   = $db->simple_select('profilefields','type',"fid={$fid}");
$raw = $db->fetch_field($q,'type');
$opts= array_filter(array_map('trim', explode("\n",$raw)));
array_shift($opts);
foreach($opts as $v) {
    $sel = ($mybb->get_input('region_value')===$v)?' selected':'';
    echo '<option value="'.htmlspecialchars($v,ENT_QUOTES).'"'.$sel.'>'.$v.'</option>';
}
echo '</select></td></tr>';

// Standort (Ajax)
echo '<tr><td class="thead">Standort (Genauerer Wohnort)</td><td class="trow1">';
echo '<select id="location_value" name="location_value"><option value="">-- erst Region wählen --</option></select>';
echo '</td></tr>';

// Kategorie
echo '<tr><td class="thead">Kategorie</td><td class="trow1">';
$cats = preg_split('/\r?\n/',$mybb->settings['karte_ext_categories']);
$q    = $db->simple_select('map_areas','DISTINCT category',"category!=''");
while($r=$db->fetch_array($q)) {
    if(!in_array($r['category'],$cats,true)) {
        $cats[] = $r['category'];
    }
}
echo '<select name="category">';
foreach($cats as $c) {
    $sel = ($mybb->get_input('category')===$c)?' selected':'';
    echo '<option value="'.htmlspecialchars_uni($c).'"'.$sel.'>'.htmlspecialchars_uni($c).'</option>';
}
echo '</select><br><small>oder eigene:</small> ';
echo '<input type="text" name="category_custom" size="20" />';
echo '</td></tr>';

// Markierungstitel
echo '<tr><td class="thead">Markierungstitel (Nummer auf Karte)</td><td class="trow1">';
echo '<input type="text" name="marker_title" size="10" />';
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

// — Icon (Kategorie) —
echo '<tr><td class="trow1">Icon (Kategorie - für Legende)</td><td class="trow2">'
   .'<input type="text" name="icon_category" id="icon_category"'
   .' value="'.htmlspecialchars_uni($mybb->get_input('icon_category')).'" size="50" />'
   .'<br><small>z. B. „fas fa-store“ für Handel oder Bilddatei im Verzeichnisordner/icons</small></td></tr>';

// Cropper
echo '<tr><td class="thead">Crop-Bereich</td><td class="trow1">';
echo '<img id="mapImage" style="display:none;max-width:500px;" />';
echo '<input type="hidden" id="areaX" name="x" value="0" />';
echo '<input type="hidden" id="areaY" name="y" value="0" />';
echo '<input type="hidden" id="areaW" name="width" value="0" />';
echo '<input type="hidden" id="areaH" name="height" value="0" />';
echo '</td></tr>';

// Speichern
echo '<tr><td colspan="2" style="text-align:center;padding-top:10px;">'
   .'<input type="submit" class="button" value="Speichern" /></td></tr>';

echo '</table></form>';

// Cropper.js
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>';
?>
<script type="text/javascript">
jQuery(function($){
  // Ajax-Standorte im „Neuen Kartenbereich“-Formular
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
      },
      error: function(){
        loc.html('<option value="">Fehler beim Laden</option>');
      }
    });
  }).trigger('change');

  // Cropper initialisieren
  var image   = document.getElementById('mapImage'),
      cropper = null,
      inputX  = $('#areaX'),
      inputY  = $('#areaY'),
      inputW  = $('#areaW'),
      inputH  = $('#areaH');

  $('#image_file').on('change', function(){
    var file = this.value;
    if(cropper){ cropper.destroy(); cropper = null; }
    if(!file){
      image.style.display = 'none';
      return;
    }
    image.src = '<?php echo $imgUrl; ?>' + encodeURIComponent(file);
    image.onload = function(){
      image.style.display = 'block';
      cropper = new Cropper(image, {
        viewMode: 1,
        autoCropArea: 0.5,
        movable: true,
        zoomable: true,
        background: false,
        crop: function(e){
          inputX.val(Math.round(e.detail.x));
          inputY.val(Math.round(e.detail.y));
          inputW.val(Math.round(e.detail.width));
          inputH.val(Math.round(e.detail.height));
        }
      });
    };
  }).trigger('change');
});
</script>
<?php
$page->output_footer();
