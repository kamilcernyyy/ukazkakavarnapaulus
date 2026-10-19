<?php
/**
 * Kavárna Paulus — jednoduchý editor obsahu.
 * Uloží content.json a znovu vygeneruje statický index.html (web zůstává stejně rychlý).
 */
declare(strict_types=1);
define('PAULUS_ADMIN', true);
require __DIR__ . '/config.php';
require __DIR__ . '/render.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_name('paulus_admin');
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
$dir = __DIR__;
$notice = ''; $error = '';

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function post(string $k, int $max = 600): string {
    $v = $_POST[$k] ?? '';
    $v = is_string($v) ? str_replace("\r", '', trim($v)) : '';
    return mb_substr($v, 0, $max);
}
function check_csrf(string $csrf): void {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Neplatný formulář — obnovte stránku.'); }
}

/* ---- login throttling (per IP, survives new sessions) ---- */
$ipKey = sys_get_temp_dir() . '/paulus_login_' . hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . __DIR__);
$att = json_decode((string)@file_get_contents($ipKey), true) ?: ['n' => 0, 't' => time()];
if (time() - $att['t'] > 900) $att = ['n' => 0, 't' => time()];

if (($_POST['action'] ?? '') === 'login') {
    check_csrf($csrf);
    if ($att['n'] >= 8) { $error = 'Příliš mnoho pokusů. Zkuste to za 15 minut.'; }
    elseif (password_verify((string)($_POST['password'] ?? ''), PAULUS_PASS_HASH)) {
        session_regenerate_id(true); $_SESSION['ok'] = true; $_SESSION['csrf'] = $csrf = bin2hex(random_bytes(32));
        @unlink($ipKey);
    } else {
        $att['n']++; @file_put_contents($ipKey, json_encode($att)); sleep(min($att['n'], 4));
        $error = 'Nesprávné heslo.';
    }
}
if (($_POST['action'] ?? '') === 'logout') { check_csrf($csrf); $_SESSION = []; session_destroy(); header('Location: admin.php'); exit; }

$logged = !empty($_SESSION['ok']);
$c = json_decode((string)file_get_contents($dir . '/content.json'), true);
if (!isset($c['photos']) || !is_array($c['photos'])) $c['photos'] = [];

/** atomically write content.json (with a backup) and re-render the site; '' on success */
function save_content(string $dir, array $n): string {
    @copy($dir . '/content.json', $dir . '/content-zaloha-' . date('Ymd-His') . '.json');
    $b = glob($dir . '/content-zaloha-*.json') ?: []; sort($b);
    foreach (array_slice($b, 0, max(0, count($b) - 5)) as $old) @unlink($old);
    $tmp = $dir . '/content.json.tmp';
    $ok = file_put_contents($tmp, json_encode($n, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false && rename($tmp, $dir . '/content.json');
    if (!$ok) return 'content.json nelze zapsat — zkontrolujte práva k souborům.';
    return paulus_render_to($dir) ?? '';
}

/** crop to the slot's aspect, fix phone rotation, write every size as WebP (+ AVIF where the server can); '' on success */
function save_photo(string $dir, string $slot, string $tmp, array &$c, string $alt): string {
    $spec = PAULUS_PHOTOS[$slot] ?? null;
    if (!$spec) return 'Neznámé místo pro fotku.';
    if (!extension_loaded('gd') || !function_exists('imagewebp')) return 'Server neumí upravovat obrázky (chybí knihovna GD s WebP). Napište prosím podpoře hostingu.';
    @ini_set('memory_limit', '512M'); @set_time_limit(120);
    $info = @getimagesize($tmp);
    if (!$info) return 'Soubor není obrázek.';
    if ($info[0] * $info[1] > 60000000) return 'Fotka má příliš vysoké rozlišení (max. 60 Mpx).';
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($tmp); break;
        case IMAGETYPE_PNG:  $im = @imagecreatefrompng($tmp); break;
        case IMAGETYPE_WEBP: $im = @imagecreatefromwebp($tmp); break;
        default: return 'Nahrajte fotku ve formátu JPG, PNG nebo WebP.';
    }
    if (!$im) return 'Fotku se nepodařilo načíst.';
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)((@exif_read_data($tmp) ?: [])['Orientation'] ?? 1);
        if ($o === 3) $im = imagerotate($im, 180, 0); elseif ($o === 6) $im = imagerotate($im, -90, 0); elseif ($o === 8) $im = imagerotate($im, 90, 0);
    }
    $w = imagesx($im); $h = imagesy($im); [$rw, $rh] = $spec['r'];
    if ($w / $h > $rw / $rh) { $cw = (int)round($h * $rw / $rh); $ch = $h; $cx = (int)(($w - $cw) / 2); $cy = 0; }
    else { $cw = $w; $ch = (int)round($w * $rh / $rw); $cx = 0; $cy = (int)(($h - $ch) / 2); }
    $widths = array_values(array_filter($spec['w'], fn($x) => $x <= $cw));
    if (!$widths) $widths = [$cw];
    $id = substr(sha1(sha1_file($tmp) . microtime(true)), 0, 8);
    $avif = function_exists('imageavif'); $made = [];
    foreach ($widths as $tw) {
        $th = (int)round($tw * $rh / $rw);
        $dst = imagecreatetruecolor($tw, $th);
        imagecopyresampled($dst, $im, 0, 0, $cx, $cy, $tw, $th, $cw, $ch);
        $base = $dir . '/foto-' . $slot . '-' . $tw . '.' . $id;
        if (!imagewebp($dst, $base . '.webp', 80)) { imagedestroy($dst); foreach ($made as $f) @unlink($f); return 'Fotku nelze uložit — zkontrolujte práva k zápisu.'; }
        $made[] = $base . '.webp';
        if ($avif) { if (@imageavif($dst, $base . '.avif', 55, 6)) $made[] = $base . '.avif'; else $avif = false; }
        imagedestroy($dst);
    }
    imagedestroy($im);
    if (!$avif) foreach ($made as $i => $f) if (str_ends_with($f, '.avif')) { @unlink($f); unset($made[$i]); }
    foreach (glob($dir . '/foto-' . $slot . '-*') ?: [] as $f) if (!in_array($f, $made, true)) @unlink($f);
    $c['photos'][$slot] = ['id' => $id, 'w' => $widths, 'avif' => $avif, 'alt' => $alt];
    return '';
}

