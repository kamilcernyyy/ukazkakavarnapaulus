<?php
/**
 * Kavárna Paulus — renderer.
 * Builds index.html from index.tpl.html + content.json.
 * Used by the build (CLI: php render.php) and by admin.php after saving.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('PAULUS_ADMIN')) { http_response_code(404); exit; }

/* photo slots: aspect ratio, output widths, sizes attribute, label (editor) */
const PAULUS_PHOTOS = [
    'g1' => ['r' => [4, 5], 'w' => [400, 600, 800, 1200, 1600], 'sizes' => '(max-width: 720px) 86vw, (max-width: 1240px) 31vw, 380px', 'label' => 'Galerie — fotka 1 (na výšku 4:5)'],
    'g2' => ['r' => [4, 5], 'w' => [400, 600, 800, 1200, 1600], 'sizes' => '(max-width: 720px) 86vw, (max-width: 1240px) 31vw, 380px', 'label' => 'Galerie — fotka 2 (na výšku 4:5)'],
    'g3' => ['r' => [4, 5], 'w' => [400, 600, 800, 1200, 1600], 'sizes' => '(max-width: 720px) 86vw, (max-width: 1240px) 31vw, 380px', 'label' => 'Galerie — fotka 3 (na výšku 4:5)'],
    'visit' => ['r' => [4, 3], 'w' => [480, 800, 1200, 1600], 'sizes' => '(max-width: 980px) 92vw, 600px', 'label' => 'Vchod do kavárny (4:3)'],
    'ig1' => ['r' => [1, 1], 'w' => [240, 360, 480, 720], 'sizes' => '(max-width: 720px) 31vw, (max-width: 1240px) 31vw, 400px', 'label' => 'Instagram 1 (čtverec)'],
    'ig2' => ['r' => [1, 1], 'w' => [240, 360, 480, 720], 'sizes' => '(max-width: 720px) 31vw, (max-width: 1240px) 31vw, 400px', 'label' => 'Instagram 2 (čtverec)'],
    'ig3' => ['r' => [1, 1], 'w' => [240, 360, 480, 720], 'sizes' => '(max-width: 720px) 31vw, (max-width: 1240px) 31vw, 400px', 'label' => 'Instagram 3 (čtverec)'],
];

/** responsive <picture> for a slot, or the placeholder while no photo is uploaded */
function paulus_photo(array $c, string $slot): string {
    $spec = PAULUS_PHOTOS[$slot] ?? null; $p = $c['photos'][$slot] ?? null;
    $ratio = $spec ? $spec['r'][0] . '/' . $spec['r'][1] : '1/1';
    $cls = ['16/9' => 'photo--169', '4/3' => 'photo--43', '1/1' => 'photo--11', '4/5' => 'photo--45'][$ratio] ?? '';
    if (!$spec || !$p || empty($p['w'])) {
        if (str_starts_with($slot, 'ig')) return '<svg aria-hidden="true"><use href="#cam"/></svg>';
        if ($slot === 'visit') return '';   /* no photo yet: the drawn café front next to it stays visible */
        $label = $slot === 'visit' ? 'Místo pro fotografii vchodu do kavárny' : 'Místo pro fotografii ' . substr($slot, 1);
        return '<div class="photo ' . $cls . '" role="img" aria-label="' . paulus_e($label) . '">Foto — ' . str_replace('/', ':', $ratio) . '</div>';
    }
    $set = fn(string $ext) => implode(', ', array_map(fn($w) => 'foto-' . $slot . '-' . $w . '.' . $p['id'] . '.' . $ext . ' ' . $w . 'w', $p['w']));
    $big = max($p['w']); $h = (int)round($big * $spec['r'][1] / $spec['r'][0]);
    $mid = $p['w'][(int)floor((count($p['w']) - 1) / 2)];
    return '<picture class="pic ' . $cls . '">' . (!empty($p['avif']) ? '<source type="image/avif" srcset="' . $set('avif') . '" sizes="' . $spec['sizes'] . '">' : '')
         . '<img src="foto-' . $slot . '-' . $mid . '.' . $p['id'] . '.webp" srcset="' . $set('webp') . '" sizes="' . $spec['sizes'] . '" width="' . $big . '" height="' . $h
         . '" loading="lazy" decoding="async" alt="' . paulus_e($p['alt'] ?? '') . '"></picture>';
}

