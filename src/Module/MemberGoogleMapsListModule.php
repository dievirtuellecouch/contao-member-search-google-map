<?php

namespace Cm\MemberGoogleMapsBundle\Module;

use Contao\BackendTemplate;
use Contao\Database;
use Contao\Environment;
use Contao\Controller;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Module;
use Contao\Pagination;
use Contao\StringUtil;

class MemberGoogleMapsListModule extends Module
{
    protected $strTemplate = 'mod_cm_memberlist_simple';
    private bool $cmRadiusUsed = false;
    private array $cmDebug = [];
    private bool $cmDebugEnabled = false;

    public function generate()
    {
        if (\defined('TL_MODE') && TL_MODE === 'BE') {
            $tpl = new BackendTemplate('be_wildcard');
            $tpl->wildcard = '### CM MEMBER GOOGLE MAPS LIST ###';
            $tpl->title = $this->headline;
            $tpl->id = $this->id;
            $tpl->link = $this->name;
            $tpl->href = 'contao?do=themes&table=tl_module&act=edit&id='.$this->id;
            return $tpl->parse();
        }
        // Force the selected list template already here (before parent::generate)
        try {
            $chosen = (string) ($this->map_lsttemplate ?? '');
            if ($chosen === '' && isset($this->id)) {
                // Fallback: read from DB in case the property is not mapped yet
                $db = \Contao\Database::getInstance();
                $res = $db->prepare('SELECT map_lsttemplate FROM tl_module WHERE id=?')->limit(1)->execute((int) $this->id);
                if ($res->numRows) { $chosen = (string) $res->map_lsttemplate; }
            }
            // Normalize historical typo/plural
            if ($chosen === 'mod_cm_memberlist_googlemaps_tables') { $chosen = 'mod_cm_memberlist_googlemaps'; }
            if ($chosen === 'mod_cm_memberlist_googlemaps_table') { $chosen = 'mod_cm_memberlist_googlemaps'; }
            if ($chosen === '') {
                // Fallback based on tableless flag
                $chosen = ((bool) $this->cm_membergooglemaps_tableless) ? 'mod_cm_memberlist_googlemaps_tabless' : 'mod_cm_memberlist_googlemaps';
            }
            $this->strTemplate = $chosen;
        } catch (\Throwable $e) {
            // keep default template
        }
        $html = parent::generate();
        return $html;
    }

