#!/usr/bin/php
<?php
/*
 * SysriX-Vorlagen für Unraid
 *
 * Holt die Unraid-Vorlagen (templates/*.xml) aus ALLEN Repositories von SysriX – öffentlich und privat –
 * und legt sie an zwei Stellen ab:
 *
 *   1. Benutzer-Vorlagen  /boot/config/plugins/dockerMan/templates-user/my-<Name>.xml
 *      → Docker → „Container hinzufügen“ → Vorlage. Fehlende werden angelegt, vorhandene nur aktualisiert,
 *        wenn sich etwas geändert hat. Eigene Werte (Pfade, IPs, Variablen, Netz, Extra-Parameter, Name, Tag)
 *        bleiben erhalten; neue Felder aus der Vorlage kommen dazu. Alte Fassung → Sicherung.
 *   2. Community Applications  /boot/config/plugins/community.applications/private/sysrix/
 *      → Apps → Bereich „Private“, auch wenn die App nicht offiziell in CA steht.
 *
 * Anmeldung bei GitHub (für private Repositories): Token aus GH_TOKEN/GITHUB_TOKEN, aus
 * /boot/config/plugins/sysrix-vorlagen/github-token oder aus dem „docker login ghcr.io“ (/root/.docker/config.json).
 * Das Token braucht Lesezugriff auf die Repositories (classic: „repo“, fine-grained: Contents: read).
 *
 * Normalerweise über das User Script unraid/vorlagen.sh gestartet (Zeitplan und „Run Script“ in User Scripts).
 * Aufruf:
 *   php sysrix-vorlagen.php               abgleichen
 *   php sysrix-vorlagen.php --probelauf   nur anzeigen, was passieren würde
 *   php sysrix-vorlagen.php --token=XYZ   Token dauerhaft speichern (auf dem USB-Stick) und abgleichen
 *   php sysrix-vorlagen.php --symbole     nur zwischengespeicherte Symbole bereitstellen (ohne Netz, für den Start)
 *   php sysrix-vorlagen.php --still       nur Änderungen und Warnungen ausgeben
 */

const OWNER    = 'SysriX';

$R = rtrim((string)getenv('SYSRIX_ROOT'), '/');       // nur zum Testen: anderes Wurzelverzeichnis
define('BASE',            $R . '/boot/config/plugins/sysrix-vorlagen');
define('USER_DIR',        $R . '/boot/config/plugins/dockerMan/templates-user');
define('CA_DIR',          $R . '/boot/config/plugins/community.applications/private/sysrix');
define('CA_OLD_DIR',      $R . '/boot/config/plugins/community.applications/private/unraid-images');
define('ICON_DIR',        BASE . '/icons');
define('ICON_WEB_DIR',    $R . '/usr/local/emhttp/plugins/sysrix-vorlagen/icons');
define('ICON_WEB',        '/plugins/sysrix-vorlagen/icons');
define('DOCKER_ICON_DIR', $R . '/var/lib/docker/unraid/images');
define('DOCKER_ICON_RAM', $R . '/usr/local/emhttp/state/plugins/dynamix.docker.manager/images');
define('DOCKER_CONFIG',   $R . '/root/.docker/config.json');
define('GO_FILE',         $R . '/boot/config/go');

// Felder, die immer aus der Vorlage kommen (Beschreibung, Links, Symbol …). Alles andere gehört dem Benutzer.
const UPSTREAM_FIELDS = ['Overview', 'Description', 'Category', 'Support', 'Project', 'ReadMe', 'Registry', 'Icon',
    'TemplateURL', 'Requires', 'ExtraSearchTerms', 'License', 'Maintainer', 'Changes', 'Beta', 'DonateText',
    'DonateLink', 'DonateImg', 'Screenshot', 'Video', 'Date'];

$args    = array_slice($argv, 1);
$DRY     = (bool)array_intersect($args, ['--probelauf', '--dry-run', '-n']);
$QUIET   = (bool)array_intersect($args, ['--still', '--quiet', '-q']);
$ICONS   = in_array('--symbole', $args, true);
$TOKEN   = '';
$changes = 0;
$warns   = 0;

function info(string $m): void { global $QUIET; if (!$QUIET) echo $m, "\n"; }
function note(string $m): void { global $changes; $changes++; echo $m, "\n"; }
function warn(string $m): void { global $warns; $warns++; fwrite(STDERR, "WARNUNG: $m\n"); }

function ensureDir(string $d): void { if (!is_dir($d)) @mkdir($d, 0755, true); }