/* ---- photos: upload / delete / alt text ---- */
$isFetch = ($_SERVER['HTTP_X_PAULUS'] ?? '') === 'fetch';
if ($logged && in_array($_POST['action'] ?? '', ['photo', 'photo_delete'], true)) {
    check_csrf($csrf);
    $slot = (string)($_POST['slot'] ?? '');
    if (!isset(PAULUS_PHOTOS[$slot])) $error = 'Neznámé místo pro fotku.';
    else {
        $n = $c; $alt = post('alt', 160) ?: PAULUS_PHOTOS[$slot]['label'];
        if ($_POST['action'] === 'photo_delete') {
            foreach (glob($dir . '/foto-' . $slot . '-*') ?: [] as $f) @unlink($f);
            unset($n['photos'][$slot]);
        } elseif (!empty($_FILES['foto']) && ($_FILES['foto']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
            $fe = (int)$_FILES['foto']['error'];
            if ($fe === UPLOAD_ERR_INI_SIZE || $fe === UPLOAD_ERR_FORM_SIZE) $error = 'Fotka je pro server moc velká (limit ' . ini_get('upload_max_filesize') . '). Zkuste ji nahrát z mobilu nebo zmenšit.';
            elseif ($fe !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['foto']['tmp_name'])) $error = 'Nahrání se nepovedlo, zkuste to prosím znovu.';
            else $error = save_photo($dir, $slot, $_FILES['foto']['tmp_name'], $n, $alt);
        } elseif (isset($n['photos'][$slot])) {
            $n['photos'][$slot]['alt'] = $alt;          /* only the description changed */
        } else $error = 'Vyberte fotku.';
        if (!$error) { $err = save_content($dir, $n); if ($err) $error = 'Uložení se nepovedlo: ' . $err; else { $c = $n; $notice = 'Fotka uložena. Změna je na webu.'; } }
    }
    if ($isFetch) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => !$error, 'error' => $error], JSON_UNESCAPED_UNICODE); exit; }
}
if ($logged && ($_GET['foto'] ?? '') === 'ok') $notice = 'Fotka uložena. Změna je na webu.';