/* Big animated amenity drawings (same line language as the rest of the site: one brown line, paper, orange, aqua, sand).
   The keyword decides the drawing, so the owner can rename the amenities in admin and keep the right picture. */
function paulus_amen_art(string $a): string {
    $t = mb_strtolower($a);
    if (preg_match('/bezbari|vozí|kočár/u', $t)) $i =
        '<path d="M8 196h24M2 220h28M12 244h18" class="wc-lines t5"/>'
      . '<path d="M204 262V92h64v170" class="a"/><g class="wc-door"><path d="M210 262V98h52v164z" class="o t5"/><circle cx="222" cy="184" r="5" class="k" stroke="none"/></g><path d="M196 262h80"/>'
      . '<g transform="translate(-14 0)"><g class="wc-move">'
      . '<path d="M92 168c-2-28 2-50 14-60 10-8 26-6 32 6 4 10 2 30 0 54z" class="a"/>'
      . '<path d="M120 150h46c8 0 12 5 12 12v66h-20v-56h-38z" class="o"/><path d="M152 228h30c5 0 8 3 8 8H152z" class="k t4"/>'
      . '<circle cx="118" cy="80" r="20" class="s"/><path d="M100 74c2-14 12-20 22-18 8 1 14 6 16 14-12 0-26 0-38 4z" class="k t4"/>'
      . '<path d="M66 106h18l6 62h70l6 70h26" />'
      . '<g class="wc-cast"><circle cx="168" cy="250" r="12" class="p t5"/><path d="M168 242v16" class="t3"/></g>'
      . '<g class="wc-wheel"><circle cx="104" cy="212" r="50" class="s"/><circle cx="104" cy="212" r="38" class="t4"/><path d="M104 174v76M66 212h76M77 185l54 54M131 185l-54 54" class="t3"/><circle cx="104" cy="212" r="8" class="k t3"/></g>'
      . '<path d="M126 116c10 18 12 40 6 58" class="t5"/><circle cx="132" cy="178" r="7" class="s t4"/>'
      . '</g></g>';
    elseif (preg_match('/ps[iyí]|pej|zvíř|parťá/u', $t)) $i =
        '<path d="M178 262l8-30h76l8 30z" class="o"/><ellipse cx="224" cy="232" rx="38" ry="7" class="k t4"/>'
      . '<path class="d-tail" d="M66 248c-24-4-34-22-26-40"/>'
      . '<path d="M60 262c-4-46 10-86 46-96 26-8 48 8 50 34 2 22-6 40-2 62z" class="s"/>'
      . '<path d="M134 262v-48M152 262v-40"/><path d="M30 262h240"/>'
      . '<g class="d-head"><path d="M100 118c0-26 20-42 44-42 22 0 40 14 40 34 0 10-4 18-12 22l-4 10c-6 10-20 14-34 12-20-2-34-16-34-36z" class="s"/>'
      . '<path class="d-ear k t4" d="M116 84c-16 2-24 26-14 46 12-4 18-22 14-46z"/>'
      . '<circle cx="146" cy="104" r="5" class="k" stroke="none"/><ellipse cx="180" cy="110" rx="7" ry="6" class="k t3"/>'
      . '<path class="d-tongue o" d="M166 136c0 12 13 14 15 1" stroke-width="4"/><path d="M160 134q10 5 20-2" class="t4"/></g>'
      . '<path d="M118 158c14 8 30 8 42 0" class="o t5"/>';
    elseif (preg_match('/kart|plat|hotov/u', $t)) $i =
        '<path d="M30 262h240"/>'
      . '<rect x="56" y="128" width="108" height="134" rx="18" class="p"/><rect x="74" y="146" width="72" height="44" rx="8" class="a t5"/>'
      . '<path class="pay-ok t5" d="M96 168l10 10 18-18"/>'
      . '<g class="k" stroke="none"><circle cx="86" cy="212" r="5.5"/><circle cx="110" cy="212" r="5.5"/><circle cx="134" cy="212" r="5.5"/><circle cx="86" cy="236" r="5.5"/><circle cx="110" cy="236" r="5.5"/><circle cx="134" cy="236" r="5.5"/></g>'
      . '<g class="pay-wave t5"><path d="M172 92a20 20 0 0 1 0 28"/><path d="M184 80a36 36 0 0 1 0 52"/></g>'
      . '<g class="pay-card"><g transform="rotate(-10 110 72)"><rect x="64" y="42" width="92" height="60" rx="10" class="o t5"/><rect x="76" y="58" width="22" height="16" rx="4" class="s t4"/><path d="M76 88h40" class="t4"/></g></g>'
      . '<rect x="196" y="248" width="64" height="14" rx="7" class="s t5"/><rect x="200" y="234" width="64" height="14" rx="7" class="s t5"/><rect x="194" y="220" width="64" height="14" rx="7" class="s t5"/>'
      . '<g class="pay-coin"><g class="pay-flip"><circle cx="226" cy="76" r="24" class="s t5"/><circle cx="226" cy="76" r="14" class="t4"/></g></g>';
    elseif (preg_match('/teras|výhled|venku|zahrád/u', $t)) $i =
        '<g class="tr-sun"><circle cx="246" cy="58" r="18" class="s t5"/><path d="M246 26v-10M246 100v-10M214 58h-10M288 58h-10M223 35l-7-7M276 88l-7-7M269 35l7-7M216 88l7-7" class="t4"/></g>'
      . '<path d="M30 232c26-10 52-12 76-6M246 224c10-3 18-2 24 1" class="t4"/>'
      . '<g class="tr-umb"><path d="M120 266V80"/><path d="M36 120c12-40 46-62 84-62s72 22 84 62c-14-8-28-8-42 0-12-8-26-8-42 0-14-8-28-8-42 0-14-8-28-8-42 0z" class="o"/><path d="M78 120c4-30 20-52 42-62M162 120c-4-30-20-52-42-62" class="t4"/></g>'
      . '<path d="M150 210h90M164 210v52M226 210v52"/><ellipse cx="195" cy="210" rx="48" ry="8" class="p"/>'
      . '<g class="am-steam t3"><path d="M186 170c-6-8 6-12 0-20s5-12 0-18"/><path d="M200 170c-6-8 6-12 0-20s5-12 0-18"/></g><path d="M180 186h26c0 14-6 22-13 22s-13-8-13-22z" class="a t4"/>'
      . '<path d="M30 266h240"/>';
    else $i =
        '<path d="M30 262h240"/><ellipse cx="150" cy="246" rx="96" ry="16" class="p"/>'
      . '<path d="M216 168c36-2 34 52-6 50"/><path d="M84 150h132c0 56-28 92-66 92s-66-36-66-92z" class="a"/><ellipse cx="150" cy="150" rx="66" ry="12" class="s t5"/>'
      . '<g class="am-steam t4"><path d="M126 120c-10-12 10-20 0-32s10-18 0-28"/><path d="M152 116c-10-12 10-20 0-32s10-18 0-28"/><path d="M178 120c-10-12 10-20 0-32s10-18 0-28"/></g>';
    return '<svg class="amx" viewBox="0 0 300 300" aria-hidden="true" focusable="false"><g class="l">' . $i . '</g></svg>';
}