function writeFile(string $path, string $data): bool {
    global $DRY;
    if ($DRY) return true;
    ensureDir(dirname($path));
    $tmp = $path . '.neu';
    if (@file_put_contents($tmp, $data) === false) { warn("kann $path nicht schreiben"); return false; }
    return @rename($tmp, $path) || (@copy($tmp, $path) && @unlink($tmp));
}

// ---------- Symbole nach Neustart bereitstellen (RAM-Ordner der Weboberfläche) ----------
function publishIcons(): void {
    if (!is_dir(ICON_DIR)) return;
    ensureDir(ICON_WEB_DIR);
    foreach (glob(ICON_DIR . '/*') ?: [] as $f) {
        $t = ICON_WEB_DIR . '/' . basename($f);
        if (!is_file($t) || md5_file($t) !== md5_file($f)) @copy($f, $t);
    }
}

if ($ICONS) { publishIcons(); exit(0); }

// ---------- Token ----------
foreach ($args as $a) {
    if (str_starts_with($a, '--token=')) {
        $t = trim(substr($a, 8));
        if ($t !== '' && !$DRY) { ensureDir(BASE); file_put_contents(BASE . '/github-token', $t . "\n"); @chmod(BASE . '/github-token', 0600); }
        info('Token gespeichert in ' . BASE . '/github-token');
    }
}

function findToken(): array {
    foreach (['GH_TOKEN', 'GITHUB_TOKEN'] as $e) {
        $t = trim((string)getenv($e));
        if ($t !== '') return [$t, "Umgebungsvariable $e"];
    }
    $f = BASE . '/github-token';
    if (is_file($f) && ($t = trim((string)file_get_contents($f))) !== '') return [$t, $f];
    if (is_file(DOCKER_CONFIG)) {
        $j = json_decode((string)file_get_contents(DOCKER_CONFIG), true);
        foreach (($j['auths'] ?? []) as $host => $a) {
            if (stripos((string)$host, 'ghcr.io') === false || empty($a['auth'])) continue;
            $dec = (string)base64_decode($a['auth']);
            $p = strpos($dec, ':');
            if ($p !== false && substr($dec, $p + 1) !== '') return [substr($dec, $p + 1), 'docker login ghcr.io'];
        }
    }
    return ['', ''];
}

// ---------- HTTP ----------
function http(string $url, string $accept = 'application/vnd.github+json'): array {
    global $TOKEN;
    $hdr = ['User-Agent: sysrix-vorlagen', "Accept: $accept", 'X-GitHub-Api-Version: 2022-11-28', 'Connection: close'];
    if ($TOKEN !== '' && str_starts_with($url, 'https://api.github.com/')) $hdr[] = "Authorization: Bearer $TOKEN";
    if (function_exists('curl_init')) {                  // bevorzugt: feste Zeitgrenzen, IPv4 (kein Hängen an IPv6)
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $hdr,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) warn('Abruf fehlgeschlagen: ' . preg_replace('/\?.*$/', '', $url) . ' – ' . curl_error($ch));
        curl_close($ch);
        return [$code, $body === false ? '' : (string)$body];
    }
    $ctx = stream_context_create(['http' => ['header' => implode("\r\n", $hdr) . "\r\n", 'timeout' => 30,
        'ignore_errors' => true, 'protocol_version' => 1.1]]);
    $body = @file_get_contents($url, false, $ctx);
    $headers = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
    $code = 0;
    foreach ($headers as $l) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $l, $m)) $code = (int)$m[1];   // letzte Antwort zählt
    return [$code, $body === false ? '' : $body];
}

function apiList(string $url): ?array {
    $all = [];
    for ($p = 1; $p <= 20; $p++) {
        [$c, $b] = http($url . (str_contains($url, '?') ? '&' : '?') . "per_page=100&page=$p");
        if ($c !== 200) return $p === 1 ? null : $all;
        $j = json_decode($b, true);
        if (!is_array($j)) break;
        $all = array_merge($all, $j);
        if (count($j) < 100) break;
    }
    return $all;
}