/* ---- save content ---- */
if ($logged && ($_POST['action'] ?? '') === 'save') {
    check_csrf($csrf);
    $n = $c;
    foreach ($c['texts'] as $k => $_) $n['texts'][$k] = post('t_' . $k, 900);
    foreach (['title', 'description', 'og_title', 'og_description'] as $k) $n['seo'][$k] = post('seo_' . $k, 300);
    foreach (['street', 'zip', 'city', 'phone'] as $k) $n['contact'][$k] = post('c_' . $k, 120);
    $mail = post('c_email', 120);
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) $error = 'E-mail kavárny není platný.'; else $n['contact']['email'] = $mail;
    $n['site']['name'] = post('s_name', 80) ?: $c['site']['name'];
    foreach (['url', 'instagram_url', 'facebook_url', 'mhd_url', 'walk_url'] as $k) {
        $u = post('s_' . $k, 300);
        if ($u !== '' && !preg_match('#^https://[^\s"<>]+$#', $u)) { $error = 'Odkazy musí začínat https://'; continue; }
        $n['site'][$k] = $u;
    }
    $n['site']['instagram_handle'] = post('s_instagram_handle', 60);
    $ga = strtoupper(post('s_ga_id', 30));
    if ($ga !== '' && !preg_match('/^G-[A-Z0-9]{4,}$/', $ga)) $error = 'Měřicí ID Google Analytics má tvar G-XXXXXXXXXX.'; else $n['site']['ga_id'] = $ga;

    $n['hours'] = [];
    foreach ((array)($_POST['h_label'] ?? []) as $i => $_) {
        $label = mb_substr(trim((string)$_POST['h_label'][$i]), 0, 30);
        if ($label === '') continue;
        $o = (string)($_POST['h_open'][$i] ?? ''); $cl = (string)($_POST['h_close'][$i] ?? '');
        $days = array_values(array_unique(array_map('intval', array_filter((array)($_POST['h_days'][$i] ?? []), fn($d) => preg_match('/^[0-6]$/', (string)$d)))));
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $o) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $cl) || !$days) { $error = 'Otevírací doba „' . $label . '“: vyplňte časy a aspoň jeden den.'; continue; }
        $n['hours'][] = ['label' => $label, 'days' => $days, 'opens' => $o, 'closes' => $cl];
    }
    if (!$n['hours']) $error = 'Vyplňte aspoň jeden řádek otevírací doby.';
    $rows = function (string $a, string $b, int $max) {
        $out = [];
        foreach ((array)($_POST[$a] ?? []) as $i => $v) {
            $x = mb_substr(trim(str_replace("\r", '', (string)$v)), 0, $max); $y = mb_substr(trim(str_replace("\r", '', (string)($_POST[$b][$i] ?? ''))), 0, 900);
            if ($x !== '') $out[] = [$x, $y];
        }
        return $out;
    };
    $n['menu'] = [];   /* sections of the menu book: a section without a name or without items is dropped */
    foreach ((array)($_POST['mc'] ?? []) as $ci => $cat) {
        $cat = mb_substr(trim((string)$cat), 0, 40); if ($cat === '') continue;
        $items = [];
        foreach ((array)($_POST['mi_name'][$ci] ?? []) as $ii => $nm) {
            $nm = mb_substr(trim((string)$nm), 0, 80); if ($nm === '') continue;
            $items[] = ['name' => $nm, 'price' => mb_substr(trim((string)($_POST['mi_price'][$ci][$ii] ?? '')), 0, 30), 'note' => mb_substr(trim((string)($_POST['mi_note'][$ci][$ii] ?? '')), 0, 80)] + (!empty($_POST['mi_best'][$ci][$ii]) ? ['best' => true] : []);
        }
        if ($items) $n['menu'][] = ['cat' => $cat, 'items' => $items];
    }
    $n['faq'] = array_map(fn($r) => ['q' => $r[0], 'a' => $r[1]], $rows('f_q', 'f_a', 200));
    $n['events'] = array_map(fn($r) => ['title' => $r[0], 'date' => $r[1]], $rows('e_title', 'e_date', 120));
    $n['amenities'] = array_values(array_filter(array_map(fn($v) => mb_substr(trim((string)$v), 0, 80), (array)($_POST['a'] ?? [])), fn($v) => $v !== ''));

    if (!$error) {
        $err = save_content($dir, $n);
        if ($err) $error = 'Uložení se nepovedlo: ' . $err; else { $notice = 'Uloženo. Změny jsou na webu.'; $c = $n; }
    }
}

/* ---- change password ---- */
if ($logged && ($_POST['action'] ?? '') === 'password') {
    check_csrf($csrf);
    $new = (string)($_POST['new'] ?? '');
    if (!password_verify((string)($_POST['old'] ?? ''), PAULUS_PASS_HASH)) $error = 'Současné heslo nesouhlasí.';
    elseif (mb_strlen($new) < 10) $error = 'Nové heslo musí mít aspoň 10 znaků.';
    elseif ($new !== (string)($_POST['new2'] ?? '')) $error = 'Nová hesla se neshodují.';
    else {
        $php = "<?php\n// Heslo do admin.php (uložen jen otisk, ne heslo). Změnu proveďte v administraci.\ndefine('PAULUS_PASS_HASH', " . var_export(password_hash($new, PASSWORD_DEFAULT), true) . ");\n";
        $error = file_put_contents($dir . '/config.php.tmp', $php) !== false && rename($dir . '/config.php.tmp', $dir . '/config.php') ? '' : 'Heslo nelze uložit.';
        if (!$error) $notice = 'Heslo změněno.';
    }
}

