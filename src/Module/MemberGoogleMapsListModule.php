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
    // Use the tableless list template as unified default for all module types
    protected $strTemplate = 'mod_cm_memberlist_googlemaps_tabless';
    private bool $cmRadiusUsed = false;
    private array $cmDebug = [];
    private bool $cmDebugEnabled = false;

    public function generate()
    {
        // Unconditional probe to verify active class at runtime
        try {
            $this->logProbe('enter_generate', [
                'class' => __CLASS__,
                'moduleId' => (int) ($this->id ?? 0),
                'request' => (string) (Environment::get('request') ?? ''),
            ]);
        } catch (\Throwable $e) { /* ignore */ }
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
            // Normalize historical names to the tableless variant
            if ($chosen === 'mod_cm_memberlist_googlemaps_tables') { $chosen = 'mod_cm_memberlist_googlemaps_tabless'; }
            if ($chosen === 'mod_cm_memberlist_googlemaps_table')  { $chosen = 'mod_cm_memberlist_googlemaps_tabless'; }
            if ($chosen === 'mod_cm_memberlist_googlemaps')        { $chosen = 'mod_cm_memberlist_googlemaps_tabless'; }
            if ($chosen === '') {
                // Always use the tableless template by default
                $chosen = 'mod_cm_memberlist_googlemaps_tabless';
            }
            // Enforce unified tableless template regardless of selection
            $this->strTemplate = 'mod_cm_memberlist_googlemaps_tabless';
            try { $this->logProbe('template_selected', ['chosen' => $this->strTemplate, 'moduleId' => (int)($this->id ?? 0)]); } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            // keep default template
        }

        // Enable debug logging early if requested via query
        $this->cmDebugEnabled = (\Contao\Input::get('cm_debug') !== null);

        // Early redirect: if the search was submitted on a different page,
        // forward to the configured result page with the current query string.
        try {
            $hasSearch = (
                \Contao\Input::get('cm_location') !== null
                || \Contao\Input::get('cm_country') !== null
                || \Contao\Input::get('cm_max_dist') !== null
                || \Contao\Input::get('cm_max_dist_select') !== null
                || \Contao\Input::get('for') !== null
                || \Contao\Input::get('cm_search') !== null
                || \Contao\Input::get('plz') !== null
                || \Contao\Input::get('plzarea') !== null
                || \Contao\Input::get('cmf_submitted') !== null
            );
            if ($hasSearch) {
                $this->logProbe('has_search_detected', ['class' => __CLASS__, 'moduleId' => (int) ($this->id ?? 0)]);
                // Resolve target page (prefer Weiterleitungsseite)
                $tg = (int) ($this->cmf_target ?? 0);
                $pg = (int) ($this->cm_memberlist_pg ?? 0);
                $jt = (int) ($this->jumpTo ?? 0);
                if ((!$tg && !$pg && !$jt) && isset($this->id)) {
                    $res = \Contao\Database::getInstance()->prepare('SELECT cmf_target, cm_memberlist_pg, jumpTo FROM tl_module WHERE id=?')->limit(1)->execute((int)$this->id);
                    if ($res->numRows) { $tg = (int)$res->cmf_target; $pg = (int)$res->cm_memberlist_pg; $jt = (int)$res->jumpTo; }
                }
                $targetId = $tg ?: ($pg ?: $jt);
                if ($targetId > 0) {
                    if ($page = \Contao\PageModel::findByPk($targetId)) {
                        $url = $page->getFrontendUrl();
                        $base = rtrim(\Contao\Environment::get('base'), '/');
                        $targetAbs = $base.'/'.ltrim($url, '/');
                        $reqPath = parse_url(\Contao\Environment::get('request') ?: '', PHP_URL_PATH) ?: '';
                        $tgtPath = parse_url($targetAbs, PHP_URL_PATH) ?: '';
                        // Adjust for preview mode
                        if ($reqPath && str_starts_with($reqPath, '/preview.php/') && !str_starts_with($tgtPath, '/preview.php/')) {
                            $tgtPath = '/preview.php'.$tgtPath;
                        }
                        // Build query string, enforcing default cm_country if configured and missing
                        $qs = $_SERVER['QUERY_STRING'] ?? '';
                        $params = [];
                        if ($qs !== '') { parse_str($qs, $params); }
                        $defCountry = strtolower(trim((string) ($this->cm_map_country ?? '')));
                        if (!isset($params['cm_country']) && $defCountry !== '') {
                            $params['cm_country'] = $defCountry;
                            $qs = http_build_query($params);
                        }
                        $target = $tgtPath.($qs ? ('?'.$qs) : '');
                        if ($reqPath !== $tgtPath) {
                            $this->dbg('redirect_force', [ 'targetId' => $targetId, 'target' => $target ]);
                            $this->logSys('member_search_redirect', [ 'targetId' => $targetId, 'target' => $target ]);
                            \Contao\Controller::redirect($target);
                        } else {
                            $this->dbg('redirect_skip_same_path', [ 'path' => $reqPath ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Do not break page on redirect errors
            $this->dbg('redirect_error', [ 'error' => $e->getMessage() ]);
        }
        $html = parent::generate();
        return $html;
    }

    protected function compile(): void
    {
        // Unconditional probe for compile to verify class/template usage
        try { $this->logProbe('enter_compile', ['class' => __CLASS__, 'moduleId' => (int) ($this->id ?? 0)]); } catch (\Throwable $e) {}
        $this->Template->action = Environment::get('request');
        $this->cmDebugEnabled = (Input::get('cm_debug') !== null);

        // Detect whether any filter parameters are present (non-empty)
        $hasAnyFilter = false;
        $p = static fn($k) => trim((string) (Input::get($k) ?? ''));
        $nonEmpty = static fn($v) => $v !== '';
        $candidates = [
            $p('cm_search'), $p('for'), $p('cm_location'), $p('plz'), $p('plzarea'),
            $p('cm_max_dist'), $p('cm_max_dist_select')
        ];
        foreach ($candidates as $val) { if ($nonEmpty($val)) { $hasAnyFilter = true; break; } }
        if (!$hasAnyFilter) {
            // Treat explicit cm_country param as filter only if it is present in request
            if (Input::get('cm_country') !== null && $p('cm_country') !== '') { $hasAnyFilter = true; }
        }
        // Decide whether to suppress results initially but still render the search form
        $showAllOnEmpty = (bool) ($this->cm_memberlist_showall_on_empty ?? false);
        $suppressInitial = (!$hasAnyFilter && !$showAllOnEmpty);
        // Expose whether a search was used to templates (for placing a repeated form below results)
        $this->Template->searchUsed = $hasAnyFilter;

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

        // Search (rename param 'for' -> 'cm_search', keep BC fallback)
        $search = trim((string) (Input::get('cm_search') ?? (Input::get('for') ?? '')));
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
        // Perform search: single-field or multi-field based on module flags
        if ($search !== '') {
            // Determine flags early (fallback to DB if properties not mapped yet)
            $fieldsearch = (bool) ($this->cm_memberlist_fieldsearch ?? false);
            $multifield  = (bool) ($this->cm_memberlist_multifieldseach ?? false);
            try {
                if (!$fieldsearch || !$multifield) {
                    $res = Database::getInstance()->prepare('SELECT cm_memberlist_fieldsearch, cm_memberlist_multifieldseach FROM tl_module WHERE id=?')->limit(1)->execute((int) ($this->id ?? 0));
                    if ($res->numRows) {
                        if (!$fieldsearch) { $fieldsearch = (bool) $res->cm_memberlist_fieldsearch; }
                        if (!$multifield)  { $multifield  = (bool) $res->cm_memberlist_multifieldseach; }
                    }
                }
            } catch (\Throwable $e) {}

            // If an address string is present, limit the keyword search to non-address fields
            $addrQueryRaw = trim((string) (Input::get('cm_location') ?? ''));
            $addressFieldNames = ['street','postal','city','country'];
            $addressFields = array_values(array_intersect($searchFields, $addressFieldNames));
            $nonAddressFields = array_values(array_diff($searchFields, $addressFields));
            $targetFields = ($addrQueryRaw !== '' && $nonAddressFields) ? $nonAddressFields : $searchFields;

            if ($fieldsearch && !$multifield) {
                // Single-field search: respect selected field if valid; otherwise fallback to multi
                $chosen = (string) (Input::get('search') ?? '');
                if ($chosen !== '' && in_array($chosen, (array) $targetFields, true)) {
                    $where .= ' AND '.$chosen.' LIKE ?';
                    $values[] = '%'.$search.'%';
                    $this->dbg('search_single', [ 'field' => $chosen ]);
                } else {
                    $likes = [];
                    foreach ($targetFields as $sf) { $likes[] = $sf.' LIKE ?'; $values[] = '%'.$search.'%'; }
                    if ($likes) { $where .= ' AND ('.implode(' OR ', $likes).')'; }
                    $this->dbg('search_multi_fallback', [ 'fields' => $targetFields ]);
                }
            } else {
                // Multi-field search across configured fields
                $likes = [];
                foreach ($targetFields as $sf) { $likes[] = $sf.' LIKE ?'; $values[] = '%'.$search.'%'; }
                if ($likes) { $where .= ' AND ('.implode(' OR ', $likes).')'; }
                $this->dbg('search_multi', [ 'fields' => $targetFields ]);
            }
        }

        // Per-field filters (visible when address form is enabled): build AND filters for each filled field
        $perFieldFilters = [];
        $perFieldValues = [];
        // Skip address fields here (they have dedicated inputs)
        $addressFieldNames = ['street','postal','city','country'];
        foreach ($searchFields as $sf) {
            if (in_array($sf, $addressFieldNames, true)) { continue; }
            $param = 'cm_'.$sf;
            $val = trim((string) (Input::get($param) ?? ''));
            if ($val !== '') {
                $perFieldFilters[] = $sf.' LIKE ?';
                $perFieldValues[] = '%'.$val.'%';
            }
        }
        if ($perFieldFilters) {
            $where .= ' AND ('.implode(' OR ', $perFieldFilters).')';
            foreach ($perFieldValues as $v) { $values[] = $v; }
            $this->dbg('search_per_field_or', [ 'fields' => $searchFields ]);
        }

        // Checkbox group filters for Leistungen (as separate groups)
        $groupFilters = [];
        $groupValues = [];
        $lgaSel = (array) (Input::get('lga') ?? []);
        if ($lgaSel) {
            $ors = [];
            foreach ($lgaSel as $opt) { $ors[] = 'LeistungenAllgemein LIKE ?'; $groupValues[] = '%'.$opt.'%'; }
            if ($ors) { $groupFilters[] = '('.implode(' OR ', $ors).')'; }
        }
        $lieferantSel = (array) (Input::get('lieferant') ?? []);
        if ($lieferantSel) {
            $ors = [];
            foreach ($lieferantSel as $opt) { $ors[] = 'Lieferant LIKE ?'; $groupValues[] = '%'.$opt.'%'; }
            if ($ors) { $groupFilters[] = '('.implode(' OR ', $ors).')'; }
        }
        $sachSel = (array) (Input::get('sach') ?? []);
        if ($sachSel) {
            $ors = [];
            foreach ($sachSel as $opt) { $ors[] = 'Sachverstaendiger LIKE ?'; $groupValues[] = '%'.$opt.'%'; }
            if ($ors) { $groupFilters[] = '('.implode(' OR ', $ors).')'; }
        }
        if ($groupFilters) {
            $where .= ' AND '.implode(' AND ', $groupFilters);
            foreach ($groupValues as $gv) { $values[] = $gv; }
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
        // Expose whether PLZ search (plz/plzarea) was used for controlling distance output in templates
        $this->Template->plzSearchUsed = (($plzarea !== '') || ($postal !== ''));
        // Note: country filtering handled after distance decision as well.
        
        // NOTE: textual address LIKE filters will be applied after we decide about radius filtering

        // Fields to display
        $displayFields = [];
        // Fields selected under "Durchsuchte Felder" may be shown as well when address form is enabled
        $searchFieldsConfigured = StringUtil::deserialize($this->ml_search_fields ?? '', true);
        if (!\is_array($searchFieldsConfigured)) { $searchFieldsConfigured = []; }
        $rows = StringUtil::deserialize($this->cm_membergooglemaps_fieldslist ?? '', true);
        if ($rows && \is_array($rows)) {
            foreach ($rows as $r) { if (!empty($r['field'])) { $displayFields[] = (string) $r['field']; } }
        }
        if (!$displayFields) { $displayFields = $searchFieldsConfigured; }
        if (!$displayFields) { $displayFields = ['company','firstname','lastname','street','postal','city','website']; }
        // Early decide if address form is enabled to include search fields into display
        $showAddressEarly = (bool) ($this->cm_memberlist_addressform ?? false);
        if (!$showAddressEarly) {
            try {
                $resAddr = Database::getInstance()->prepare('SELECT cm_memberlist_addressform FROM tl_module WHERE id=?')->limit(1)->execute((int) ($this->id ?? 0));
                if ($resAddr->numRows) { $showAddressEarly = (bool) $resAddr->cm_memberlist_addressform; }
            } catch (\Throwable $e) {}
        }
        if ($showAddressEarly && $searchFieldsConfigured) {
            $displayFields = array_values(array_unique(array_merge($displayFields, $searchFieldsConfigured)));
        }

        // Determine which service groups are selected for output in the module
        $serviceFieldsSelected = array_values(array_intersect($displayFields, ['LeistungenAllgemein','Lieferant','Sachverstaendiger']));
        $hasAnyService = !empty($serviceFieldsSelected);
        // Collapse service groups into a single visible field "LeistungenAllgemein"
        if ($hasAnyService) {
            $displayFields = array_values(array_unique(array_merge(
                array_diff($displayFields, ['Lieferant','Sachverstaendiger']),
                ['LeistungenAllgemein']
            )));
        }
        $this->Template->hasServiceFields = $hasAnyService;

        // SELECT (always include address columns so templates can render city/postal regardless of config)
        // Ensure avatar, address and person name columns are selected so templates can render them regardless of field config
        $baseCols = ['id','alias','cm_membergooglemaps_coords','groups','avatar','street','postal','city','country','firstname','lastname','phone','fax','email','website','LeistungenAllgemein','Lieferant','Sachverstaendiger'];
        // Always include configured search fields in SELECT so they are available for output when address form is enabled
        $select = array_unique(array_merge($baseCols, $displayFields, (array)$searchFieldsConfigured));
        $selectSql = implode(',', array_filter(array_map(fn($f) => preg_match('~^[a-z0-9_]+$~i', $f) ? $f : null, $select)));
        if (!$selectSql) { $selectSql = 'id,alias,cm_membergooglemaps_coords'; }

        // Distance filter using Google geocoded visitor location
        $lat1 = null; $lng1 = null; $hasLocal = false;
        // Determine effective max distance: request value or module defaults
        // Max distance: accept new name 'cm_max_dist_select' (dropdown) and fallback to 'cm_max_dist'
        $maxDist = (int) (Input::get('cm_max_dist_select') ?? (Input::get('cm_max_dist') ?? 0));
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
        // Trigger geocoding when a free-text location is provided OR when PLZ + distance are given
        $wantGeocode = false;
        $cmLoc = trim((string) (Input::get('cm_location') ?? ''));
        if ($cmLoc !== '') { $wantGeocode = true; }
        if (!$wantGeocode) {
            $plzOnly = trim((string) $postal) !== '';
            if ($plzOnly && $maxDist > 0) { $wantGeocode = true; }
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

        $items = [];
        if (!$suppressInitial) {
            $stmt = Database::getInstance()->prepare("SELECT $selectSql FROM tl_member WHERE $where ORDER BY $orderBy");
            $result = $stmt->execute(...$values);

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
                        // Label without scheme and without trailing slash
                        $label = preg_replace('~^https?://~i', '', $rawSite);
                        $label = rtrim($label, '/');
                        $item['website'] = $label ?: $rawSite;
                        $item['websiteUrl'] = $href;
                    } else {
                        $item[$f] = $val;
                    }
                } else {
                    $item[$f] = $val;
                }
                }
            // Ensure commonly used fields are present even if not part of displayFields
            foreach (['firstname','lastname','phone','fax','email','website'] as $ensure) {
                if (!array_key_exists($ensure, $item)) {
                    $item[$ensure] = (string) ($row[$ensure] ?? '');
                }
            }
            // Combine selected service groups into one output under "Leistungen Allgemein"
            try {
                $all = [];
                $groupsToCombine = $serviceFieldsSelected ?: [];
                foreach ($groupsToCombine as $gf) {
                    $raw = $row[$gf] ?? '';
                    $arr = [];
                    if (is_string($raw) && strpos($raw, 'a:') === 0) {
                        $arr = StringUtil::deserialize($raw, true);
                    } elseif (is_string($raw) && $raw !== '') {
                        // Fallback: comma separated string
                        $arr = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
                    } elseif (is_array($raw)) {
                        $arr = $raw;
                    }
                    foreach ((array)$arr as $v) {
                        if ($v !== '') { $all[] = (string) $v; }
                    }
                }
                $all = array_values(array_unique($all));
                $item['LeistungenAllgemein'] = $all ? implode(', ', $all) : '';
                // Clear the other group outputs so the templates only show the combined list
                $item['Lieferant'] = '';
                $item['Sachverstaendiger'] = '';
            } catch (\Throwable $e) { /* ignore combine errors */ }
                $items[] = $item;
            }
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
        // Do not show a "not found" message on initial, unfiltered load when suppression is active
        $this->Template->notFoundMessage = $suppressInitial ? '' : (string) ($this->cm_memberlist_notfound ?: 'Keine Einträge gefunden.');
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
            // Prefer Symfony container parameter which evaluates env in prod
            $c = \Contao\System::getContainer();
            if ($c->hasParameter('google_maps_api_key')) {
                $apiKey = (string) $c->getParameter('google_maps_api_key');
            }
            // Fallbacks if container param is not set
            if ($apiKey === '') {
                if (!empty($_SERVER['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_SERVER['GOOGLE_MAPS_API_KEY']; }
                elseif (!empty($_ENV['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_ENV['GOOGLE_MAPS_API_KEY']; }
                elseif (($tmp = getenv('GOOGLE_MAPS_API_KEY')) !== false && $tmp !== '') { $apiKey = (string) $tmp; }
                elseif ($c->hasParameter('env(GOOGLE_MAPS_API_KEY)')) { $apiKey = (string) $c->getParameter('env(GOOGLE_MAPS_API_KEY)'); }
            }
        } catch (\Throwable $e) {}
        // Fallback: parse .env.local or .env if runtime env vars are not exposed (e.g., prod)
        if ($apiKey === '') {
            try {
                $base = \Contao\System::getContainer()->getParameter('kernel.project_dir');
                foreach (['/.env.local','/.env'] as $rel) {
                    $path = $base.$rel;
                    if (!is_file($path) || !is_readable($path)) { continue; }
                    $lines = @file($path, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [];
                    foreach ($lines as $line) {
                        if (preg_match('~^\s*#~', $line)) { continue; }
                        if (preg_match('~^\s*GOOGLE_MAPS_API_KEY\s*=\s*"?([^"\r\n#]+)~', $line, $m)) {
                            $apiKey = trim($m[1]);
                            break 2;
                        }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        $this->Template->googleApiKey = $apiKey;
        // Debug: note api key presence when cm_debug is enabled
        $this->dbg('map_key', ['present' => $apiKey !== '' ? 1 : 0]);
        // Ensure bundled CSS for placeholders is included
        if (!isset($GLOBALS['TL_CSS']['websailing_google_map'])) {
            // Use the actual public bundle path name (see public/bundles)
            $GLOBALS['TL_CSS']['websailing_google_map'] = 'bundles/googlemap/css/google_map.css|static';
        }
        // Build minimal Google Maps initialization for template compatibility
        // Avoid using undefined $includeMap variable here; check module flag directly
        if ((bool) ($this->cm_map_onlist ?? false) && $apiKey) {
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
        // Debug: expose map flags
        $this->dbg('map_flags', [
            'includeMap' => (int)$includeMap,
            'acceptanceRequired' => (int) (bool) ($this->cm_gc_acceptance_required ?? false),
            'position' => (string) ($this->mapPosition ?? 'bottom')
        ]);
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
            $res = Database::getInstance()->prepare('SELECT cm_memberlist_addressform, cm_memberlist_distanceform, cm_memberlist_distanceasdropdown, cm_map_country, cm_map_country_as_select, cm_memberlist_fieldsearch, cm_memberlist_multifieldseach, cm_memberlist_plzsearch FROM tl_module WHERE id=?')->limit(1)->execute((int) ($this->id ?? 0));
            if ($res->numRows) {
                // Always take DB values when present to avoid sticky defaults
                $showAddress   = (bool) $res->cm_memberlist_addressform;
                $distForm      = (bool) $res->cm_memberlist_distanceform;
                $distAsDrop    = (bool) $res->cm_memberlist_distanceasdropdown;
                $selectCountry = (bool) $res->cm_map_country_as_select;
                $fieldsearch   = (bool) $res->cm_memberlist_fieldsearch;
                $multifield    = (bool) $res->cm_memberlist_multifieldseach;
                $plzsearch     = (bool) $res->cm_memberlist_plzsearch;
            }
        } catch (\Throwable $e) {}

        // All-in-one form: show if any search control is enabled (address, keyword, PLZ or distance)
        $this->Template->allInOne = ($showAddress || $fieldsearch || $multifield || $plzsearch || $distForm);

        // Labels
        try { \Contao\Controller::loadLanguageFile('default'); } catch (\Throwable $e) {}
        $MSC = $GLOBALS['TL_LANG']['MSC'] ?? [];
        $this->Template->search_label    = (string) ($MSC['search'] ?? 'Suchen');
        $this->Template->per_page_label  = (string) ($MSC['list_perPage'] ?? 'Ergebnisse pro Seite');
        $this->Template->fields_label    = (string) (($MSC['all_fields'][0] ?? null) ?: 'Feld');
        // Requested label for text search field
        $this->Template->keywords_label  = 'Firma / Name / Leistungen';
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
        $this->Template->for = $search; // BC for templates still using 'for'
        $this->Template->cm_search = $search;
        // Build search field options (for single-field dropdown), based on configured search fields
        $opts = '';
        $curSel = (string) (Input::get('search') ?? '');
        if ($fieldsearch && !$multifield) {
            try { Controller::loadLanguageFile('tl_member'); Controller::loadDataContainer('tl_member'); } catch (\Throwable $e) {}
            foreach ($searchFields as $sf) {
                $label = $GLOBALS['TL_DCA']['tl_member']['fields'][$sf]['label'][0] ?? ($GLOBALS['TL_LANG']['tl_member'][$sf][0] ?? ucfirst((string)$sf));
                $sel = ($sf === $curSel) ? ' selected' : '';
                $opts .= '<option value="'.htmlspecialchars($sf, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars((string)$label, ENT_QUOTES).'</option>';
            }
        }
        $this->Template->search_fields = $opts;

        // Build per-field input HTML (only render if address fields section is enabled)
        $fieldInputsHtml = '';
        if ($showAddressEarly && $searchFieldsConfigured) {
            try { Controller::loadLanguageFile('tl_member'); Controller::loadDataContainer('tl_member'); } catch (\Throwable $e) {}
            $addressFieldNames = ['street','postal','city','country'];
            foreach ($searchFieldsConfigured as $sf) {
                if (in_array($sf, $addressFieldNames, true)) { continue; }
                $lab = $GLOBALS['TL_DCA']['tl_member']['fields'][$sf]['label'][0]
                    ?? ($GLOBALS['TL_LANG']['tl_member'][$sf][0] ?? ucfirst((string)$sf));
                $val = htmlspecialchars((string) (Input::get('cm_'.$sf) ?? ''), ENT_QUOTES);
                $fieldInputsHtml .= '<label class="cm_sf_label" for="cm_'.htmlspecialchars($sf, ENT_QUOTES).'">'.htmlspecialchars((string)$lab, ENT_QUOTES).'</label>';
                $fieldInputsHtml .= '<input type="text" class="cm_sf_input" name="cm_'.htmlspecialchars($sf, ENT_QUOTES).'" id="cm_'.htmlspecialchars($sf, ENT_QUOTES).'" value="'.$val.'">';
            }
        }
        // Do not append service group checkboxes in the request form
        $this->Template->field_inputs = $fieldInputsHtml;
        // Build unified search inputs block (non-address fields) in configured order
        $searchInputsHtml = '';
        if ($searchFieldsConfigured) {
            try { Controller::loadLanguageFile('tl_member'); Controller::loadDataContainer('tl_member'); } catch (\Throwable $e) {}
            $addressFieldNames = ['street','postal','city','country'];
            foreach ($searchFieldsConfigured as $sf) {
                if (in_array($sf, $addressFieldNames, true)) { continue; }
                $lab = $GLOBALS['TL_DCA']['tl_member']['fields'][$sf]['label'][0]
                    ?? ($GLOBALS['TL_LANG']['tl_member'][$sf][0] ?? ucfirst((string)$sf));
                $val = htmlspecialchars((string) (Input::get('cm_'.$sf) ?? ''), ENT_QUOTES);
                $searchInputsHtml .= '<label class="cm_field_label" for="cm_'.htmlspecialchars($sf, ENT_QUOTES).'">'.htmlspecialchars((string)$lab, ENT_QUOTES).'</label>';
                $searchInputsHtml .= '<input type="text" class="cm_field_input" name="cm_'.htmlspecialchars($sf, ENT_QUOTES).'" id="cm_'.htmlspecialchars($sf, ENT_QUOTES).'" value="'.$val.'">';
            }
        }
        $this->Template->search_inputs = $searchInputsHtml;

        // Do not render individual address inputs (cm_street, cm_postal, cm_city) anymore
        $this->Template->address_inputs = '';
        $this->Template->plzsearch     = $plzsearch;
        $this->Template->plzarea       = trim((string) (Input::get('plzarea') ?? ''));
        // Radius block should render when address OR distance is enabled
        $this->Template->radiusform    = ($showAddress || $distForm);
        $this->Template->distanceform  = $distForm;
        $this->Template->distanceasdropdown = $distAsDrop;

        // Country select (only if 'Länderfeld als Auswahl' is active)
        $defaultCountry = strtolower(trim((string) ($this->cm_map_country ?? '')));
        if ($defaultCountry === '') { $defaultCountry = 'de'; }
        $selected = strtolower(trim((string) (Input::get('cm_country') ?? $defaultCountry)));
        if ($selectCountry) {
            $countryOpts = [];
            try {
                $countries = \Contao\System::getContainer()->get('contao.intl.countries')->getCountries();
                foreach ($countries as $code => $label) { $countryOpts[strtolower($code)] = $label; }
            } catch (\Throwable $e) { $countryOpts = ['de'=>'Deutschland','at'=>'Österreich','ch'=>'Schweiz']; }
            $countryHtml = '<select name="cm_country" class="cm_country">';
            foreach ($countryOpts as $code => $label) { $countryHtml .= '<option value="'.$code.'"'.($code===$selected?' selected="selected"':'').'>'.htmlspecialchars($label, ENT_QUOTES).'</option>'; }
            $countryHtml .= '</select>';
            $this->Template->visitorcountry = $countryHtml;
            $this->Template->show_country = true;
        } else {
            // Hide country input when not configured as select
            $this->Template->visitorcountry = '';
            $this->Template->show_country = false;
        }

        // Distance options (read dropdown first, fallback to text)
        $maxDist = (int) (Input::get('cm_max_dist_select') ?? (Input::get('cm_max_dist') ?? 0));
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

        // Ensure unified CSS class on all module types of this bundle
        try {
            $cls = (string) ($this->Template->class ?? '');
            if (strpos($cls, 'mod_cm_memberfinder') === false) {
                $this->Template->class = trim($cls.' mod_cm_memberfinder');
            }
        } catch (\Throwable $e) { /* ignore */ }

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
            $c = \Contao\System::getContainer();
            if ($c->hasParameter('google_maps_api_key')) { $apiKey = (string) $c->getParameter('google_maps_api_key'); $source = 'container_param'; }
            if ($apiKey === '') {
                if (!empty($_SERVER['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_SERVER['GOOGLE_MAPS_API_KEY']; $source = 'SERVER'; }
                elseif (!empty($_ENV['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_ENV['GOOGLE_MAPS_API_KEY']; $source = 'ENV'; }
                elseif (($tmp = getenv('GOOGLE_MAPS_API_KEY')) !== false && $tmp !== '') { $apiKey = (string) $tmp; $source = 'getenv'; }
                elseif ($c->hasParameter('env(GOOGLE_MAPS_API_KEY)')) { $apiKey = (string) $c->getParameter('env(GOOGLE_MAPS_API_KEY)'); $source = 'container_env'; }
            }
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
            $dir = '';
            try {
                $base = \Contao\System::getContainer()->getParameter('kernel.project_dir');
                if (is_dir($base.'/var/log')) { $dir = $base.'/var/log'; }
                else { $dir = $base.'/var/logs'; }
            } catch (\Throwable $e) {
                // Fallback from vendor path up to project root
                $base = dirname(__DIR__, 6);
                if (is_dir($base.'/var/log')) { $dir = $base.'/var/log'; }
                else { $dir = $base.'/var/logs'; }
            }
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

    private function logSys(string $event, array $context = []): void
    {
        try {
            $msg = $event.(empty($context) ? '' : (' '.json_encode($context, JSON_UNESCAPED_SLASHES)));
            // Prefer Contao's monolog logger
            if (\Contao\System::getContainer()->has('monolog.logger.contao')) {
                $logger = \Contao\System::getContainer()->get('monolog.logger.contao');
                if (method_exists($logger, 'info')) { $logger->info($msg); return; }
            }
            // Fallback to legacy System::log if available
            if (method_exists(\Contao\System::class, 'log')) {
                \Contao\System::log($msg, __METHOD__, TL_GENERAL);
                return;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        @error_log($event.(empty($context) ? '' : (' '.json_encode($context))));
    }

    private function logProbe(string $step, array $context = []): void
    {
        try {
            $dir = '';
            try {
                $base = \Contao\System::getContainer()->getParameter('kernel.project_dir');
                if (is_dir($base.'/var/log')) { $dir = $base.'/var/log'; }
                else { $dir = $base.'/var/logs'; }
            } catch (\Throwable $e) {
                $base = dirname(__DIR__, 6);
                if (is_dir($base.'/var/log')) { $dir = $base.'/var/log'; }
                else { $dir = $base.'/var/logs'; }
            }
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $file = $dir.'/cm_member_probe.log';
            $entry = [
                'ts' => date('c'),
                'module' => (int) ($this->id ?? 0),
                'step' => $step,
                'uri' => (string) (\Contao\Environment::get('request') ?? ''),
                'data' => $context,
            ];
            @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
        } catch (\Throwable $e) { /* ignore */ }
    }
}
