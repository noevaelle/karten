<?php
if(!defined('IN_MYBB') || !defined('IN_ADMINCP')) {
    die('Kein Zugriff.');
}

global $db, $mybb, $page, $lang;
$lang->load('karten');

// Breadcrumb & Header
$page->add_breadcrumb_item('Karten verwalten','index.php?module=config-karten');
$page->add_breadcrumb_item('Karte hochladen','index.php?module=config-karten_upload');
$page->output_header('Karte hochladen');

// Sub‐Tabs
$sub_tabs = [
    'übersicht'  => ['title'=>'Übersicht',       'link'=>'index.php?module=config-karten'],
    'hinzufügen' => ['title'=>'Neuer Bereich',   'link'=>'index.php?module=config-karten_add'],
    'upload'     => ['title'=>'Karte hochladen', 'link'=>'index.php?module=config-karten_upload'],
];
$page->output_nav_tabs($sub_tabs, 'upload');

// Pfade
$relPath = trim($mybb->settings['karten_image_path'], '/');
$absPath = rtrim(MYBB_ROOT, '/')."/{$relPath}/";

// ─── ➊ Titel bearbeiten ───────────────────────────────────────────────────
$editFile = trim($mybb->get_input('edit'));
if($editFile) {
    $file = basename(rawurldecode($editFile));

    // POST-Handler
    if($mybb->request_method === 'post' && $mybb->get_input('edit_submit')) {
        if($mybb->get_input('my_post_key') !== $mybb->post_code) {
            flash_message('Ungültiger Autorisierungscode','error');
            admin_redirect("index.php?module=config-karten_upload&edit=".urlencode($file));
        }
        $image_title = $db->escape_string(trim($mybb->get_input('image_title')));
        // prüfen, ob Eintrag existiert
        $exists = $db->fetch_field(
            $db->simple_select('map_images','image_file',
                "image_file='".$db->escape_string($file)."'"
            ),
            'image_file'
        );
        if($exists) {
            $db->update_query('map_images',
                ['image_title'=>$image_title],
                "image_file='".$db->escape_string($file)."'"
            );
        } else {
            $db->insert_query('map_images', [
                'image_file'  => $db->escape_string($file),
                'image_title' => $image_title
            ]);
        }
        flash_message('Kartentitel gespeichert','success');
        admin_redirect('index.php?module=config-karten_upload');
    }

    // Aktuellen Titel holen
    $row = $db->fetch_array(
        $db->simple_select('map_images','image_title',
            "image_file='".$db->escape_string($file)."'"
        )
    );
    $current = htmlspecialchars_uni($row['image_title'] ?? '');

    // Bearbeitungs-Formular ausgeben
    echo '<form method="post" action="index.php?module=config-karten_upload&edit='.urlencode($file).'">';
    echo '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code,ENT_QUOTES).'" />';
    echo '<table class="tborder" cellpadding="5" cellspacing="0" width="100%">';
    echo '<tr><td class="trow1">Dateiname</td><td class="trow2">'.$file.'</td></tr>';
    echo '<tr><td class="trow1">Kartentitel</td><td class="trow2">';
    echo '<input type="text" name="image_title" size="50" value="'.$current.'" />';
    echo '</td></tr>';
    echo '<tr><td class="trow2" colspan="2" align="center">';
    echo '<input type="submit" class="button" name="edit_submit" value="Speichern" />';
    echo '</td></tr>';
    echo '</table></form>';

    $page->output_footer();
    exit;
}

// ─── ➋ Löschen ──────────────────────────────────────────────────────────
$fileToDelete = trim($mybb->get_input('delete'));
if($fileToDelete) {
    $file = basename(rawurldecode($fileToDelete));
    // Bestätigung
    if(!$mybb->get_input('confirm_delete')) {
        echo '<form method="post" action="index.php?module=config-karten_upload&amp;delete='.urlencode($file).'">';
        echo '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code,ENT_QUOTES).'" />';
        echo '<div class="confirm_message" style="margin:20px;">';
        echo '<p>Möchtest du das Bild <strong>'.$file.'</strong> wirklich löschen?</p>';
        echo '<div class="buttons">';
        echo '<input type="submit" name="confirm_delete" class="button" value="Ja, löschen" /> ';
        echo '<a href="index.php?module=config-karten_upload" class="button">Abbrechen</a>';
        echo '</div></div></form>';
        $page->output_footer();
        exit;
    }
    // Abschließendes Löschen
    if($mybb->get_input('my_post_key') !== $mybb->post_code) {
        flash_message('Ungültiger Autorisierungscode','error');
    } else {
        $path = $absPath . $file;
        if(is_file($path)) { unlink($path); }
        $db->delete_query('map_images', "image_file='".$db->escape_string($file)."'");
        flash_message('Karte gelöscht','success');
    }
    admin_redirect('index.php?module=config-karten_upload');
}