    protected function compile(): void
    {
        $this->Template->action = Environment::get('request');
        $this->cmDebugEnabled = (Input::get('cm_debug') !== null);

        // Inputs
        $postal = trim((string) (Input::get('plz') ?? (Input::get('plzarea') ?? '')));
        if ($postal === '') {
            $loc = trim((string) (Input::get('cm_location') ?? ''));
            if (preg_match('~^\d{3,5}$~', $loc)) { $postal = $loc; }
        }
        // Country from request (do not force default here to avoid unintended filtering)
        $countryReq = Input::get('country');
        if ($countryReq === null) { $countryReq = Input::get('cm_country'); }
        $country = strtoupper(trim((string) ($countryReq ?? '')));
        $this->Template->plz = $postal;
        $this->Template->country = $country;

        // Base filter: only consider enabled members (tl_member has no start/stop columns)
        $where = "disable!='1'";
        $values = [];

        $this->dbg('input', [
            'request' => Environment::get('request'),
            'moduleId' => (int) ($this->id ?? 0),
            'postal' => $postal,
            'plzarea' => Input::get('plzarea'),
            'cm_location' => Input::get('cm_location'),
            'cm_country' => Input::get('cm_country'),
            'cm_max_dist' => Input::get('cm_max_dist'),
            'privacy_required' => (bool) $this->cm_gc_acceptance_required,
            'privacy_value' => Input::get('cm_gc_privacy')
        ]);

        // Search
        $search = trim((string) (Input::get('for') ?? ''));
        // preferred search fields from module
        $searchFields = StringUtil::deserialize($this->ml_search_fields ?? '', true);
        // Fallbacks if empty: read directly from tl_module (migration-safety), then from legacy field config
        if (!$searchFields || !\is_array($searchFields) || !count($searchFields)) {
            try {
                $resSF = Database::getInstance()->prepare('SELECT ml_search_fields, cm_memberlist_seachfieldslist, cm_membergooglemaps_fieldslist FROM tl_module WHERE id=?')->limit(1)->execute((int) ($this->id ?? 0));
                if ($resSF->numRows) {
                    $sf1 = StringUtil::deserialize($resSF->ml_search_fields, true);
                    $sf2 = StringUtil::deserialize($resSF->cm_memberlist_seachfieldslist, true);
                    $sf3Rows = StringUtil::deserialize($resSF->cm_membergooglemaps_fieldslist, true);
                    $sf3 = [];
                    if ($sf3Rows && \is_array($sf3Rows)) { foreach ($sf3Rows as $r) { if (!empty($r['field'])) { $sf3[] = (string) $r['field']; } } }
                    $searchFields = array_values(array_unique(array_filter(array_merge((array)$sf1, (array)$sf2, (array)$sf3))));
                }
            } catch (\Throwable $e) {}
        }
        // Final default if still empty
        if (!$searchFields || !\is_array($searchFields) || !count($searchFields)) {
            $searchFields = ['company','firstname','lastname','street','postal','city','email','website','LeistungenAllgemein','Lieferant','Sachverstaendiger'];
        }
        // Sanitize list
        $searchFields = array_values(array_filter(array_unique(array_map(function($f){ return preg_match('~^[a-z0-9_]+$~i', (string)$f) ? (string)$f : null; }, (array)$searchFields))));
        // Always perform a fulltext-like search across the configured fields when input present
        if ($search !== '') {
            $likes = [];
            foreach ($searchFields as $sf) {
                $likes[] = $sf.' LIKE ?';
                $values[] = '%'.$search.'%';
            }
            if ($likes) { $where .= ' AND ('.implode(' OR ', $likes).')'; }
            $this->dbg('search_multi', [ 'fields' => $searchFields ]);
        }

        // Postal filter: decided later based on whether distance search is active
        $plzarea = trim((string) (Input::get('plzarea') ?? ''));
        $plzdigits = (int) ($this->cm_memberlist_plznumberdigits ?? 0);
        $wantPostalFilter = false;
        $postalLikeValue = null;
        if ($plzarea !== '' && ($plzdigits === 0 || preg_match('~^\d{'.$plzdigits.'}\d*$~', $plzarea))) {
            $wantPostalFilter = true;
            $postalLikeValue = $plzarea.'%';
        } elseif ($postal !== '') {
            $wantPostalFilter = true;
            $postalLikeValue = $postal.'%';
        }
        // Note: country filtering handled after distance decision as well.
        
        // NOTE: textual address LIKE filters will be applied after we decide about radius filtering

        // Fields to display
        $displayFields = [];
        $rows = StringUtil::deserialize($this->cm_membergooglemaps_fieldslist ?? '', true);
        if ($rows && \is_array($rows)) {
            foreach ($rows as $r) { if (!empty($r['field'])) { $displayFields[] = (string) $r['field']; } }
        }
        if (!$displayFields) { $displayFields = StringUtil::deserialize($this->ml_fields ?? '', true); }
        if (!$displayFields) { $displayFields = ['company','firstname','lastname','street','postal','city','website']; }

        // SELECT (always include address columns so templates can render city/postal regardless of config)
        $mlFields = StringUtil::deserialize($this->ml_fields ?? '', true);
        // Ensure avatar, address and person name columns are selected so templates can render them regardless of field config
        $baseCols = ['id','alias','cm_membergooglemaps_coords','groups','avatar','street','postal','city','country','firstname','lastname'];
        $select = array_unique(array_merge($baseCols, $displayFields, (array)$mlFields));
        $selectSql = implode(',', array_filter(array_map(fn($f) => preg_match('~^[a-z0-9_]+$~i', $f) ? $f : null, $select)));
        if (!$selectSql) { $selectSql = 'id,alias,cm_membergooglemaps_coords'; }

        // Distance filter using Google geocoded visitor location
        $lat1 = null; $lng1 = null; $hasLocal = false;
        // Determine effective max distance: request value or module defaults
        $maxDist = (int) (Input::get('cm_max_dist') ?? 0);
        if ($maxDist <= 0) {
            // Try to parse legacy distance values with [default]
            $def = 0; $opt = [];
            $rawVals = (string) ($this->cm_memberlist_distancevalues ?? '');
            if ($rawVals !== '') {
                $raw = array_filter(array_map('trim', explode(',', $rawVals)), 'strlen');
                foreach ($raw as $part) {
                    if (preg_match('~^\[(\d+)\]$~', $part, $m)) { $def = (int)$m[1]; $opt[] = (int)$m[1]; }
                    else { $opt[] = (int)$part; }
                }
            }
            if ($def > 0) { $maxDist = $def; }
            elseif (!empty($this->cmf_default_dist)) { $maxDist = (int) $this->cmf_default_dist; }
            elseif (!empty($opt)) { $maxDist = (int) ($opt[0] ?? 0); }
        }
        // Trigger geocoding if either a location OR a postal (from plz/plzarea/cm_location numeric) is present
        $wantGeocode = false;
        if (Input::get('cm_location') !== null && trim((string) Input::get('cm_location')) !== '') {
            $wantGeocode = true;
        } elseif ($postal !== '') {
            $wantGeocode = true;
        }
        // Perform geocoding whenever a location was provided (independent of map privacy checkbox)
        if ($wantGeocode) {
            [$lat1, $lng1] = $this->geocodeVisitorLocation();
            if ($lat1 !== null && $lng1 !== null) { $hasLocal = true; }
        }
        $this->dbg('geocode_decision', [
            'wantGeocode' => $wantGeocode,
            'privacyAccepted' => $privacyAccepted,
            'hasLocal' => $hasLocal,
            'lat' => $lat1,
            'lng' => $lng1,
        ]);

        // Determine whether to build route links (needs to be known before building $items)
        $linkRouteFlag = (bool) ($this->cm_membergooglemaps_linkroute ?? false) || (bool) ($this->cm_map_routetotable ?? false);
        // DB fallback in case properties are not hydrated
        if (!$linkRouteFlag) {
            try {
                $resLR = Database::getInstance()->prepare('SELECT cm_membergooglemaps_linkroute, cm_map_routetotable FROM tl_module WHERE id=?')->limit(1)->execute((int) ($this->id ?? 0));
                if ($resLR->numRows) {
                    $linkRouteFlag = (bool) ($resLR->cm_membergooglemaps_linkroute ?? 0) || (bool) ($resLR->cm_map_routetotable ?? 0);
                }
            } catch (\Throwable $e) {}
        }

        $orderBy = 'lastname, firstname';
        if ($hasLocal && $maxDist > 0) {
            $distExpr = $this->distanceSql($lat1, $lng1);
            $selectSql .= ', '.$distExpr.' AS dist';
            $where .= ' AND '.$distExpr.' <= ?';
            $values[] = $maxDist;
            $orderBy = 'dist ASC';
            $this->cmRadiusUsed = true;
        } else {
            // Apply postal LIKE filter only when not using distance search.
            if ($wantPostalFilter && $postalLikeValue) {
                $where .= ' AND postal LIKE ?';
                $values[] = $postalLikeValue;
            }
            // Apply textual address LIKE filters (on street/postal/city) only when not using radius
            $addrQuery = trim((string) (Input::get('cm_location') ?? ''));
            if ($addrQuery !== '') {
                $words = preg_split('~\s+~', $addrQuery, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $clauses = [];
                foreach ($words as $w) {
                    $w = trim($w);
                    if ($w === '') { continue; }
                    $clauses[] = '(street LIKE ? OR postal LIKE ? OR city LIKE ?)';
                    $values[] = '%'.$w.'%';
                    $values[] = '%'.$w.'%';
                    $values[] = '%'.$w.'%';
                }
                if ($clauses) {
                    $where .= ' AND '.implode(' AND ', $clauses);
                }
            }
        }

        // Prepare debug data early (before executing the query)
        $this->cmDebug = [
            'hasLocal' => $hasLocal,
            'maxDist'  => $maxDist,
            'lat'      => $lat1,
            'lng'      => $lng1,
            'postal'   => $postal,
            'plzarea'  => $plzarea,
        ];

        $this->dbg('sql', [
            'select' => $selectSql,
            'where' => $where,
            // Limit values log to scalar dump
            'values' => array_map(fn($v) => is_scalar($v)? $v : (is_object($v)? get_class($v) : gettype($v)), $values),
            'orderBy' => $orderBy,
            'radiusUsed' => $this->cmRadiusUsed,
        ]);

        $stmt = Database::getInstance()->prepare("SELECT $selectSql FROM tl_member WHERE $where ORDER BY $orderBy");
        $result = $stmt->execute(...$values);

        $items = [];
        $filterGroups = \Contao\StringUtil::deserialize($this->ml_groups ?? '', true);
        while ($result->next()) {
            $row = $result->row();
            if ($filterGroups) {
                $memberGroups = \Contao\StringUtil::deserialize($row['groups'] ?? '', true);
                $pass = false;
                foreach ((array)$memberGroups as $gid) { if (in_array((int)$gid, array_map('intval',$filterGroups), true)) { $pass = true; break; } }
                if (!$pass) { continue; }
            }
            $item = ['id' => $row['id'], 'coords' => $row['cm_membergooglemaps_coords'], 'alias' => $row['alias']];
            // Resolve avatar path from UUID (Contao 4/5) or keep legacy path
            $avatarPath = '';
            try {
                $raw = (string) ($row['avatar'] ?? '');
                if ($raw !== '') {
                    $uuid = null;
                    if (strlen($raw) === 16) {
                        $uuid = \Contao\StringUtil::binToUuid($raw);
                    } elseif (preg_match('~^[a-f0-9\-]{36}$~i', $raw)) {
                        $uuid = $raw;
                    }
                    if ($uuid) {
                        if ($file = \Contao\FilesModel::findByUuid($uuid)) {
                            $avatarPath = (string) $file->path;
                        }
                    } else {
                        // Assume legacy stored relative path
                        $avatarPath = $raw;
                    }
                }
            } catch (\Throwable $e) {
                // ignore avatar resolution errors
            }
            if ($avatarPath !== '') { $item['avatar'] = $avatarPath; }
            if (isset($row['dist'])) {
                $item['dist'] = (float) $row['dist'];
            }
            if ($linkRouteFlag) {
                $routeUrl = '';
                $coords = trim((string) ($row['cm_membergooglemaps_coords'] ?? ''));
                if ($coords !== '') {
                    $routeUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($coords);
                } else {
                    $addrParts = [];
                    foreach (['street','postal','city','country'] as $p) {
                        if (!empty($row[$p])) { $addrParts[] = $row[$p]; }
                    }
                    if ($addrParts) {
                        $routeUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(implode(' ', $addrParts));
                    }
                }
                $item['routeUrl'] = $routeUrl;
            }
            foreach ($displayFields as $f) {
                // Preserve resolved avatar path and do not overwrite it with raw DB value
                if ($f === 'avatar') {
                    if (isset($item['avatar'])) {
                        continue;
                    }
                    // If not resolved above, try a last-minute resolution
                    $val = '';
                    try {
                        $raw = (string) ($row['avatar'] ?? '');
                        if ($raw !== '') {
                            $uuid = null;
                            if (strlen($raw) === 16) { $uuid = \Contao\StringUtil::binToUuid($raw); }
                            elseif (preg_match('~^[a-f0-9\-]{36}$~i', $raw)) { $uuid = $raw; }
                            if ($uuid && ($file = \Contao\FilesModel::findByUuid($uuid))) { $val = (string) $file->path; }
                            else { $val = $raw; }
                        }
                    } catch (\Throwable $e) {}
                    if ($val !== '') { $item['avatar'] = $val; }
                    continue;
                }
                $val = $row[$f] ?? '';
                if (\is_string($val) && strpos($val, 'a:') === 0) {
                    $arr = StringUtil::deserialize($val, true);
                    if ($arr) { $val = implode(', ', $arr); }
                }
                if ($f === 'website') {
                    $rawSite = trim((string) $val);
                    if ($rawSite !== '') {
                        $href = $rawSite;
                        // Prepend https:// if scheme is missing and it looks like a domain
                        if (!preg_match('~^https?://~i', $href)) {
                            if (preg_match('~^//~', $href)) {
                                $href = 'https:'.$href;
                            } else {
                                // Starts with www. or looks like domain.tld
                                if (preg_match('~^(www\.)~i', $href) || preg_match('~^[A-Za-z0-9.-]+\.[A-Za-z]{2,}(/|$)~', $href)) {
                                    $href = 'https://'.$href;
                                }
                            }
                        }
                        // Label without scheme
                        $label = preg_replace('~^https?://~i', '', $rawSite);
                        $item['website'] = $label ?: $rawSite;
                        $item['websiteUrl'] = $href;
                    } else {
                        $item[$f] = $val;
                    }
                } else {
                    $item[$f] = $val;
                }
            }
            $items[] = $item;
        }

        // Ensure German field labels are available
        try { Controller::loadLanguageFile('tl_member'); Controller::loadDataContainer('tl_member'); } catch (\Throwable $e) {}
        $labelMap = [];
        foreach ((array)$displayFields as $f) {
            $labelMap[$f] = $GLOBALS['TL_DCA']['tl_member']['fields'][$f]['label'][0]
                ?? ($GLOBALS['TL_LANG']['tl_member'][$f][0] ?? ucfirst((string)$f));
        }

        $this->Template->items = $items;
        $this->Template->displayFields = $displayFields;
        $this->Template->fieldLabels = $labelMap;
        $this->Template->hasResults = \count($items) > 0;
        $this->Template->notFoundMessage = (string) ($this->cm_memberlist_notfound ?: 'Keine Einträge gefunden.');
        $this->Template->linkWebsite = (bool) $this->cm_membergooglemaps_linktowebsite;
        $this->Template->linkRoute = $linkRouteFlag;
        $this->Template->listdistance = $this->cmRadiusUsed;
        // Map acceptance like 3.5
        $this->Template->acceptanceRequired = (bool) $this->cm_gc_acceptance_required;
        // Vereinheitlichter Platzhalter-Text wie beim Google-Map-CE (erzwinge gleichen Text)
        $defaultAcceptance = 'Bitte akzeptieren Sie die Cookie‑Richtlinien (Google Maps), um die Karte zu sehen.';
        $this->Template->acceptanceText = $defaultAcceptance;
        // Provide Google Maps API key to templates (multi-source resolution)
        $apiKey = '';
        try {
            if (!empty($_SERVER['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_SERVER['GOOGLE_MAPS_API_KEY']; }
            elseif (!empty($_ENV['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_ENV['GOOGLE_MAPS_API_KEY']; }
            elseif (($tmp = getenv('GOOGLE_MAPS_API_KEY')) !== false && $tmp !== '') { $apiKey = (string) $tmp; }
            elseif (\Contao\System::getContainer()->hasParameter('env(GOOGLE_MAPS_API_KEY)')) { $apiKey = (string) \Contao\System::getContainer()->getParameter('env(GOOGLE_MAPS_API_KEY)'); }
        } catch (\Throwable $e) {}
        $this->Template->googleApiKey = $apiKey;
        // Ensure bundled CSS for placeholders is included
        if (!isset($GLOBALS['TL_CSS']['websailing_google_map'])) {
            $GLOBALS['TL_CSS']['websailing_google_map'] = 'bundles/websailinggooglemap/css/google_map.css|static';
        }
        // Build minimal Google Maps initialization for template compatibility
        if ($includeMap && $apiKey) {
            $mapId = 'memberlistmap_'.(int)($this->id ?? 0);
            $this->Template->mapID = $mapId;
            $heightCss = $this->Template->mapHeight ?: '400px';
            $this->Template->mapstyle = 'height: '.$heightCss.'; width:100%';
            // Ensure maps library is present and async loading is used (template adds callback if missing)
            $base = 'https://maps.googleapis.com/maps/api/js?libraries=maps&loading=async&key='.rawurlencode($apiKey);
            $this->Template->BaseScriptCode = $base;
            $center = '51.000,10.000';
            if ($this->Template->mapCenterEmpty) { $center = (string) $this->Template->mapCenterEmpty; }
            $clat = 51.0; $clng = 10.0;
            if (preg_match('~^\s*([\-\d\.]+)\s*,\s*([\-\d\.]+)\s*$~', $center, $m)) {
                $clat = (float)$m[1]; $clng = (float)$m[2];
            }
            $zoom = (int) ($this->Template->mapZoomEmpty ?: 6);
            $init = "function ".$mapId."_initialize(){var c={lat:".$clat.",lng:".$clng."};new google.maps.Map(document.getElementById('".$mapId."'),{zoom:".$zoom.",center:c});}";
            $this->Template->GoogleMapCode = $init;
        }

        // Debug: add basic statistics about coords presence to help diagnose radius filtering
        $coordsCount = 0;
        foreach ($items as $it) { if (!empty($it['coords'])) { $coordsCount++; } }
        $this->dbg('results', [ 'count' => \count($items), 'withCoords' => $coordsCount ]);

        // Map include: show map when requested in module, regardless of coords (fallback center can be used)
        $includeMap = (bool) $this->cm_map_onlist;
        $hasCoords = false;
        foreach ($items as $it) { if (!empty($it['coords'])) { $hasCoords = true; break; } }
        $this->Template->includeMap = $includeMap;
        $this->Template->mapShowOnEmpty = (bool) $this->cm_map_showmaponempty;
        $this->Template->mapCenterEmpty = (string) $this->cm_map_centerempty;
        $this->Template->mapZoomEmpty = (int) $this->cm_map_zoomempty;
        // Build markers from items (use coords if available)
        $markers = [];
        foreach ($items as $it) {
            $coords = trim((string)($it['coords'] ?? ''));
            if ($coords === '' || strpos($coords, ',') === false) continue;
            $parts = explode(',', $coords, 2);
            $lat = (float) trim($parts[0]);
            $lng = (float) trim($parts[1]);
            $title = '';
            if (!empty($it['company'])) { $title = (string) $it['company']; }
            elseif (!empty($it['firstname']) || !empty($it['lastname'])) { $title = trim(($it['firstname'] ?? '').' '.($it['lastname'] ?? '')); }
            $markers[] = ['lat' => $lat, 'lng' => $lng, 'title' => $title];
        }
        $this->Template->markersJson = json_encode($markers);
        // Normalize height (can be stored as serialized array with unit/value)
        $heightRaw = $this->cm_map_heightlist;
        $heightVal = '';
        try {
            if (\is_string($heightRaw)) {
                // Might be plain CSS value or serialized array; try deserialize first
                $hArr = null;
                if (strpos($heightRaw, 'a:') === 0 || strpos($heightRaw, ':"unit"') !== false) {
                    $hArr = StringUtil::deserialize($heightRaw, true);
                }
                if (\is_array($hArr) && isset($hArr['value'])) {
                    $unit = $hArr['unit'] ?? 'px';
                    $val  = trim((string)$hArr['value']);
                    if ($val !== '') { $heightVal = $val.(preg_match('~^\d+$~',$val)?$unit:''); }
                } else {
                    $heightVal = trim($heightRaw);
                }
            } else {
                $hArr = StringUtil::deserialize($heightRaw, true);
                if (\is_array($hArr) && isset($hArr['value'])) {
                    $unit = $hArr['unit'] ?? 'px';
                    $val  = trim((string)$hArr['value']);
                    if ($val !== '') { $heightVal = $val.(preg_match('~^\d+$~',$val)?$unit:''); }
                }
            }
        } catch (\Throwable $e) {}
        if ($heightVal === '' || $heightVal === 'a:0:{}') { $heightVal = '400px'; }
        $this->Template->mapHeight = $heightVal;
        // Normalize position values and provide both legacy and new variables
        $pos = (string) ($this->cm_map_poslist ?: 'bottom');
        $posNorm = strtolower($pos);
        if ($posNorm !== 'above' && $posNorm !== 'below') {
            // accept also 'top'/'bottom'
            if ($posNorm === 'top') { $posNorm = 'above'; } elseif ($posNorm === 'bottom') { $posNorm = 'below'; } else { $posNorm = 'below'; }
        }
        $this->Template->mappos = $posNorm; // legacy used in template conditions
        $this->Template->mapPosition = ($posNorm === 'above' ? 'top' : 'bottom');

        // ------- UI flags for combined search form (all-in-one) -------
        // Determine which controls to show (mirror legacy behavior)
        $showAddress = (bool) $this->cm_memberlist_addressform;
        $selectCountry = (bool) ($this->cm_map_country_as_select ?? false);
        $distForm    = (bool) $this->cm_memberlist_distanceform;
        $distAsDrop  = (bool) $this->cm_memberlist_distanceasdropdown;
        $fieldsearch = (bool) $this->cm_memberlist_fieldsearch;
        $multifield  = (bool) $this->cm_memberlist_multifieldseach;
        $plzsearch   = (bool) $this->cm_memberlist_plzsearch;
        // DB fallback if properties are not mapped
        try {
            if (!($showAddress && $distForm && $distAsDrop)) {
                $res = Database::getInstance()->prepare('SELECT cm_memberlist_addressform, cm_memberlist_distanceform, cm_memberlist_distanceasdropdown, cm_map_country, cm_map_country_as_select, cm_memberlist_fieldsearch, cm_memberlist_multifieldseach, cm_memberlist_plzsearch FROM tl_module WHERE id=?')->limit(1)->execute((int) ($this->id ?? 0));
                if ($res->numRows) {
                    $showAddress = (bool) ($res->cm_memberlist_addressform ?: $showAddress);
                    $distForm    = (bool) ($res->cm_memberlist_distanceform ?: $distForm);
                    $distAsDrop  = (bool) ($res->cm_memberlist_distanceasdropdown ?: $distAsDrop);
                    $selectCountry = (bool) (($res->cm_map_country_as_select ?: $this->cm_map_country_as_select));
                    $fieldsearch = (bool) ($res->cm_memberlist_fieldsearch ?: $fieldsearch);
                    $multifield  = (bool) ($res->cm_memberlist_multifieldseach ?: $multifield);
                    $plzsearch   = (bool) ($res->cm_memberlist_plzsearch ?: $plzsearch);
                }
            }
        } catch (\Throwable $e) {}

        // All-in-one form: show if either field/PLZ search or address form is enabled (legacy behavior)
        $this->Template->allInOne = ($showAddress || $fieldsearch || $multifield || $plzsearch);

        // Labels
        try { \Contao\Controller::loadLanguageFile('default'); } catch (\Throwable $e) {}
        $MSC = $GLOBALS['TL_LANG']['MSC'] ?? [];
        $this->Template->search_label    = (string) ($MSC['search'] ?? 'Suchen');
        $this->Template->per_page_label  = (string) ($MSC['list_perPage'] ?? 'Ergebnisse pro Seite');
        $this->Template->fields_label    = (string) (($MSC['all_fields'][0] ?? null) ?: 'Feld');
        $this->Template->keywords_label  = (string) ($MSC['keywords'] ?? 'Suchbegriffe');
        $this->Template->plz_search      = (string) ($MSC['cm_plz_search'] ?? 'PLZ-Suche');
        $this->Template->plzarea_label   = (string) ($MSC['plzarea'] ?? 'PLZ-Bereich');
        $this->Template->lbl_location    = $showAddress ? 'PLZ / Ort' : ((string) ($MSC['cm_lbl_location'] ?? 'Adresse:'));
        $this->Template->lbl_country     = (string) ($MSC['cm_lbl_country'] ?? 'Land:');
        $this->Template->lbl_max_dist    = (string) ($MSC['cm_lbl_max_dist'] ?? 'Entfernung:');
        $this->Template->lbl_max_dist_drdn = (string) ($MSC['cm_lbl_max_dist_drdn'] ?? 'Umkreis auswählen:');
        $this->Template->cm_distitem       = (string) ($MSC['cm_distitem'] ?? 'km');
        $this->Template->distsearch_label  = (string) ($this->cmf_submit_label ?: ($MSC['cm_distsearch_label'] ?? 'Suchen'));
        $this->Template->radius_search     = (string) ($this->cmf_heading ?: ($MSC['cm_radius_search'] ?? 'Umkreissuche'));

        // Flags
        $this->Template->fieldsearch   = $fieldsearch;
        $this->Template->multifieldsearch = $multifield;
        $this->Template->plzsearch     = $plzsearch;
        $this->Template->plzarea       = trim((string) (Input::get('plzarea') ?? ''));
        $this->Template->radiusform    = $showAddress; // legacy: whole block controlled by addressform
        $this->Template->distanceform  = $distForm;
        $this->Template->distanceasdropdown = $distAsDrop;

        // Country select
        $defaultCountry = strtolower(trim((string) ($this->cm_map_country ?? '')));
        $selected = strtolower(trim((string) (Input::get('cm_country') ?? ($defaultCountry !== '' ? $defaultCountry : ''))));
        if ($selectCountry) {
            $countryOpts = [];
            try {
                $countries = \Contao\System::getContainer()->get('contao.intl.countries')->getCountries();
                foreach ($countries as $code => $label) { $countryOpts[strtolower($code)] = $label; }
            } catch (\Throwable $e) { $countryOpts = ['de'=>'Deutschland','at'=>'Österreich','ch'=>'Schweiz']; }
            $countryHtml = '<select name="cm_country" class="cm_country">';
            if ($selected === '') { $countryHtml .= '<option value="" selected="selected"></option>'; }
            foreach ($countryOpts as $code => $label) { $countryHtml .= '<option value="'.$code.'"'.($code===$selected?' selected="selected"':'').'>'.htmlspecialchars($label, ENT_QUOTES).'</option>'; }
            $countryHtml .= '</select>';
            $this->Template->visitorcountry = $countryHtml;
        } else {
            $this->Template->visitorcountry = '<input class="cm_country" type="text" name="cm_country" value="'.htmlspecialchars($selected, ENT_QUOTES).'">';
        }

        // Distance options
        $maxDist = (int) (Input::get('cm_max_dist') ?? 0);
        $optVals = [];
        if ($distAsDrop && !empty($this->cm_memberlist_distancevalues)) {
            $raw = array_filter(array_map('trim', explode(',', (string) $this->cm_memberlist_distancevalues)), 'strlen');
            $def = null;
            foreach ($raw as $part) {
                if (preg_match('~^\[(\d+)\]$~', $part, $m)) { $val = (int)$m[1]; $def = $val; $optVals[]=$val; }
                else { $optVals[] = (int)$part; }
            }
            if (!$maxDist) { $maxDist = $def ?: ($optVals[0] ?? 10); }
        }
        if (!$optVals) {
            $optVals = [10,25,50,100,200];
            if (!empty($this->cmf_dist_options)) {
                $optVals = array_map('intval', array_filter(array_map('trim', explode(',', (string)$this->cmf_dist_options)), 'strlen'));
            }
            if (!$maxDist) { $maxDist = (int) ($this->cmf_default_dist ?: 10); }
        }
        $optHtml=''; foreach ($optVals as $ov) { $optHtml .= '<option value="'.$ov.'"'.($ov===$maxDist?' selected':'').'>'.$ov.'</option>'; }
        $this->Template->distvalues = $optHtml; $this->Template->max_dist = $maxDist;

        // Address fields support and composed cm_location
        $this->Template->show_address = $showAddress;
        $street = trim((string) (Input::get('cm_street') ?? ''));
        $postal = trim((string) (Input::get('cm_postal') ?? ''));
        $city   = trim((string) (Input::get('cm_city') ?? ''));
        $this->Template->cm_street = $street; $this->Template->cm_postal = $postal; $this->Template->cm_city = $city;
        $composed = '';
        if ($showAddress) { $parts = array_filter([$street,$postal,$city], fn($v)=>$v!==''); if ($parts) { $composed = implode(' ', $parts); } }
        $this->Template->visitorlocation = $composed !== '' ? $composed : trim((string) (Input::get('cm_location') ?? ''));
        $this->Template->lbl_street = 'Straße'; $this->Template->lbl_postal = 'PLZ'; $this->Template->lbl_city = 'Ort';

        // Respect explicitly selected list template only; otherwise keep current $this->strTemplate
        $chosen = (string) ($this->map_lsttemplate ?? '');
        if ($chosen) {
            $tpl = new FrontendTemplate($chosen);
            foreach ($this->Template->getData() as $k=>$v) { $tpl->$k = $v; }
            $tpl->infoTemplate = (string) ($this->map_infotemplate ?? '');
            $this->Template = $tpl;
        }

        // Pagination
        $perPage = (int) ($this->perPage ?: 0);
        if ($perPage > 0) {
            $page = max(1, (int) (Input::get('page') ?? 1));
            $offset = ($page - 1) * $perPage;
            $this->Template->items = array_slice($items, $offset, $perPage);
            $pagination = new Pagination(\count($items), $perPage);
            $this->Template->pagination = $pagination->generate("\n  ");
        } else {
            $this->Template->pagination = '';
        }
    }

    private function distanceSql(float $lat1, float $lng1): string
    {
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $sin = sin($lat1);
        $cos = cos($lat1);
        // Use dedicated lat/lng columns if present; otherwise parse from coords "lat,lng"
        $latCol = "IF(cm_membergooglemaps_lat<>'' AND cm_membergooglemaps_lat IS NOT NULL, RADIANS(cm_membergooglemaps_lat), RADIANS(CAST(SUBSTRING_INDEX(cm_membergooglemaps_coords, ',', 1) AS DECIMAL(10,6))))";
        $lngCol = "IF(cm_membergooglemaps_lng<>'' AND cm_membergooglemaps_lng IS NOT NULL, RADIANS(cm_membergooglemaps_lng), RADIANS(CAST(SUBSTRING_INDEX(cm_membergooglemaps_coords, ',', -1) AS DECIMAL(10,6))))";
        return '6371*ACOS('.$sin.'*SIN('.$latCol.')+'.$cos.'*COS('.$latCol.')*COS('.$lngCol.'-'.$lng1.'))';
    }

    private function geocodeVisitorLocation(): array
    {
        $location = trim((string) (Input::get('cm_location') ?? ''));
        $postal = trim((string) (Input::get('plz') ?? (Input::get('plzarea') ?? '')));
        if ($postal === '' && preg_match('~^\d{3,5}$~', $location)) { $postal = $location; }
        $country = strtoupper(trim((string) (Input::get('country') ?? (Input::get('cm_country') ?? 'DE'))));

        // Try multiple sources for API key to be robust in prod env
        $apiKey = '';
        $source = null;
        try {
            if (!empty($_SERVER['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_SERVER['GOOGLE_MAPS_API_KEY']; $source = 'SERVER'; }
            elseif (!empty($_ENV['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_ENV['GOOGLE_MAPS_API_KEY']; $source = 'ENV'; }
            elseif (($tmp = getenv('GOOGLE_MAPS_API_KEY')) !== false && $tmp !== '') { $apiKey = (string) $tmp; $source = 'getenv'; }
            elseif (\Contao\System::getContainer()->hasParameter('env(GOOGLE_MAPS_API_KEY)')) { $apiKey = (string) \Contao\System::getContainer()->getParameter('env(GOOGLE_MAPS_API_KEY)'); $source = 'container'; }
        } catch (\Throwable $e) {}
        if (!$apiKey) {
            $this->dbg('geocode_skip', [ 'reason' => 'no_api_key' ]);
            return [null, null];
        }
        $this->dbg('geocode_key', [ 'source' => $source, 'present' => true ]);

        $components = [];
        if ($postal !== '') { $components[] = 'postal_code:'.rawurlencode($postal); }
        if ($country !== '') { $components[] = 'country:'.rawurlencode($country); }
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?language=de&key='.rawurlencode($apiKey);
        if ($components) {
            $url .= '&components='.implode('|', $components);
            if ($location !== '' && !preg_match('~\d{3,}~', $location)) {
                $url .= '&address='.rawurlencode($location);
            }
        } else {
            $addr = $location !== '' ? $location : $postal;
            if ($country) { $addr = trim($addr.' '.$country); }
            if ($addr === '') { return [null, null]; }
            $url .= '&address='.rawurlencode($addr);
        }

        $this->dbg('geocode_request', [
            'url' => preg_replace('~key=[^&]+~', 'key=REDACTED', $url),
            'components' => $components,
            'location' => $location,
            'postal' => $postal,
            'country' => $country,
        ]);

        $cacheDir = dirname(__DIR__, 4).'/var/logs';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0775, true); }
        $cacheFile = $cacheDir.'/geocode-cache.json';
        $cache = [];
        if (is_file($cacheFile)) {
            try { $cache = json_decode((string) file_get_contents($cacheFile), true) ?: []; } catch (\Throwable $e) {}
        }
        $key = substr(sha1($url), 0, 16);
        if (isset($cache[$key]) && isset($cache[$key]['t']) && isset($cache[$key]['lat']) && isset($cache[$key]['lng'])) {
            if ((time() - (int)$cache[$key]['t']) < 14*24*3600) {
                return [(float)$cache[$key]['lat'], (float)$cache[$key]['lng']];
            }
        }

        $opts = ['http' => ['timeout' => 4.0]];
        try {
            $json = @file_get_contents($url, false, stream_context_create($opts));
            if ($json === false) { return [null, null]; }
            $data = json_decode($json, true);
            if (!\is_array($data) || ($data['status'] ?? '') !== 'OK') {
                $this->dbg('geocode_response', [ 'status' => $data['status'] ?? 'NO_DATA', 'raw' => substr($json, 0, 500) ]);
                return [null, null];
            }
            $loc = $data['results'][0]['geometry']['location'] ?? null;
            if (!$loc || !isset($loc['lat'], $loc['lng'])) { return [null, null]; }
            $lat = (float) $loc['lat'];
            $lng = (float) $loc['lng'];
            $cache[$key] = ['t' => time(), 'lat' => $lat, 'lng' => $lng, 'q' => $url];
            @file_put_contents($cacheFile, json_encode($cache));
            $this->dbg('geocode_response', [ 'status' => 'OK', 'lat' => $lat, 'lng' => $lng ]);
            return [$lat, $lng];
        } catch (\Throwable $e) {
            $this->dbg('geocode_error', [ 'error' => $e->getMessage() ]);
            return [null, null];
        }
    }

    private function dbg(string $step, array $context = []): void
    {
        if (!$this->cmDebugEnabled) { return; }
        try {
            $dir = dirname(__DIR__, 4).'/var/logs';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            // Write to a dedicated radius debug log file
            $file = $dir.'/cm_member_radius.log';
            $entry = [
                'ts' => date('c'),
                'module' => (int) ($this->id ?? 0),
                'step' => $step,
                'uri' => (string) (\Contao\Environment::get('request') ?? ''),
                'data' => $context,
            ];
            @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }
}