// ---------- XML-Hilfen ----------
function loadXml(string $xml): ?DOMDocument {
    $d = new DOMDocument();
    $d->preserveWhiteSpace = false;
    $d->formatOutput = true;
    if (!@$d->loadXML($xml) || !$d->documentElement || $d->documentElement->nodeName !== 'Container') return null;
    $d->encoding = 'UTF-8';                               // Umlaute lesbar statt &#xE4;
    return $d;
}
function kids(DOMElement $e, ?string $name = null): array {
    $r = [];
    foreach ($e->childNodes as $c) if ($c instanceof DOMElement && ($name === null || $c->nodeName === $name)) $r[] = $c;
    return $r;
}
function field(DOMDocument $d, string $name): string {
    $k = kids($d->documentElement, $name);
    return $k ? trim($k[0]->textContent) : '';
}
function imageBase(string $repo): string {
    $r = strtolower(trim($repo));
    $r = preg_replace('/@sha256:.*$/', '', $r);
    if (preg_match('#^(.*?)(:[^/:]+)?$#', $r, $m)) $r = $m[1];
    return $r;
}
function cfgKey(DOMElement $c): string { return strtolower($c->getAttribute('Type')) . '|' . $c->getAttribute('Target'); }

// Vergleich ohne Rücksicht auf Reihenfolge und Einrückung
function signature(DOMDocument $d): string {
    $parts = [];
    foreach (kids($d->documentElement) as $e) {
        $attrs = [];
        foreach ($e->attributes as $a) $attrs[] = $a->name . '=' . $a->value;
        sort($attrs);
        $parts[] = $e->nodeName . '[' . implode(',', $attrs) . ']=' . str_replace("\r", '', trim($e->textContent));
    }
    sort($parts);
    $root = [];
    foreach ($d->documentElement->attributes as $a) $root[] = $a->name . '=' . $a->value;
    sort($root);
    return implode(',', $root) . "\n" . implode("\n", $parts);
}

// Vorlage (neu) + Benutzer-Vorlage (alt) → Ergebnis
function merge(DOMDocument $new, DOMDocument $old): DOMDocument {
    $res = new DOMDocument('1.0', 'UTF-8');
    $res->preserveWhiteSpace = false;
    $res->formatOutput = true;
    $root = $res->appendChild($res->importNode($new->documentElement, true));
    $firstCfg = fn() => kids($root, 'Config')[0] ?? null;

    foreach (kids($old->documentElement) as $oe) {
        $n = $oe->nodeName;
        if ($n === 'Config' || in_array($n, UPSTREAM_FIELDS, true)) continue;
        if ($n === 'Repository' && imageBase($oe->textContent) !== imageBase(field($new, 'Repository'))) continue; // Image umgezogen
        $imp = $res->importNode($oe, true);
        $ne = kids($root, $n)[0] ?? null;
        if ($ne) $root->replaceChild($imp, $ne);
        elseif ($fc = $firstCfg()) $root->insertBefore($imp, $fc);
        else $root->appendChild($imp);
    }

    $oldCfg = kids($old->documentElement, 'Config');
    $used = [];
    foreach (kids($root, 'Config') as $nc) {
        $hit = null;
        foreach ($oldCfg as $i => $oc) if (!isset($used[$i]) && cfgKey($oc) === cfgKey($nc)) { $hit = $i; break; }
        if ($hit === null) foreach ($oldCfg as $i => $oc) if (!isset($used[$i]) && $oc->getAttribute('Name') === $nc->getAttribute('Name')) { $hit = $i; break; }
        if ($hit === null) continue;                 // neues Feld → Vorgabe aus der Vorlage
        $used[$hit] = true;
        $oc = $oldCfg[$hit];
        while ($nc->firstChild) $nc->removeChild($nc->firstChild);
        if ($oc->textContent !== '') $nc->appendChild($res->createTextNode($oc->textContent));
        if ($oc->getAttribute('Mode') !== '') $nc->setAttribute('Mode', $oc->getAttribute('Mode'));
    }
    foreach ($oldCfg as $i => $oc) if (!isset($used[$i])) $root->appendChild($res->importNode($oc, true)); // selbst angelegte Felder
    return $res;
}

function isImage(string $file): bool {
    if (!is_file($file) || filesize($file) < 64) return false;
    $h = (string)file_get_contents($file, false, null, 0, 512);
    return str_starts_with($h, "\x89PNG") || str_starts_with($h, "\xFF\xD8") || str_starts_with($h, 'GIF8')
        || stripos($h, '<svg') !== false || str_starts_with($h, 'RIFF');
}

// =====================================================================================================
[$TOKEN, $tokenSrc] = findToken();
info($TOKEN !== '' ? "GitHub-Anmeldung: $tokenSrc" : 'Keine GitHub-Anmeldung gefunden – nur öffentliche Repositories.');

