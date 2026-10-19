# Kavárna Paulus — web (kavarnapaulus.cz)

Všechny soubory jsou v **jedné složce bez podsložek** — nic se při nahrávání nerozháže.

## Nasazení
**Wedos (ostrý web):** přes FTP nahraj *celý obsah* složky do `www/` — včetně skrytého `.htaccess`.
Až bude na kavarnapaulus.cz aktivní SSL, odkomentuj v `.htaccess` přesměrování na HTTPS.

**Vercel (jen testování):** nahraj stejný obsah do repa. Díky `.vercelignore` se tam PHP soubory
(editor, rezervace) nenahrají — formulář na Vercelu proto nabídne odeslání e-mailem. Vše ostatní funguje.

## Úprava textů — `kavarnapaulus.cz/admin.php`
- Heslo jsem předal v chatu; v souborech je uložen jen jeho otisk. **Po prvním přihlášení ho změň** (dole v editoru).
- Upravit jde: fotky, texty všech sekcí, kontakt, otevírací doba (dny + časy), menu, vybavení (karty s ikonkami), akce, časté dotazy,
  odkazy na Instagram/Facebook, SEO popisky a ID Google Analytics.
- Po uložení se web znovu vygeneruje jako statické HTML — zůstává stejně rychlý.
- Otevírací doba se změní najednou na webu, ve strukturovaných datech pro Google i v odpovědi v dotazech
  (do odpovědi stačí napsat `{otevreno}`).
- Před každým uložením se udělá záloha (`content-zaloha-*.json`, posledních 5). Interní soubory nejsou z webu dostupné.

## Zvýraznění v nadpisech
V editoru stačí slovo obalit: `*slovo*` = oranžově, `_slovo_` = tyrkysově (např. „Co u nás *dostanete*“).