/* "Psí parťáci vítáni (miska s vodou samozřejmostí)" → heading + the bracket as a small line under it */
function paulus_amen_label(string $a): string {
    if (preg_match('/^(.*?)\s*\((.+)\)\s*$/u', $a, $m)) return '<h3>' . paulus_e($m[1]) . '</h3><p>' . paulus_e($m[2]) . '</p>';
    return '<h3>' . paulus_e($a) . '</h3>';
}
function paulus_amen_pill(string $a): string {
    $name = paulus_amen_name($a);
    $det = preg_match('/\(([^)]*)\)\s*$/u', $a, $m) ? trim($m[1]) : '';
    return '<span class="amen-pill__n">' . paulus_e($name) . '</span>' . ($det !== '' ? '<span class="amen-pill__d">' . paulus_e($det) . '</span>' : '');
}
function paulus_amen_name(string $a): string { return trim(preg_replace('/\s*\(.*\)\s*$/u', '', $a)); }

/* menu data: [{cat, items:[{name, price, note}]}]; the older flat list [{name, price}] still works (one section) */
function paulus_menu_sections(array $menu): array {
    if ($menu && isset($menu[0]['name'])) return [['cat' => 'Menu', 'items' => $menu]];
    return array_values(array_filter($menu, fn($s) => trim($s['cat'] ?? '') !== '' && !empty($s['items'])));
}
/* a small line drawing on top of each menu page, chosen by the section name */
function paulus_menu_icon(string $cat): string {
    $t = mb_strtolower($cat);
    if (preg_match('/nealk|limo|čaj|nápoj|džus|fresh/u', $t)) $i = '<path d="M34 12h28l-4 54H38z" class="c-teal"/><path d="M36 30h24" stroke-width="2.6"/><path d="M52 4l-6 30" stroke-width="3"/><circle cx="62" cy="14" r="9" class="c-yellow"/><path d="M62 5v18M53 14h18" stroke-width="2"/><circle cx="45" cy="46" r="2.4" class="fill-paper" stroke-width="2"/><circle cx="51" cy="56" r="2" class="fill-paper" stroke-width="2"/>';
    elseif (preg_match('/peč|croiss|snída/u', $t)) $i = '<path d="M14 52c-4-16 8-30 22-31 3 9 3 22-2 33-8 2-15 1-20-2zM82 52c4-16-8-30-22-31-3 9-3 22 2 33 8 2 15 1 20-2z" class="c-gold"/><path d="M34 22c4-7 9-10 14-10s10 3 14 10c2 12 1 26-4 36-3 2-6 3-10 3s-7-1-10-3c-5-10-6-24-4-36z" class="c-yellow"/><path d="M44 18c-3 14-3 28 1 42M52 18c3 14 3 28-1 42" stroke-width="2.4"/><path d="M8 66h80" stroke-width="3"/>';
    elseif (preg_match('/zákus|dort|sladk|dezert/u', $t)) $i = '<path d="M14 58L74 34v24z" class="c-sand"/><path d="M14 58L74 34v8L14 66z" class="c-orange"/><path d="M14 58v8h60v-24" class="c-sand"/><path d="M14 66h60" /><path d="M30 52c6-2 10-4 14-6M46 46c6-2 10-4 14-6" stroke-width="2.4"/><circle cx="66" cy="26" r="7" class="fill-orange"/><path d="M66 19c0-6 4-10 8-11" stroke-width="2.4"/><path d="M8 70h80" stroke-width="3"/>';
    elseif (preg_match('/slan|toast|sendvi|bistro|jídl/u', $t)) $i = '<path d="M12 40l36-22 36 22-36 14z" class="c-yellow"/><path d="M12 40v6l36 14 36-14v-6" class="c-green"/><path d="M12 46v6l36 14 36-14v-6" class="c-orange"/><path d="M12 52v4l36 14 36-14v-4" class="c-yellow"/><path d="M30 34l2 1M44 28l2 1M58 32l2 1M44 40l2 1" stroke-width="3"/>';
    else $i = '<path d="M20 30h48c0 20-10 32-24 32S20 50 20 30z" class="c-teal"/><path d="M68 36c10 0 10 16 0 16" /><path d="M10 66h68" stroke-width="3"/><path d="M34 22c-4-5 4-8 0-13M46 22c-4-5 4-8 0-13M58 22c-4-5 4-8 0-13" stroke-width="2.6"/>';
    return '<svg class="lst__ic" viewBox="0 0 96 72" aria-hidden="true" focusable="false"><g class="line" stroke-width="3.2">' . $i . '</g></svg>';
}