// ---------- Repositories ----------
$repos = [];
$addRepos = function (?array $list) use (&$repos) {
    foreach ($list ?: [] as $r) if (strcasecmp($r['owner']['login'] ?? '', OWNER) === 0) $repos[$r['name']] = $r;
};
$pub = apiList('https://api.github.com/users/' . OWNER . '/repos?type=owner');
if ($pub === null) warn('Repository-Liste von GitHub nicht abrufbar (Netz oder Abruf-Grenze).');
$addRepos($pub);
$listOk = $pub !== null && $TOKEN !== '';   // ohne Anmeldung fehlen die privaten → dann nichts in CA aufräumen
if ($TOKEN !== '') {
    $mine = apiList('https://api.github.com/user/repos?affiliation=owner,collaborator,organization_member&visibility=all');
    if ($mine === null) { warn("Token wird von GitHub abgelehnt ($tokenSrc) – private Repositories fehlen."); $listOk = false; }
    $addRepos($mine);
    $addRepos(apiList('https://api.github.com/orgs/' . OWNER . '/repos?type=all'));
}
$nAll = count($repos);
$repos = array_filter($repos, fn($r, $n) => preg_match('/_unraid$/i', $n) === 1, ARRAY_FILTER_USE_BOTH);  // nur Unraid-Pakete
ksort($repos, SORT_NATURAL | SORT_FLAG_CASE);
$nPriv = count(array_filter($repos, fn($r) => !empty($r['private'])));
info(sprintf('%d Unraid-Repositories (*_unraid, %d privat) von %d bei %s.', count($repos), $nPriv, $nAll, OWNER));
if ($TOKEN !== '' && $nPriv === 0) warn('Keine privaten Repositories sichtbar – hat das Token Lesezugriff auf Repositories (Scope „repo“ bzw. Contents: read)?');

// ---------- Vorlagen holen ----------
$templates = [];
foreach ($repos as $name => $r) {
    if (!empty($r['archived'])) continue;
    if (!preg_match('/_unraid$/i', $name)) continue;                  // nur Unraid-Pakete (keine HA-Integrationen, Karten …)
    $branch = $r['default_branch'] ?? 'main';
    $priv = !empty($r['private']);
    if (!$QUIET) echo "  $name … ";
    [$c, $b] = http('https://api.github.com/repos/' . OWNER . "/$name/contents/templates?ref=" . rawurlencode($branch));
    if (!$QUIET) echo $c === 404 ? "keine Vorlagen\n" : ($c === 200 ? "Vorlagen\n" : "HTTP $c\n");
    if ($c === 404) continue;
    if ($c !== 200) { warn("$name: Ordner templates nicht abrufbar (HTTP $c)"); $listOk = false; continue; }
    foreach (json_decode($b, true) ?: [] as $f) {
        if (($f['type'] ?? '') !== 'file' || !preg_match('/\.xml$/i', $f['name'])) continue;
        if ($priv || $TOKEN !== '' || empty($f['download_url'])) {   // mit Token alles über api.github.com
            [$c2, $xml] = http('https://api.github.com/repos/' . OWNER . "/$name/contents/templates/" . rawurlencode($f['name']) . '?ref=' . rawurlencode($branch), 'application/vnd.github.raw');
        } else {
            [$c2, $xml] = http($f['download_url'], '*/*');
        }
        $dom = $c2 === 200 ? loadXml($xml) : null;
        if (!$dom) { warn("$name/templates/{$f['name']}: keine gültige Unraid-Vorlage (HTTP $c2)"); $listOk = false; continue; }
        $templates[] = ['repo' => $name, 'branch' => $branch, 'private' => $priv, 'file' => $f['name'], 'dom' => $dom,
            'name' => field($dom, 'Name'), 'url' => strtolower(field($dom, 'TemplateURL')),
            'image' => imageBase(field($dom, 'Repository')), 'icon' => field($dom, 'Icon')];
    }
}
info(count($templates) . ' Vorlagen gefunden.');
if (!$templates) { warn('Nichts zu tun.'); exit(1); }