## Rezervace
Rezervační formulář už na webu není. Sekce „Těšíme se na vás“ (na konci stránky, kotva #rezervace) má tlačítka
„Zavolat a zamluvit stůl“ (vytočí telefon z editoru) a „Navigovat na Mapy.cz“ a kartu s dnešní otevírací dobou.
Pod kartou „Dnes na Kopečku“ poskakuje postavička.

## Google Analytics
V editoru vlož měřicí ID (`G-…`). Teprve pak se objeví lišta cookies; bez souhlasu se nic neměří.
Odkaz „Nastavení cookies“ v patičce umožní souhlas změnit.

## Doplnit před spuštěním
- adresa a telefon (editor → Kontakt), odkazy na Instagram a Facebook
- `soukromi.html` → správce údajů (název/jméno, IČO, sídlo) — v souboru `soukromi.tpl.html`, řádek „Správce“
- fotky: v editoru sekce **Fotky** (galerie 3×, vchod, Instagram 6×). Stačí vybrat fotku z mobilu — editor ji
  zmenší, ořízne na správný poměr, opraví otočení a uloží v 4–6 velikostech (WebP, a AVIF pokud ho server umí).

## Logo
Na webu je zatím **dočasné logo** („Paulus“ + oblouk „KAVÁRNA“). Je v ikonách, náhledu pro sdílení i ve strukturovaných datech.
Až dorazí finální logo (ideálně SVG), vymění se na všech místech najednou.

## Barvy a písmo (úprava po připomínkách klienta, říjen 2026)
- Jen tři barvy: hnědá linka, oranžová a výjimečně tyrkysová (kopule baziliky na panoramatu). Zvýrazněná slova v nadpisech jsou oranžová.
- Panorama: tools/pano_recolor.py přebarví work/pano/reference.jpg (plné barvy bez šraf), tools/hero_pano.py pak z výsledku
  vyřízne stromy/mraky a uloží obrázky: `python3 tools/pano_recolor.py work/pano/reference.jpg work/pano3/v5.png`
  a `PANO_COLOR=work/pano3/v5.png python3 tools/hero_pano.py`.
- Kresby v kartách Káva / Ráno upečeno / Slano do ruky: hnědá linka, oranžová jen na šálku, croissantu a botách, rajčeti (CSS).
- Jednotná velikost: nadpisy sekcí, úvodní texty, nadpisy karet a texty karet mají na celém webu stejnou velikost
  (proměnné --t-h2, --t-lede, --t-h3, --t-body na konci styles.css). Větší je jen hlavička.
- Obálka menu: hnědá deska, světlé logo, nápis Menu a tlačítko Nahlédni.
- Vyhlídka, terasa a klid: jen heslovité pilulky (texty = Výhody v administraci, text v závorce = menší řádek).
- Spojte kofein s výšlapem: bez mapy, tlačítka míst a karta pod nimi (texty v administraci, sekce Výlet).
- Patička bez šálku vedle loga, manifest bez ikonek v textu.
- Velký nápis v hlavičce a odkazy v horní liště jsou v nadpisovém písmu (Bricolage Grotesque), ne verzálkami.
- „Méně spěchu, víc kofeinu“: oranžový pruh přes celou šířku, nadpis a text vlevo, karta se skokem do šálku vpravo.
- Menu knížka: stránky jako ceník (nadpis s linkou, položky na tenkých linkách, cena vpravo, poznámka pod názvem), bez ikonek.
  Sekce se skládají za sebe, dokud se celé vejdou na stránku (víc obsahu na stránku); co se nevejde, začne na další.
  Bestseller: v administraci u položky zaškrtněte „Bestseller“ → položka dostane oranžový štítek a objeví se i na první
  stránce „★ Bestseller“ (oranžové tlačítko jako první nad knížkou). Název tlačítka/stránky je v textech Menu.
- Panorama: původní obrázek, jen zelená je nahrazená papírovou barvou (tools/pano_nogreen.py → work/pano3/v9.png, pak PANO_COLOR=work/pano3/v9.png tools/hero_pano.py). Pomalý zoom kamery je vypnutý.

## Animace
- Hlavička na počítači a na telefonu na šířku (varianta F): oranžová obrazovka vyplněná celou animací přiťuknutí
  (o 15 % menší, vpravo, přesahuje doprava a dolů; jeho levý okraj je schovaný pod kartou; work/hw = celý původní záběr), nápis, nadpis,
  podnadpis a tlačítka na kartě vlevo. Odkazy v liště jsou na světlých pilulkách, logo je celé bílé. Spodek hlavičky se
  rozplyne do panoramatu.
- „Méně spěchu, víc kofeinu“ (varianta 4): velký skok do šálku bez rámečku vpravo, nadpis a text na kartě vlevo.
- Vyhlídka: pilulky při prvním zobrazení postupně povyskočí. Výlet: karta vybraného místa je oranžová. Na mobilu na výšku zůstává
  světlá verze pod sebou s velkou animací (work/hc = ořez).
- Původní popis (mobil): nahoře obří dvouřádkový nápis „Nově otevřeno“ (hnědě, zarovnaný doprava)
  a „Kavárna Paulus“ (oranžově) přes celou šířku,   velké video s rukama a šálky ve spodní polovině mezi levým okrajem a textem (blíž k textu, pára stoupá za nápisem), vpravo dole hlavní nadpis
  „Dobrá káva a výhled na Olomouc.“, podnadpis a tlačítka „Kde jsme“ (posune na panorama „Konec cesty do kopce“, #kde)
  a „Nabídka & Ceny“ (#menu). Vpravo nahoře v liště je tlačítko „Zavolat“ (vytočí telefon z Kontaktů).
  Na mobilu a tabletu na výšku je to samé pod sebou (nápis → ruce se šálky → nadpis a tlačítka).
  Všechny texty včetně štítků jsou v administraci (Úvodní obrazovka); velikost nápisu se sama přizpůsobí délce delšího řádku.
  Najetím myší na velký nápis se písmena pod kurzorem zvednou a natočí jako vlna a pružně dosednou; po načtení jednou
  vlna přeběhne sama, na mobilu ji spustí ťuknutí. Video je ořez původní animace (work/hc/run.sh: levý, pravý i horní okraj se ztrácí do pozadí, zapečené ve videu).
- Druhá obrazovka („Pár kroků od baziliky“, #kde): kreslený pohled na Svatý Kopeček. Na počítači přes celou obrazovku
  s kartou na trávníku, na mobilu výřez kolem baziliky s kartou pod obrázkem. Texty karty jsou v administraci.
  Z obrázku jsou vystřižené stromy, mraky a auto: stromy se kývají ve větru, mraky plují, auto občas odjede.
  Po cestičkách chodí lidé, ze střechy baziliky občas vzlétnou holubi, v nádvoří stříká kašna, oblohou přeletí ptáci.
  Obrázek se stáhne až ve chvíli, kdy návštěvník začne stránkou posouvat (AVIF/WebP ve 3 velikostech).
  Úprava výřezů: tools/hero_pano.py (souřadnice v pixelech obrázku), pak build.py.
- Manifest „Méně spěchu, víc kofeinu.“: velký nadpis, vedle karta s animací skoku do oranžového šálku (video AV1/H.264).
  Animace se přehraje jednou, když je karta na obrazovce, a zastaví se, jakmile panáček sedí v šálku; dál stoupá jen pára
  (kreslená v SVG, smyčka). Bez JS / s omezeným pohybem je vidět konečný záběr s párou. Výroba: work/jump3 (run.sh + čištění páry).
  Text se při čtení vybarvuje slovo po slově a za slovy espresso, pečivo,
  terase a čas jsou malé kresbičky (doplní se samy podle slov v textu). Bez JS / s omezeným pohybem je text celý tmavý.
- Pořadí sekcí: hlavička → Konec cesty do kopce (panorama) → Stálice na baru (menu) → Co u nás dostanete → Jak to u nás vypadá →
  Méně spěchu, víc kofeinu (manifest + animace skoku do šálku) → Vyhlídka, terasa a klid od města → Kousek Kopečku s sebou →
  Spojte kofein s výšlapem (mapa) → Co je u nás nového (3 fotky) → Najdete nás → Než dorazíte → Těšíme se na vás → patička.
- Patička: karty jako na zbytku webu, lehce nakřivo připnuté na tmavé ploše (logo, adresa, kontakt, odkazy, sítě).
- Drobné animace po stránce: zrnka, lístky, mráčky, ptáci, jiskřičky, balón, vlaštovka, lžička sypající mletou kávu,
  vlaštovka z papíru, srdíčka, otazníky. Objevují se jen v sekci na obrazovce, nejvýš dvě v sekci a sedm na stránce.
- Menu „Stálice na baru“: jedna velká karta s obálkou „Menu“. Tlačítko Nahlédni ho otevře jako knížku, tlačítka sekcí
  (Káva, Nealko, Pečivo, Zákusky, Slané) listují rovnou na danou stránku, šipky a na mobilu i přejetí prstem listují po stránkách.
  Na počítači dvoustrana s obsahem, na mobilu jedna stránka. Tlačítka uvnitř knížky se při najetí myší jen přebarví
  (posun nahoru by v Chrome na otočené stránce „ztrácel“ myš a tlačítko by blikalo). Sekce a položky (název, cena, poznámka) jsou v administraci;
  každá sekce = jedna stránka. Bez JavaScriptu se menu ukáže jako karty pod sebou. Pro Google je menu i ve strukturovaných datech.
- Přechody kolem panoramatu jsou plynulé: spodek hlavičky se rozplyne do barvy oblohy a spodek panoramatu do stránky.
  Knížka se výškou přizpůsobí obrazovce; delší sekce, které se na stránku nevejdou, automaticky pokračují na další stránce
  („Káva pokračování“) a obsah i čísla stránek se dopočítají. Telefon na šířku má dvoustranu; tlačítka sekcí stránku posunou jen tak, aby byla vidět celá.
- Texty webu jsou v novém tónu (kopecký nadhled, lehký humor, fakta místo frází). Všechny jsou v administraci.
  Výhody (karty s ikonkami): text v závorce se ukáže menším písmem pod nadpisem karty.
- „Najdete nás“: dokud v administraci není fotka vchodu, stojí vedle karty kreslené průčelí kavárny s točícím se
  odznakem „Nově otevřeno“. Po nahrání fotky se místo kresby ukáže fotka.
- Po celé stránce se občas objeví drobnost: padající zrnka, listí, mráčky, ptáci, jiskřičky, balón nad mapou,
  papírová vlaštovka u rezervace, otazníky u dotazů. Vždy nejvýš dvě v sekci, jen když je sekce na obrazovce.
- Vlnitý text (nad mapou výletů) zrychlí podle scrollu a při scrollu nahoru jede pozpátku.
- Při „omezení pohybu“ v systému se nic z toho nespouští.

## Kontrola zobrazení
Ověřeno na 22 velikostech (telefony 320–430 px na výšku i na šířku, tablety, notebooky 1280×720 až 1536×864,
monitory 1920–3440 px), bez JavaScriptu (☰ pak vede na navigaci v patičce), s omezeným pohybem a bez videa.
Kontrolní skripty: tools/audit.mjs, tools/strip.mjs.

## Výsledky (Lighthouse, Chrome bez GPU = přísnější než běžný telefon)
Mobil i desktop: výkon 96–100 · přístupnost 100 · osvědčené postupy 100 · SEO 100.
Ověřeno na 9 velikostech obrazovky (telefon 360 px → monitor 2560 px), na výšku i na šířku, bez přetékání,
všechny ovládací prvky ≥ 44 px, funguje bez JavaScriptu i s omezením pohybu.

## Technika
- Fonty lokálně, ořezané na češtinu; metricky sladěné náhradní fonty (text při načtení neposkakuje).
- Hero: velký nápis je text (rychlé první vykreslení), obrázek s průhledností, video AV1/H.264 se spustí po načtení.
- Bublinové pozadí se kreslí do malého plátna — stejný vzhled, zlomek výpočtu.
- Animace se připraví až u obrazovky a mimo ni se pozastaví; při „omezení pohybu“ se nespouští.
- Soubory s otiskem v názvu (`site.1a2b3c4d.js`) se cachují natrvalo; service worker zrychlí opakované návštěvy
  a umožní přidat web na plochu telefonu jako aplikaci.
