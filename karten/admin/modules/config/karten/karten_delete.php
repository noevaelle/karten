<?php
if(!defined('IN_MYBB') || !defined('IN_ADMINCP')) {
    die('Kein Zugriff.');
}

global $db, $mybb, $page, $lang;
$lang->load('karten');

// ID holen
$id = $mybb->get_input('id', MyBB::INPUT_INT);

// Header & Sub-Tabs
$page->add_breadcrumb_item('Karten verwalten', 'index.php?module=config-karten');
$page->output_header('Karten verwalten');

// Wenn Seite noch nicht per POST bestätigt wurde: Bestätigungsformular ausgeben
if($mybb->request_method !== 'post') {
    echo '<div class="confirm_message" style="margin:20px;">';
    echo '<p>Möchtest du den Kartenbereich <strong>#'.$id.'</strong> wirklich löschen?</p>';
    echo '<form method="post" action="index.php?module=config-karten_delete&amp;id='.$id.'">';
    // CSRF-Token
    echo '<input type="hidden" name="my_post_key" value="'.htmlspecialchars($mybb->post_code,ENT_QUOTES).'" />';
    echo '<div class="buttons" style="margin-top:10px;">';
    echo '<input type="submit" class="button" value="Ja, löschen" />';
    echo ' <a href="index.php?module=config-karten" class="button">Abbrechen</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    $page->output_footer();
    exit;
}

// CSRF-Prüfung
if($mybb->get_input('my_post_key') !== $mybb->post_code) {
    flash_message('Autorisierungscode ungültig','error');
    admin_redirect('index.php?module=config-karten');
}

// Tatsächliches Löschen
$db->delete_query('map_areas', "id={$id}");
flash_message('Kartenbereich gelöscht','success');
admin_redirect('index.php?module=config-karten');