// ─── ➌ Hochladen ─────────────────────────────────────────────────────────
if($mybb->request_method === 'post' && $mybb->get_input('upload_submit')) {
    if($mybb->get_input('my_post_key') !== $mybb->post_code) {
        flash_message('Ungültiger Autorisierungscode','error');
        admin_redirect('index.php?module=config-karten_upload');
    }
    if(empty($_FILES['upload']['name']) || $_FILES['upload']['error']) {
        flash_message('Bitte eine Bilddatei auswählen','error');
        admin_redirect('index.php?module=config-karten_upload');
    }
    $allowed = ['png','jpg','jpeg','gif','svg'];
    $ext     = strtolower(pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, $allowed, true)) {
        flash_message('Unzulässiges Dateiformat','error');
        admin_redirect('index.php?module=config-karten_upload');
    }
    $file   = basename($_FILES['upload']['name']);
    $target = $absPath . $file;
    if(move_uploaded_file($_FILES['upload']['tmp_name'], $target)) {
        // Titel initial speichern
        $image_title = $db->escape_string(trim($mybb->get_input('image_title')));
        $db->insert_query('map_images', [
            'image_file'  => $db->escape_string($file),
            'image_title' => $image_title
        ]);
        flash_message('Karte hochgeladen','success');
    } else {
        flash_message('Fehler beim Verschieben der Datei','error');
    }
    admin_redirect('index.php?module=config-karten_upload');
}

// ─── ➍ Upload‐Formular ───────────────────────────────────────────────────
echo '<form method="post" enctype="multipart/form-data" action="index.php?module=config-karten_upload">';
echo '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code,ENT_QUOTES).'" />';
echo '<table class="tborder" cellpadding="5" cellspacing="0" width="100%">';
echo '<tr><td class="trow1">Bilddatei</td><td class="trow2">';
echo '<input type="file" name="upload" accept="image/*" />';
echo '</td></tr>';
echo '<tr><td class="trow1">Kartentitel</td><td class="trow2">';
echo '<input type="text" name="image_title" size="50" />';
echo '</td></tr>';
echo '<tr><td class="trow2" colspan="2" align="center">';
echo '<input type="submit" class="button" name="upload_submit" value="Hochladen" />';
echo '</td></tr>';
echo '</table></form>';

// ─── ➎ Liste aller Bilder ─────────────────────────────────────────────────
echo '<h3>Verfügbare Kartenbilder</h3>';
echo '<table class="tborder" cellpadding="5" cellspacing="1" width="100%">';
echo '<tr><th>Vorschau</th><th>Dateiname</th><th>Kartentitel</th><th>Aktion</th></tr>';
if(is_dir($absPath)) {
    foreach(scandir($absPath) as $f) {
        if(preg_match('/\.(png|jpe?g|gif|svg)$/i',$f)) {
            // Titel aus DB holen
            $title = $db->fetch_field(
                $db->simple_select('map_images','image_title',
                    "image_file='".$db->escape_string($f)."'"
                ),
                'image_title'
            );
            $url   = htmlspecialchars($mybb->settings['bburl'].'/'.$relPath.'/'.$f,ENT_QUOTES);
            $edit  = 'index.php?module=config-karten_upload&amp;edit='.urlencode($f);
            $del   = 'index.php?module=config-karten_upload&amp;delete='.urlencode($f);
            echo '<tr>';
            echo '<td style="width:80px;"><img src="'.$url.'" style="max-width:80px;" /></td>';
            echo '<td>'.htmlspecialchars($f,ENT_QUOTES).'</td>';
            echo '<td>'.htmlspecialchars($title,ENT_QUOTES).'</td>';
            echo '<td><a href="'.$edit.'">Titel bearbeiten</a> • <a href="'.$del.'">Löschen</a></td>';
            echo '</tr>';
        }
    }
}
echo '</table>';

$page->output_footer();