/* manifesto: plain text with small drawings slipped in after a few key words (first match each) */
function paulus_manifest(string $txt): string { return paulus_e($txt); }   /* plain text (the little inline drawings were dropped) */

const PAULUS_DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function paulus_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); }
function paulus_json($v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}
function paulus_hm(string $t): string { return (string)(int)substr($t, 0, 2) . ':' . substr($t, 3, 2); }

function paulus_hours_sentence(array $c): string {
    $parts = [];
    foreach ($c['hours'] as $h) $parts[] = $h['label'] . ' ' . paulus_hm($h['opens']) . '–' . paulus_hm($h['closes']);
    return implode(', ', $parts);
}

function paulus_render(array $c, string $tpl, array $assets = []): string {
    $t = $c['texts'];
    $site = $c['site'];
    $url = rtrim($site['url'], '/') . '/';
    $tel = preg_replace('/[^0-9+]/', '', $c['contact']['phone']);
    $hoursSentence = paulus_hours_sentence($c);

    /* ---- computed blocks ---- */
    $hoursHtml = '';
    foreach ($c['hours'] as $h) {
        $d = implode(',', array_map('intval', $h['days']));
        $hoursHtml .= '<span data-days="' . $d . '">' . paulus_e($h['label']) . '</span>'
                    . '<span data-days="' . $d . '">' . paulus_hm($h['opens']) . '–' . paulus_hm($h['closes']) . '</span>';
    }
    /* menu: sections (Káva, Nealko…) — rendered as a plain readable list; site.js turns it into the flip book */
    $menuHtml = ''; $menuLd = []; $secN = 0;
    /* "Bestseller" page: every item ticked in admin, collected onto its own first page (not repeated in the schema) */
    $best = [];
    foreach (paulus_menu_sections($c['menu']) as $sec) foreach ($sec['items'] as $m) if (!empty($m['best']) && trim($m['name'] ?? '') !== '') $best[] = $m + ['from' => $sec['cat']];
    if ($best) {
        $menuHtml .= '<section class="lst__sec lst__sec--best" id="lst-best" aria-labelledby="lst-best-h" data-cat="' . paulus_e($t['menu_best'] ?? 'Bestseller') . '" data-best>'
                   . '<h3 class="lst__h" id="lst-best-h">' . paulus_e($t['menu_best'] ?? 'Bestseller') . '</h3><ul class="lst__list">';
        foreach ($best as $m) {
            $note = trim($m['note'] ?? '') ?: $m['from'];
            $menuHtml .= '<li><span class="menu__name">' . paulus_e($m['name']) . '</span><span class="menu__dots" aria-hidden="true"></span>'
                       . '<span class="menu__price">' . paulus_e($m['price']) . '</span><span class="lst__note">' . paulus_e($note) . '</span></li>';
        }
        $menuHtml .= '</ul></section>';
    }
    foreach (paulus_menu_sections($c['menu']) as $sec) {
        $items = array_values(array_filter($sec['items'], fn($m) => trim($m['name'] ?? '') !== ''));
        if (!$items) continue;
        $secN++; $sid = 'lst-' . $secN;
        $menuHtml .= '<section class="lst__sec" id="' . $sid . '" aria-labelledby="' . $sid . '-h" data-cat="' . paulus_e($sec['cat']) . '">'
                   . '<h3 class="lst__h" id="' . $sid . '-h">' . paulus_e($sec['cat']) . '</h3><ul class="lst__list">';
        $ldItems = [];
        foreach ($items as $m) {
            $note = trim($m['note'] ?? '');
            $menuHtml .= '<li><span class="menu__name">' . paulus_e($m['name']) . (!empty($m['best']) ? ' <span class="lst__best">' . paulus_e($t['menu_best'] ?? 'Bestseller') . '</span>' : '') . '</span><span class="menu__dots" aria-hidden="true"></span>'
                       . '<span class="menu__price">' . paulus_e($m['price']) . '</span>' . ($note !== '' ? '<span class="lst__note">' . paulus_e($note) . '</span>' : '') . '</li>';
            $it = ['@type' => 'MenuItem', 'name' => $m['name']];
            if ($note !== '') $it['description'] = $note;
            if (preg_match('/(\d+)/', (string)$m['price'], $pm)) $it['offers'] = ['@type' => 'Offer', 'price' => $pm[1], 'priceCurrency' => 'CZK'];
            $ldItems[] = $it;
        }
        $menuHtml .= '</ul></section>';
        $menuLd[] = ['@type' => 'MenuSection', 'name' => $sec['cat'], 'hasMenuItem' => $ldItems];
    }
    $amenArt = '';
    foreach ($c['amenities'] as $a) if (trim($a) !== '')
        $amenArt .= '<li class="amen-pill">' . paulus_amen_pill($a) . '</li>';
    $faqHtml = '';
    $faqLd = [];
    foreach ($c['faq'] as $f) {
        if (trim($f['q']) === '') continue;
        $a = str_replace('{otevreno}', $hoursSentence, $f['a']);
        $faqHtml .= '<details><summary><h3>' . paulus_e($f['q']) . '</h3></summary><p>' . paulus_e($a) . '</p></details>';
        $faqLd[] = ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
    }
    $eventsHtml = '';
    foreach ($c['events'] as $ev) {
        if (trim($ev['title'] ?? '') === '') continue;
        $eventsHtml .= '<li><span class="ev__date">' . paulus_e($ev['date'] ?? '') . '</span><span class="ev__title">' . paulus_e($ev['title']) . '</span></li>';
    }
    if ($eventsHtml !== '') $eventsHtml = '<ul class="events">' . $eventsHtml . '</ul>';

    $sameAs = array_values(array_filter([$site['instagram_url'], $site['facebook_url']], fn($u) => $u !== '' && !preg_match('#^https?://(www\.)?(instagram|facebook)\.com/?$#', $u)));
    $pr = []; foreach (paulus_menu_sections($c['menu']) as $sec) foreach ($sec['items'] as $m) if (preg_match('/(\d+)/', (string)($m['price'] ?? ''), $pm)) $pr[] = (int)$pm[1];
    $priceRange = $pr ? min($pr) . '–' . max($pr) . ' Kč' : '';
    $cafe = [
        '@context' => 'https://schema.org',
        '@type' => 'CafeOrCoffeeShop',
        'name' => $site['name'],
        'description' => $c['seo']['description'],
        'url' => $url,
        'image' => $url . ($assets['og-image.jpg'] ?? 'og-image.jpg'),
        'logo' => $url . ($assets['logo.svg'] ?? 'logo.svg'),
        'telephone' => $c['contact']['phone'],
        'email' => $c['contact']['email'],
        'priceRange' => $priceRange,
        'currenciesAccepted' => 'CZK',
        'paymentAccepted' => 'Hotovost, platební karta, Apple Pay, Google Pay',
        'servesCuisine' => ['Káva', 'Snídaně', 'Pečivo'],
        'acceptsReservations' => true,
        'hasMenu' => ['@type' => 'Menu', 'name' => 'Menu', 'hasMenuSection' => $menuLd],
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => $c['contact']['street'], 'addressLocality' => $c['contact']['city'],
                      'postalCode' => $c['contact']['zip'], 'addressCountry' => 'CZ'],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 49.6337, 'longitude' => 17.3357],
        'openingHoursSpecification' => array_map(fn($h) => ['@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => array_map(fn($d) => PAULUS_DAYS[(int)$d], $h['days']), 'opens' => $h['opens'], 'closes' => $h['closes']], $c['hours']),
        'amenityFeature' => array_values(array_map(fn($a) => ['@type' => 'LocationFeatureSpecification', 'name' => paulus_amen_name($a), 'value' => true],
            array_filter($c['amenities'], fn($a) => trim($a) !== ''))),
        'containedInPlace' => ['@type' => 'Place', 'name' => 'Svatý Kopeček, Olomouc'],
    ];
    if ($sameAs) $cafe['sameAs'] = $sameAs;
    $faqBlock = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqLd];

    $raw = [
        'url' => paulus_e($url),
        'tel' => paulus_e($tel),
        'year' => date('Y'),
        'maps_url' => paulus_e('https://mapy.cz/zakladni?q=' . rawurlencode($site['name'] . ' Svatý Kopeček')),
        'hours_html' => $hoursHtml,
        'hours_sentence' => paulus_e($hoursSentence),
        /* the big header lines are sized from the longer one, so any text typed in admin fills the width and never overflows */
        'hero_big_n' => (string)max(6, mb_strlen(trim((string)($t['hero_big1'] ?? ''))), mb_strlen(trim((string)($t['hero_big2'] ?? '')))),
        'menu_html' => $menuHtml,
        'amenart_html' => $amenArt,
        'manifest_html' => paulus_manifest($t['intro_text'] ?? ''),
        'faq_html' => $faqHtml,
        'events_html' => $eventsHtml,
        'ld_cafe' => paulus_json($cafe),
        'ld_faq' => paulus_json($faqBlock),
        'mail_voucher' => paulus_e('mailto:' . $c['contact']['email'] . '?subject=' . rawurlencode('Dárkový poukaz')),
        'mail_coffee' => paulus_e('mailto:' . $c['contact']['email'] . '?subject=' . rawurlencode('Káva domů')),
    ];

    /* ---- conditional block: analytics only when an ID is set ---- */
    $hasGa = (bool)preg_match('/^G-[A-Z0-9]{4,}$/', $site['ga_id']);
    $tpl = preg_replace('/\{\{#ga\}\}(.*?)\{\{\/ga\}\}/s', $hasGa ? '$1' : '', $tpl);

    return preg_replace_callback('/\{\{(t|th|tw|tp|v|raw|wave|photo|split):([\w.]+)(?::(\d+))?\}\}/', function ($m) use ($c, $t, $raw) {
        switch ($m[1]) {
            case 't':    return str_replace("\n", '<br>', paulus_e(str_replace(["\r", '*'], '', $t[$m[2]] ?? '')));
            case 'tw':   /* words wrapped for the desktop title entrance (CSS only); line breaks kept */
                $i = 0;
                return implode('<br>', array_map(function ($line) use (&$i) {
                    return implode(' ', array_map(function ($w) use (&$i) { return '<span class="w"><span class="wi" style="--i:' . ($i++) . '">' . paulus_e($w) . '</span></span>'; },
                        preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY)));
                }, explode("\n", str_replace("\r", '', $t[$m[2]] ?? ''))));
            case 'th':   /* heading: *word* = orange, _word_ = teal highlight */
                $h = paulus_e(str_replace("\r", '', $t[$m[2]] ?? ''));
                $h = preg_replace('/\*([^*\n]+)\*/u', '<span class="hl-o">$1</span>', $h);
                $h = preg_replace('/(?<![\w])_([^_\n]+)_(?![\w])/u', '<span class="hl-t">$1</span>', $h);
                return str_replace("\n", '<br>', $h);
            case 'tp':   return paulus_e(preg_replace('/\s*\n\s*/', ' ', $t[$m[2]] ?? ''));
            case 'v':    $v = $c; foreach (explode('.', $m[2]) as $k) $v = $v[$k] ?? ''; return paulus_e((string)$v);
            case 'raw':  return $raw[$m[2]] ?? '';
            case 'photo': return paulus_photo($c, $m[2]);
            case 'split': /* big header lines: readable text for screen readers + one span per letter for the wave under the cursor */
                $w = trim(preg_replace('/\s+/u', ' ', str_replace('*', '', (string)($t[$m[2]] ?? ''))));
                $o = '';
                foreach (preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY) as $ch) $o .= $ch === ' ' ? '<span class="c c--sp"> </span>' : '<span class="c">' . paulus_e($ch) . '</span>';
                return '<span class="vh">' . paulus_e($w) . '</span><span class="hero__lv" aria-hidden="true">' . $o . '</span>';
            case 'wave': $w = trim((string)($t[$m[2]] ?? '')); if ($w !== '' && !preg_match('/[·•|\/–-]$/u', $w)) $w .= ' ·';   /* always ends with a separator and a space, however it was typed in admin */
                         return paulus_e(str_repeat($w . ' ', (int)($m[3] ?? 4)));
        }
        return '';
    }, $tpl);
}

/** Render to disk atomically. Returns null on success, or an error message. */
function paulus_render_to(string $dir): ?string {
    $c = json_decode((string)@file_get_contents($dir . '/content.json'), true);
    if (!is_array($c)) return 'content.json nelze načíst';
    $tpl = @file_get_contents($dir . '/index.tpl.html');
    if ($tpl === false) return 'index.tpl.html chybí';
    $assets = json_decode((string)@file_get_contents($dir . '/assets.json'), true) ?: [];
    foreach (['index' => $tpl, 'soukromi' => @file_get_contents($dir . '/soukromi.tpl.html')] as $name => $t) {
        if ($t === false) continue;
        $tmp = $dir . '/' . $name . '.html.tmp';
        if (@file_put_contents($tmp, paulus_render($c, $t, $assets)) === false || !@rename($tmp, $dir . '/' . $name . '.html')) return $name . '.html nelze zapsat';
    }
    return null;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $err = paulus_render_to($argv[1] ?? __DIR__);
    if ($err) { fwrite(STDERR, $err . "\n"); exit(1); }
    echo "index.html rendered\n";
}