$labels = [
  'Úvodní obrazovka' => ['hero_big1' => 'Velký text – horní řádek (hnědě, zarovnaný doprava)', 'hero_big2' => 'Velký text – spodní řádek (oranžově)', 'hero_title' => 'Hlavní nadpis vpravo dole (Enter = nový řádek) · *slovo* = oranžově', 'hero_lede' => 'Podnadpis · *věta* = oranžově', 'hero_cta' => 'Tlačítko 1 (vede na Kde nás najdete – panorama)', 'hero_cta2' => 'Tlačítko 2 (nabídka)', 'nav_call' => 'Tlačítko vpravo nahoře (zavolá na telefon z Kontaktů)'],
  'Konec cesty do kopce (panorama pod hlavičkou)' => ['kde_title' => 'Nadpis · *slovo* = oranžově', 'kde_text' => 'Text', 'kde_cta' => 'Tlačítko na mapu', 'kde_cta2' => 'Tlačítko na dopravu (mapa výletů)'],
  'Manifest (Méně spěchu…)' => ['intro_title' => 'Nadpis · *slovo* = oranžově', 'intro_text' => 'Text'],
  'Co u nás dostanete' => ['trio_title' => 'Nadpis · *slovo* = oranžově', 'card1_title' => 'Karta 1 — nadpis', 'card1_text' => 'Karta 1 — text', 'card2_title' => 'Karta 2 — nadpis', 'card2_text' => 'Karta 2 — text', 'card3_title' => 'Karta 3 — nadpis', 'card3_text' => 'Karta 3 — text'],
  'Menu' => ['menu_title' => 'Nadpis · *slovo* = oranžově', 'menu_lede' => 'Podtitul', 'menu_note' => 'Poznámka pod menu (i na stránce Co máme a na zadní straně)', 'menu_best' => 'Název stránky a tlačítka Bestseller', 'book_title' => 'Menu knížka — nápis na obálce', 'book_open' => 'Menu knížka — tlačítko na obálce', 'book_toc' => 'Menu knížka — nadpis obsahu', 'book_end' => 'Menu knížka — nápis na zadní straně', 'book_close' => 'Menu knížka — tlačítko zavřít'],
  'Vybavení (ikonky)' => ['stay_title' => 'Nadpis · *slovo* = oranžově', 'stay_text' => 'Text pod nadpisem'],
  'Kousek Kopečku s sebou' => ['offer_title' => 'Nadpis · *slovo* = oranžově', 'offer1_title' => 'Poukaz — nadpis', 'offer1_text' => 'Poukaz — text', 'offer1_cta' => 'Poukaz — tlačítko', 'offer2_title' => 'Balíček — nadpis', 'offer2_text' => 'Balíček — text', 'offer2_cta' => 'Balíček — tlačítko', 'offer3_title' => 'Akce — nadpis', 'offer3_text' => 'Akce — text', 'offer3_cta' => 'Akce — tlačítko'],
  'Kam na Kopečku (mapa)' => ['wave_divider' => 'Vlnitý text nad sekcí', 'trip_title' => 'Nadpis · *slovo* = oranžově', 'trip_text' => 'Text', 'trip_hint' => 'Mapa — nápověda pod mapou', 'trip_home_pin' => 'Mapa: kavárna — štítek v mapě', 'trip_home_title' => 'Mapa: kavárna — název', 'trip_home_meta' => 'Mapa: kavárna — tučný řádek', 'trip_home_text' => 'Mapa: kavárna — text', 'trip_tip1_pin' => 'Mapa: bazilika — štítek v mapě', 'trip_tip1_title' => 'Mapa: bazilika — název', 'trip_tip1_meta' => 'Mapa: bazilika — tučný řádek', 'trip_tip1_text' => 'Mapa: bazilika — text', 'trip_tip2_pin' => 'Mapa: ZOO — štítek v mapě', 'trip_tip2_title' => 'Mapa: ZOO — název', 'trip_tip2_meta' => 'Mapa: ZOO — tučný řádek', 'trip_tip2_text' => 'Mapa: ZOO — text', 'trip_tip3_pin' => 'Mapa: výhled — štítek v mapě', 'trip_tip3_title' => 'Mapa: výhled — název', 'trip_tip3_meta' => 'Mapa: výhled — tučný řádek', 'trip_tip3_text' => 'Mapa: výhled — text', 'trip_mhd_pin' => 'Mapa: autobus — štítek v mapě', 'trip_mhd_title' => 'Mapa: autobus — název', 'trip_mhd_meta' => 'Mapa: autobus — tučný řádek', 'trip_mhd_text' => 'Mapa: autobus — text', 'trip_mhd_cta' => 'Mapa: autobus — tlačítko', 'trip_walk_pin' => 'Mapa: pěšky a na kole — štítek v mapě', 'trip_walk_title' => 'Mapa: pěšky a na kole — název', 'trip_walk_meta' => 'Mapa: pěšky — tučný řádek', 'trip_walk_text' => 'Mapa: pěšky — text', 'trip_walk_cta' => 'Mapa: pěšky — tlačítko'],
  'Galerie a Instagram' => ['gallery_title' => 'Galerie — nadpis · *slovo* = oranžově', 'ig_title' => 'Instagram — nadpis · *slovo* = oranžově', 'ig_cta' => 'Instagram — tlačítko'],
  'Kudy k nám a FAQ' => ['visit_title' => 'Kudy k nám — nadpis · *slovo* = oranžově', 'visit_cta' => 'Tlačítko na mapu', 'faq_title' => 'FAQ — nadpis · *slovo* = oranžově'],
  'Těšíme se na vás a patička' => ['rez_title' => 'Nadpis · *slovo* = oranžově', 'rez_text' => 'Text', 'rez_call' => 'Tlačítko zavolat', 'rez_nav' => 'Tlačítko navigovat', 'rez_card' => 'Nadpis karty s otevírací dobou', 'footer_title' => 'Patička — nadpis', 'footer_note' => 'Patička — text za ©'],
];
$dayNames = ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'];
$field = function (string $name, string $label, string $value, bool $long = false) {
    $id = 'f_' . $name;
    return '<label for="' . e($id) . '">' . e($label) . '</label>' . ($long || mb_strlen($value) > 70 || str_contains($value, "\n")
        ? '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="' . max(2, min(6, (int)ceil(mb_strlen($value) / 70) + substr_count($value, "\n"))) . '">' . e($value) . '</textarea>'
        : '<input id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '">');
};
?><!DOCTYPE html>
<html lang="cs"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Úprava webu — <?= e($c['site']['name']) ?></title>
<style>
:root{--o:#FF6037;--k:#351E1C;--s:#F5F4ED;--p:#FBFAF4;--l:#dcd8c6}
*{box-sizing:border-box}body{margin:0;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--s);color:var(--k)}
.top{position:sticky;top:0;z-index:5;background:var(--k);color:var(--s);display:flex;gap:12px;align-items:center;justify-content:space-between;padding:12px 20px;flex-wrap:wrap}
.top a{color:var(--s)}main{max-width:900px;margin:0 auto;padding:24px 16px 120px}
h1{font-size:22px;margin:0}h2{font-size:19px;margin:0 0 12px}
fieldset{border:0;background:var(--p);border-radius:16px;padding:20px;margin:0 0 16px;box-shadow:0 1px 0 var(--l)}
legend{font-weight:800;font-size:18px;padding:0;margin-bottom:8px;float:left;width:100%}
label{display:block;font-weight:600;font-size:14px;margin:12px 0 4px}
input,textarea,select{width:100%;font:inherit;padding:10px 12px;border:2px solid var(--l);border-radius:10px;background:#fff;color:var(--k)}
input:focus,textarea:focus{outline:none;border-color:var(--k)}
.grid{display:grid;grid-template-columns:2fr 1fr;gap:8px 12px;align-items:end}.grid3{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:8px 12px}
.days{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}.days label{display:inline-flex;gap:4px;align-items:center;margin:0;font-weight:500;background:#fff;border:2px solid var(--l);border-radius:8px;padding:6px 10px}
.days input{width:auto}.row{border-top:1px solid var(--l);padding-top:10px;margin-top:10px}
.btn{display:inline-block;border:0;border-radius:999px;padding:12px 22px;font:inherit;font-weight:700;cursor:pointer;background:var(--k);color:var(--s)}
.btn--o{background:var(--o);color:var(--k)}.save{position:fixed;left:0;right:0;bottom:0;background:rgba(245,244,237,.95);padding:12px 16px;text-align:center;box-shadow:0 -1px 0 var(--l)}
.msg{padding:12px 16px;border-radius:12px;margin:0 0 16px;font-weight:600}.ok{background:#dff0e3}.err{background:#fde2dc}
.msec{border:1.5px solid #e2d8cf;border-radius:14px;padding:12px 14px 14px;margin-top:12px}.grid3{display:grid;grid-template-columns:2fr 1fr 2fr auto;gap:10px;align-items:end}.best{display:flex;align-items:center;gap:6px;white-space:nowrap;padding-bottom:10px;font-weight:600}@media (max-width:600px){.grid3{grid-template-columns:1fr 1fr}}
.hint{font-size:13px;color:#6b5652;margin:4px 0 0}.login{max-width:380px;margin:12vh auto;background:var(--p);padding:28px;border-radius:18px}
@media(max-width:600px){.grid,.grid3{grid-template-columns:1fr}}
.ph{display:grid;grid-template-columns:180px 1fr;gap:16px;align-items:start}.ph__thumb{background:var(--l);border-radius:10px;overflow:hidden;display:grid;place-items:center;color:#6b5652;font-size:13px}
.ph__thumb img{width:100%;height:100%;object-fit:cover;display:block}@media(max-width:600px){.ph{grid-template-columns:1fr}}
</style></head><body>
<?php if (!$logged): ?>
<form class="login" method="post">
  <h1>Úprava webu</h1><p class="hint"><?= e($c['site']['name']) ?></p>
  <?php if ($error): ?><p class="msg err"><?= e($error) ?></p><?php endif ?>
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="login">
  <label for="pw">Heslo</label><input id="pw" type="password" name="password" autocomplete="current-password" required autofocus>
  <p style="margin-top:16px"><button class="btn btn--o">Přihlásit</button></p>
</form>
<?php else: ?>
<div class="top"><h1>Úprava webu — <?= e($c['site']['name']) ?></h1>
  <span><a href="./" target="_blank" rel="noopener">Zobrazit web ↗</a>
  <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout"> <button class="btn btn--o" style="padding:8px 16px">Odhlásit</button></form></span></div>
<main>
<?php if ($notice): ?><p class="msg ok"><?= e($notice) ?></p><?php endif ?>
<?php if ($error): ?><p class="msg err"><?= e($error) ?></p><?php endif ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save">

<fieldset><legend>Kontakt</legend>
  <?= $field('c_street', 'Ulice a číslo', $c['contact']['street']) ?>
  <div class="grid"><div><?= $field('c_city', 'Obec', $c['contact']['city']) ?></div><div><?= $field('c_zip', 'PSČ', $c['contact']['zip']) ?></div></div>
  <div class="grid"><div><?= $field('c_email', 'E-mail', $c['contact']['email']) ?></div><div><?= $field('c_phone', 'Telefon', $c['contact']['phone']) ?></div></div>
</fieldset>

<fieldset><legend>Otevírací doba</legend><p class="hint">Řádek smažete vymazáním jeho názvu. Mění se automaticky i na Googlu a v odpovědi v dotazech.</p>
<?php foreach (array_merge($c['hours'], [['label' => '', 'days' => [], 'opens' => '', 'closes' => '']]) as $i => $h): ?>
  <div class="row"><div class="grid3">
    <div><label>Název řádku</label><input name="h_label[<?= $i ?>]" value="<?= e($h['label']) ?>" placeholder="např. Po–Pá"></div>
    <div><label>Otevíráme</label><input type="time" name="h_open[<?= $i ?>]" value="<?= e($h['opens']) ?>"></div>
    <div><label>Zavíráme</label><input type="time" name="h_close[<?= $i ?>]" value="<?= e($h['closes']) ?>"></div></div>
    <div class="days"><?php foreach ([1, 2, 3, 4, 5, 6, 0] as $d): ?><label><input type="checkbox" name="h_days[<?= $i ?>][]" value="<?= $d ?>" <?= in_array($d, $h['days'], true) ? 'checked' : '' ?>> <?= $dayNames[$d] ?></label><?php endforeach ?></div>
  </div>
<?php endforeach ?>
</fieldset>

<fieldset><legend>Menu (lístek jako knížka)</legend><p class="hint">Každá sekce je jedna stránka knížky (Káva, Nealko, Pečivo…). Položku smažete vymazáním názvu, sekci vymazáním jejího názvu. Prázdná sekce dole slouží k přidání nové. Poznámka je nepovinná (např. „sezónní zrno“). Zaškrtnutím <b>Bestseller</b> se položka objeví i na první stránce „Bestseller“ (oranžové tlačítko nad knížkou) a dostane oranžový štítek.</p>
<?php $msecs = (isset($c['menu'][0]['name'])) ? [['cat' => 'Menu', 'items' => $c['menu']]] : $c['menu'];
foreach (array_merge($msecs, [['cat' => '', 'items' => []]]) as $ci => $sec): ?>
  <div class="msec"><label>Sekce</label><input name="mc[<?= $ci ?>]" value="<?= e($sec['cat']) ?>" placeholder="Např. Zákusky">
  <?php foreach (array_merge($sec['items'], array_fill(0, 3, ['name' => '', 'price' => '', 'note' => ''])) as $ii => $m): ?>
    <div class="grid3"><div><label>Položka</label><input name="mi_name[<?= $ci ?>][<?= $ii ?>]" value="<?= e($m['name']) ?>"></div><div><label>Cena</label><input name="mi_price[<?= $ci ?>][<?= $ii ?>]" value="<?= e($m['price']) ?>" placeholder="65 Kč"></div><div><label>Poznámka</label><input name="mi_note[<?= $ci ?>][<?= $ii ?>]" value="<?= e($m['note'] ?? '') ?>"></div><label class="best"><input type="checkbox" name="mi_best[<?= $ci ?>][<?= $ii ?>]" value="1" <?= !empty($m['best']) ? 'checked' : '' ?>> Bestseller</label></div>
  <?php endforeach ?>
  </div>
<?php endforeach ?>
</fieldset>

<fieldset><legend>Vyhlídka, terasa a klid — hesla</legend>
<p class="hint">Každé heslo je jedna pilulka. Text v závorce na konci se ukáže menším písmem pod heslem, např. „Psi vítáni (miska s vodou samozřejmostí)“.</p>
<?php foreach (array_merge($c['amenities'], ['', '']) as $i => $a): ?><input name="a[<?= $i ?>]" value="<?= e($a) ?>" style="margin-top:8px"><?php endforeach ?>
</fieldset>

<fieldset><legend>Akce</legend><p class="hint">Zobrazí se v kartě Akce. Prázdné = karta jen s textem.</p>
<?php foreach (array_merge($c['events'], array_fill(0, 3, ['title' => '', 'date' => ''])) as $i => $ev): ?>
  <div class="grid"><div><label>Název akce</label><input name="e_title[<?= $i ?>]" value="<?= e($ev['title']) ?>"></div><div><label>Datum</label><input name="e_date[<?= $i ?>]" value="<?= e($ev['date']) ?>" placeholder="12. 6."></div></div>
<?php endforeach ?>
</fieldset>

<fieldset><legend>Časté dotazy</legend><p class="hint">Do odpovědi můžete vložit <b>{otevreno}</b> — doplní se aktuální otevírací doba.</p>
<?php foreach (array_merge($c['faq'], array_fill(0, 2, ['q' => '', 'a' => ''])) as $i => $f): ?>
  <div class="row"><label>Otázka</label><input name="f_q[<?= $i ?>]" value="<?= e($f['q']) ?>"><label>Odpověď</label><textarea name="f_a[<?= $i ?>]" rows="2"><?= e($f['a']) ?></textarea></div>
<?php endforeach ?>
</fieldset>

<?php foreach ($labels as $group => $keys): ?>
<fieldset><legend><?= e($group) ?></legend>
  <?php foreach ($keys as $k => $lab) echo $field('t_' . $k, $lab, (string)($c['texts'][$k] ?? '')); ?>
</fieldset>
<?php endforeach ?>

<fieldset><legend>Vyhledávače a sdílení</legend>
  <?= $field('seo_title', 'Titulek stránky (do 60 znaků)', $c['seo']['title']) ?>
  <?= $field('seo_description', 'Popis pro Google (120–160 znaků)', $c['seo']['description'], true) ?>
  <?= $field('seo_og_title', 'Titulek při sdílení', $c['seo']['og_title']) ?>
  <?= $field('seo_og_description', 'Popis při sdílení', $c['seo']['og_description'], true) ?>
</fieldset>

<fieldset><legend>Odkazy a měření</legend>
  <?= $field('s_name', 'Název kavárny', $c['site']['name']) ?>
  <?= $field('s_url', 'Adresa webu', $c['site']['url']) ?>
  <div class="grid"><div><?= $field('s_instagram_url', 'Instagram (odkaz)', $c['site']['instagram_url']) ?></div><div><?= $field('s_instagram_handle', 'Instagram (jméno)', $c['site']['instagram_handle']) ?></div></div>
  <?= $field('s_facebook_url', 'Facebook (odkaz)', $c['site']['facebook_url']) ?>
  <?= $field('s_mhd_url', 'Odkaz „Najít spojení“ (MHD)', $c['site']['mhd_url'] ?? '') ?>
  <?= $field('s_walk_url', 'Odkaz „Trasa na Mapy.cz“', $c['site']['walk_url'] ?? '') ?>
  <?= $field('s_ga_id', 'Google Analytics — měřicí ID (G-…). Prázdné = neměří se a lišta cookies se nezobrazí.', $c['site']['ga_id']) ?>
</fieldset>
<div class="save"><button class="btn btn--o">Uložit změny</button></div>
</form>

<fieldset id="fotky"><legend>Fotky</legend>
  <p class="hint">Fotku stačí vybrat z mobilu nebo počítače — ořízne se na správný poměr a uloží v několika velikostech, aby web zůstal rychlý. Popis pomáhá nevidomým i vyhledávačům.</p>
  <?php foreach (PAULUS_PHOTOS as $slot => $spec): $p = $c['photos'][$slot] ?? null; ?>
  <form class="row photo-form" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="photo"><input type="hidden" name="slot" value="<?= e($slot) ?>">
    <div class="ph">
      <div class="ph__thumb" style="aspect-ratio:<?= $spec['r'][0] ?>/<?= $spec['r'][1] ?>"><?php if ($p): ?><img src="foto-<?= e($slot) ?>-<?= (int)min($p['w']) ?>.<?= e($p['id']) ?>.webp" alt=""><?php else: ?><span>bez fotky</span><?php endif ?></div>
      <div class="ph__body">
        <strong><?= e($spec['label']) ?></strong>
        <label>Fotka</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/heic,image/*">
        <label>Popis fotky</label><input name="alt" value="<?= e($p['alt'] ?? '') ?>" placeholder="např. Bar s espresso kávovarem a vitrínou s croissanty">
        <p style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn--o">Uložit fotku</button>
        <?php if ($p): ?><button class="btn" name="action" value="photo_delete" onclick="return confirm('Smazat tuto fotku?')">Smazat</button><?php endif ?></p>
        <p class="hint ph__msg" role="status"></p>
      </div>
    </div>
  </form>
  <?php endforeach ?>
</fieldset>

<form method="post"><fieldset><legend>Změna hesla</legend>
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="password">
  <div class="grid3"><div><label>Současné heslo</label><input type="password" name="old" autocomplete="current-password"></div>
  <div><label>Nové heslo (10+ znaků)</label><input type="password" name="new" autocomplete="new-password"></div>
  <div><label>Nové heslo znovu</label><input type="password" name="new2" autocomplete="new-password"></div></div>
  <p style="margin-top:12px"><button class="btn">Změnit heslo</button></p>
</fieldset></form>
</main>
<script>
/* photos: shrink big phone photos in the browser before upload (keeps under hosting upload limits, applies EXIF rotation) */
document.querySelectorAll('.photo-form').forEach(function (f) {
  f.addEventListener('submit', function (e) {
    var file = f.foto.files[0], del = e.submitter && e.submitter.value === 'photo_delete';
    if (del || !file || !window.createImageBitmap || !window.fetch) return;   /* normal form post */
    e.preventDefault();
    var msg = f.querySelector('.ph__msg'), btn = f.querySelector('.btn--o'); btn.disabled = true; msg.textContent = 'Zpracovávám fotku…';
    createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
      var k = Math.min(1, 3200 / Math.max(bmp.width, bmp.height)), cv = document.createElement('canvas');
      cv.width = Math.round(bmp.width * k); cv.height = Math.round(bmp.height * k);
      cv.getContext('2d').drawImage(bmp, 0, 0, cv.width, cv.height);
      return new Promise(function (res) { cv.toBlob(res, 'image/jpeg', .92); });
    }).then(function (blob) {
      var fd = new FormData(f); fd.set('foto', blob, 'foto.jpg'); msg.textContent = 'Nahrávám…';
      return fetch('admin.php', { method: 'POST', body: fd, headers: { 'X-Paulus': 'fetch' }, credentials: 'same-origin' });
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.ok) location.href = 'admin.php?foto=ok#fotky'; else { msg.textContent = j.error || 'Nahrání se nepovedlo.'; btn.disabled = false; }
    }).catch(function () { btn.disabled = false; msg.textContent = 'Tenhle formát prohlížeč neumí zpracovat — zkouším nahrát originál…'; f.submit(); });
  });
});
</script>
<?php endif ?>
</body></html>