// ---------- Symbole aller Vorlagen zwischenspeichern ----------
// iconFile: lokale Kopie (für den Docker-Symbol-Cache); iconPrivate: Symbol liegt in einem privaten Repo,
// der Browser kommt also nicht heran → CA bekommt die lokale Kopie über /plugins/sysrix-vorlagen/icons.
foreach ($templates as &$t) {
    $t['iconFile'] = '';
    $t['iconPrivate'] = false;
    if ($t['icon'] === '') continue;
    $ext = strtolower(pathinfo(parse_url($t['icon'], PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'png';
    $local = ICON_DIR . '/' . preg_replace('/\.xml$/i', '', $t['file']) . '.' . $ext;
    $m = [];
    $rawGh = preg_match('#^https://raw\.githubusercontent\.com/' . OWNER . '/([^/]+)/([^/]+)/(.+)$#i', $t['icon'], $m);
    foreach ($repos as $rn => $ri) if ($rawGh && strcasecmp($rn, $m[1]) === 0 && !empty($ri['private'])) $t['iconPrivate'] = true;
    if ($t['iconPrivate']) {
        [$c, $data] = http('https://api.github.com/repos/' . OWNER . "/{$m[1]}/contents/" . str_replace('%2F', '/', rawurlencode($m[3])) . '?ref=' . rawurlencode($m[2]), 'application/vnd.github.raw');
    } else {
        [$c, $data] = http($t['icon'], '*/*');
    }
    if ($c === 200 && strlen($data) > 64) {
        if (!is_file($local) || md5_file($local) !== md5($data)) writeFile($local, $data);
        $t['iconFile'] = $local;
    } elseif (is_file($local)) {
        $t['iconFile'] = $local;
    } else {
        warn("{$t['repo']}: Symbol nicht abrufbar (HTTP $c)");
    }
}
unset($t);
if (!$DRY) publishIcons();

// ---------- 1. Community Applications (Bereich „Private“) ----------
$caWritten = [];
foreach ($templates as $t) {
    $d = loadXml($t['dom']->saveXML());
    if ($t['iconPrivate'] && $t['iconFile'] !== '') {     // Browser kommt an private GitHub-Dateien nicht heran
        $ic = kids($d->documentElement, 'Icon')[0] ?? null;
        if ($ic) $ic->nodeValue = ICON_WEB . '/' . basename($t['iconFile']);
    }
    $xml = $d->saveXML();
    $target = CA_DIR . '/' . $t['file'];
    $caWritten[basename($target)] = true;
    $old = is_file($target) ? (string)file_get_contents($target) : null;
    if ($old === $xml) continue;
    if (writeFile($target, $xml)) note(($old === null ? 'CA     neu:          ' : 'CA     aktualisiert: ') . "{$t['name']} ({$t['repo']}/{$t['file']})");
}
// alte Vorlagen in CA aufräumen (nur, wenn die Liste vollständig abgerufen wurde)
if ($listOk) {
    foreach (glob(CA_DIR . '/*.xml') ?: [] as $f) {
        if (isset($caWritten[basename($f)])) continue;
        if (!$DRY) @unlink($f);
        note('CA     entfernt:     ' . basename($f) . ' (nicht mehr bei ' . OWNER . ')');
    }
}
// altes Skript private-vorlagen.sh (unraid-images) hat nach …/private/unraid-images geschrieben → doppelte Einträge vermeiden
if (is_dir(CA_OLD_DIR)) {
    foreach (glob(CA_OLD_DIR . '/*.xml') ?: [] as $f) if (isset($caWritten[basename($f)]) && !$DRY) @unlink($f);
    if (!$DRY && !(glob(CA_OLD_DIR . '/*') ?: [])) @rmdir(CA_OLD_DIR);
}

// ---------- 2. Benutzer-Vorlagen ----------
$existing = [];
foreach (glob(USER_DIR . '/*.xml') ?: [] as $f) {
    $d = loadXml((string)file_get_contents($f));
    if (!$d) continue;
    $existing[] = ['path' => $f, 'dom' => $d, 'url' => strtolower(field($d, 'TemplateURL')), 'image' => imageBase(field($d, 'Repository'))];
}
$countImg = fn(array $list, string $img) => count(array_filter($list, fn($x) => $x['image'] === $img));
$used = [];
$stamp = date('Y-m-d_His');
foreach ($templates as $t) {
    $hit = null;
    foreach ($existing as $i => $e) if (!isset($used[$i]) && $t['url'] !== '' && $e['url'] === $t['url']) { $hit = $i; break; }
    $foreign = false;   // gleicher Dateiname, aber fremder Container (anderes Image) → nie anfassen
    if ($hit === null) foreach ($existing as $i => $e) {
        if (strcasecmp(basename($e['path']), "my-{$t['name']}.xml") !== 0) continue;
        if (!isset($used[$i]) && ($e['image'] === $t['image'] || str_contains($e['url'], strtolower(OWNER)))) $hit = $i; else $foreign = true;
        break;
    }
    if ($hit === null && $t['image'] !== '' && $countImg($templates, $t['image']) === 1 && $countImg($existing, $t['image']) === 1)
        foreach ($existing as $i => $e) if (!isset($used[$i]) && $e['image'] === $t['image']) { $hit = $i; break; }

    if ($hit === null) {
        $dom = $t['dom'];
        $name = $t['name'];
        if ($foreign) {                                 // eigener Name, sonst überschreibt Unraid beim Installieren die fremde Vorlage
            $name .= '-SysriX';
            $dom = loadXml($t['dom']->saveXML());
            kids($dom->documentElement, 'Name')[0]->nodeValue = $name;
            warn("my-{$t['name']}.xml gehört zu einem anderen Container – SysriX-Vorlage heißt deshalb $name");
        }
        $path = USER_DIR . '/my-' . preg_replace('#[/\\\\]#', '_', $name) . '.xml';
        if (is_file($path)) { info("Vorlage vorhanden:    " . basename($path)); continue; }
        if (writeFile($path, $dom->saveXML())) note("Vorlage neu:          $name → " . basename($path));
        continue;
    }
    $used[$hit] = true;
    $e = $existing[$hit];
    $merged = merge($t['dom'], $e['dom']);
    if (signature($merged) === signature($e['dom'])) { info("Vorlage aktuell:      " . basename($e['path'])); continue; }
    if (!$DRY) { ensureDir(BASE . "/sicherung/$stamp"); @copy($e['path'], BASE . "/sicherung/$stamp/" . basename($e['path'])); }
    if (writeFile($e['path'], $merged->saveXML())) note("Vorlage aktualisiert: " . basename($e['path']) . ' (eigene Werte behalten, Sicherung in ' . BASE . "/sicherung/$stamp)");
}
// Sicherungen: die letzten 15 behalten
$dirs = glob(BASE . '/sicherung/*', GLOB_ONLYDIR) ?: [];
sort($dirs);
foreach (array_slice($dirs, 0, max(0, count($dirs) - 15)) as $old) if (!$DRY) { array_map('unlink', glob("$old/*") ?: []); @rmdir($old); }

// ---------- Symbole im Docker-Tab: Unraid merkt sich einen fehlgeschlagenen Abruf für immer → Cache selbst füllen ----------
if (is_dir(DOCKER_ICON_DIR) && !$DRY) {
    foreach (glob(USER_DIR . '/*.xml') ?: [] as $f) {
        $d = loadXml((string)file_get_contents($f));
        if (!$d) continue;
        $url = strtolower(field($d, 'TemplateURL'));
        foreach ($templates as $t) {
            if ($t['iconFile'] === '' || $t['url'] !== $url || !preg_match('/\.png$/i', $t['iconFile'])) continue;
            $dst = DOCKER_ICON_DIR . '/' . field($d, 'Name') . '-icon.png';
            if (isImage($dst) && md5_file($dst) === md5_file($t['iconFile'])) continue;
            if (@copy($t['iconFile'], $dst)) note('Symbol erneuert:      ' . field($d, 'Name'));
            @unlink(DOCKER_ICON_RAM . '/' . field($d, 'Name') . '-icon.png');   // RAM-Kopie baut Unraid daraus neu
        }
    }
}

// ---------- Zeitplan läuft jetzt über User Scripts: Cron/go-Zeile der ersten Fassung entfernen ----------
if (!$DRY) {
    if (is_file(BASE . '/sysrix-vorlagen.cron')) {
        @unlink(BASE . '/sysrix-vorlagen.cron');
        if (is_executable('/usr/local/sbin/update_cron')) exec('/usr/local/sbin/update_cron');
        info('Alten Cron-Eintrag entfernt (Zeitplan jetzt in User Scripts).');
    }
    $go = is_file(GO_FILE) ? (string)file_get_contents(GO_FILE) : '';
    if (str_contains($go, '# sysrix-vorlagen')) {
        $go = preg_replace('/^.*# sysrix-vorlagen\R?/m', '', $go);
        file_put_contents(GO_FILE, $go);
        info('Alte Zeile in ' . GO_FILE . ' entfernt.');
    }
}

info('');
info($DRY ? "Probelauf: $changes Änderung(en) wären nötig." : "Fertig: $changes Änderung(en)" . ($warns ? ", $warns Warnung(en)" : '') . '.');
if ($changes && !$DRY) info('Apps: Seite neu laden → Bereich „Private“.  Docker: „Container hinzufügen“ → Vorlage wählen.');
exit($warns ? 2 : 0);
