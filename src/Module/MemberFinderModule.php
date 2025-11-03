<?php

namespace Cm\MemberGoogleMapsBundle\Module;

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\Environment;
use Contao\Input;
use Contao\Module;
use Contao\PageModel;
use Contao\StringUtil;

class MemberFinderModule extends Module
{
    protected $strTemplate = 'mod_cm_memberlist_finder';

    public function generate()
    {
        if (\defined('TL_MODE') && \TL_MODE === 'BE') {
            $tpl = new BackendTemplate('be_wildcard');
            $tpl->wildcard = '### CM MEMBER FINDER ###';
            $tpl->title = $this->headline;
            $tpl->id = $this->id;
            $tpl->link = $this->name;
            $tpl->href = 'contao?do=themes&table=tl_module&act=edit&id='.$this->id;
            return $tpl->parse();
        }
        return parent::generate();
    }

    protected function compile(): void
    {
        // Probe log to confirm whether this class is active on the page
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
                'step' => 'finder_enter_compile',
                'class' => __CLASS__,
                'uri' => (string) (\Contao\Environment::get('request') ?? ''),
            ];
            @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
        } catch (\Throwable $e) { /* ignore */ }
        // Determine target action (prefer explicit target page)
        // Determine target action (prefer explicit target page)
        $this->Template->action = Environment::get('request');
        $this->logError('finder_enter', [
            'moduleId' => (int) ($this->id ?? 0),
            'request'  => (string) (Environment::get('request') ?? ''),
        ]);
        $lockedAction = false;
        // Prefer explicit Weiterleitungsseite (cmf_target) over legacy Listenseite
        if (!empty($this->cmf_target)) {
            try {
                if ($page = PageModel::findByPk((int) $this->cmf_target)) {
                    $url = $page->getFrontendUrl();
                    $base = rtrim(Environment::get('base'), '/');
                    $this->Template->action = $base . '/' . ltrim($url, '/');
                    $this->logError('action_from_cmf_target', [ 'cmf_target' => (int) $this->cmf_target, 'action' => $this->Template->action ]);
                    $lockedAction = true;
                }
            } catch (\Throwable $e) {}
        } elseif (!empty($this->cm_memberlist_pg)) {
            try {
                if ($page = PageModel::findByPk((int) $this->cm_memberlist_pg)) {
                    $url = $page->getFrontendUrl();
                    $base = rtrim(Environment::get('base'), '/');
                    $this->Template->action = $base . '/' . ltrim($url, '/');
                    $this->logError('action_from_cm_memberlist_pg', [ 'cm_memberlist_pg' => (int) $this->cm_memberlist_pg, 'action' => $this->Template->action ]);
                    $lockedAction = true;
                }
            } catch (\Throwable $e) {}
        } elseif (!empty($this->jumpTo)) {
            try {
                if ($page = PageModel::findByPk((int) $this->jumpTo)) {
                    $url = $page->getFrontendUrl();
                    $base = rtrim(Environment::get('base'), '/');
                    $this->Template->action = $base . '/' . ltrim($url, '/');
                    $this->logError('action_from_jumpTo', [ 'jumpTo' => (int) $this->jumpTo, 'action' => $this->Template->action ]);
                    $lockedAction = true;
                }
            } catch (\Throwable $e) {}
        }
        // Force action to /mitglieder if alias exists
        try {
            $db = \Contao\Database::getInstance();
            if (!$lockedAction) {
                $resAlias = $db->prepare("SELECT id FROM tl_page WHERE alias=? AND type='regular' LIMIT 1")->execute('mitglieder');
                if ($resAlias->numRows) {
                    $page = \Contao\PageModel::findByPk($resAlias->id);
                    if ($page) {
                        $url = $page->getFrontendUrl();
                        $base = rtrim(\Contao\Environment::get('base'), '/');
                        $this->Template->action = $base . '/' . ltrim($url, '/');
                    } else {
                    }
                }
            }
        } catch (\Throwable $e) {}
        // Try to route to a page that contains the list module, if present
        try {
            $db = \Contao\Database::getInstance();
            // Support both legacy and new list module types
            $types = "'cm_membergooglemapsList','cm_membergooglemaps'";
            $modIds = $db->execute("SELECT id FROM tl_module WHERE type IN ($types)")
                ->fetchEach('id');
            if ($modIds) {
                $in = implode(',', array_map('intval', $modIds));
                if (!$lockedAction) {
                    $res = $db->execute("SELECT p.id FROM tl_content c JOIN tl_article a ON c.pid=a.id JOIN tl_page p ON a.pid=p.id WHERE c.type='module' AND c.module IN ($in) ORDER BY p.sorting LIMIT 1");
                    if ($res->numRows) {
                        $page = \Contao\PageModel::findByPk($res->id);
                        if ($page) {
                            $url = $page->getFrontendUrl();
                            $this->Template->action = '/' . ltrim($url, '/');
                        } else {
                        }
                    }
                }
            }
            // Fallback: page alias/title contains "mitglieder" (always apply if found)
            if (!$lockedAction) {
                $res2 = $db->prepare("SELECT id FROM tl_page WHERE (alias=? OR title LIKE ?) AND type='regular' ORDER BY sorting LIMIT 1")
                    ->execute('mitglieder', 'Mitglieder%');
                if ($res2->numRows) {
                    $page = \Contao\PageModel::findByPk($res2->id);
                    if ($page) {
                        $url = $page->getFrontendUrl();
                        $base = rtrim(\Contao\Environment::get('base'), '/');
                        $this->Template->action = $base . '/' . ltrim($url, '/');
                    } else {
                    }
                }
            }
        } catch (\Throwable $e) {
            // keep current action
        }

        // Adjust action for preview mode (ensure /preview.php prefix if necessary)
        try {
            $reqPath = parse_url(Environment::get('request'), PHP_URL_PATH) ?: '';
            $actPath = parse_url($this->Template->action, PHP_URL_PATH) ?: '';
            if ($actPath && str_starts_with($reqPath, '/preview.php/') && !str_starts_with($actPath, '/preview.php/')) {
                $this->Template->action = '/preview.php' . $actPath;
            }
        } catch (\Throwable $e) {}

        // Early ensure action targets configured Listenseite (before redirect check)
        try {
            if (!empty($this->cmf_target)) {
                if ($page = PageModel::findByPk((int) $this->cmf_target)) {
                    $url = $page->getFrontendUrl();
                    $this->Template->action = $this->buildAbsoluteAction($url);
                    $this->logError('ensure_action_cmf_target', [ 'cmf_target' => (int) $this->cmf_target, 'action' => $this->Template->action ]);
                }
            } elseif (!empty($this->cm_memberlist_pg)) {
                if ($page = PageModel::findByPk((int) $this->cm_memberlist_pg)) {
                    $url = $page->getFrontendUrl();
                    $this->Template->action = $this->buildAbsoluteAction($url);
                    $this->logError('ensure_action_cm_memberlist_pg', [ 'cm_memberlist_pg' => (int) $this->cm_memberlist_pg, 'action' => $this->Template->action ]);
                }
            } elseif (!empty($this->jumpTo)) {
                if ($page = PageModel::findByPk((int) $this->jumpTo)) {
                    $url = $page->getFrontendUrl();
                    $this->Template->action = $this->buildAbsoluteAction($url);
                    $this->logError('ensure_action_jumpTo', [ 'jumpTo' => (int) $this->jumpTo, 'action' => $this->Template->action ]);
                }
            }
        } catch (\Throwable $e) {}

        // DB fallback: read target fields directly from tl_module (in case properties are not mapped)
        try {
            $db = \Contao\Database::getInstance();
            $res = $db->prepare('SELECT cmf_target, cm_memberlist_pg, jumpTo FROM tl_module WHERE id=?')->limit(1)->execute((int) ($this->id ?? 0));
            if ($res->numRows) {
                $tg = (int) $res->cmf_target;
                $pg = (int) $res->cm_memberlist_pg;
                $jt = (int) $res->jumpTo;
                $targetId = $tg ?: ($pg ?: $jt);
                $this->dbg('target_db_fields', [ 'moduleId' => (int) ($this->id ?? 0), 'cm_memberlist_pg' => $pg, 'cmf_target' => $tg, 'jumpTo' => $jt, 'targetId' => $targetId ]);
                $this->logError('target_db_fields', [ 'moduleId' => (int) ($this->id ?? 0), 'cmf_target' => $tg, 'cm_memberlist_pg' => $pg, 'jumpTo' => $jt, 'targetId' => $targetId ]);
                if ($targetId) {
                    if ($page = PageModel::findByPk($targetId)) {
                        $url = $page->getFrontendUrl();
                        $this->Template->action = $this->buildAbsoluteAction($url);
                        $this->dbg('target_resolved', [ 'pageId' => $targetId, 'url' => $url, 'action' => $this->Template->action ]);
                        $this->logError('target_resolved', [ 'pageId' => $targetId, 'url' => $url, 'action' => $this->Template->action ]);
                    } else {
                        $this->dbg('target_missing_page', [ 'targetId' => $targetId ]);
                        $this->logError('target_missing_page', [ 'targetId' => $targetId ]);
                    }
                }
            } else {
                $this->dbg('target_db_not_found', [ 'moduleId' => (int) ($this->id ?? 0) ]);
                $this->logError('target_db_not_found', [ 'moduleId' => (int) ($this->id ?? 0) ]);
            }
        } catch (\Throwable $e) {}

        // Prepare country defaults early to be able to influence redirect/query
        $countryCode    = (string) ($this->cm_map_country ?? '');
        $selectCountry  = (bool) ($this->cm_map_country_as_select ?? false);
        $defaultCountry = strtolower(trim($countryCode));

        // Redirect to the Listenseite if any search params were submitted and we are not on the list page
        // Consider the form as submitted if any known search parameter is present
        // or if the explicit hidden submission flag is present (to ensure redirect
        // even when users submit without filling any fields).
        // Consider the form as submitted if common search parameters are present
        // Include both legacy and new parameter names used across templates
        $hasSearch = (
            Input::get('cm_location') !== null
            || Input::get('cm_country') !== null
            || Input::get('cm_max_dist') !== null
            || Input::get('cm_max_dist_select') !== null
            || Input::get('for') !== null
            || Input::get('cm_search') !== null
            || Input::get('search') !== null
            || Input::get('plz') !== null
            || Input::get('plzarea') !== null
            || Input::get('cmf_submitted') !== null
        );
        $this->dbg('redirect_check_enter', [
            'hasSearch' => $hasSearch,
            'request' => (string) (Environment::get('request') ?? ''),
            'action'  => (string) ($this->Template->action ?? ''),
            'query'   => (string) ($_SERVER['QUERY_STRING'] ?? ''),
            'params'  => array_intersect_key($_GET, array_flip(['plz','plzarea','for','cm_search','cm_max_dist','cm_max_dist_select','cm_country','cm_location','cmf_submitted']))
        ]);
        $this->logError('finder_pre_redirect', [
            'hasSearch' => $hasSearch,
            'action'    => (string) ($this->Template->action ?? ''),
            'query'     => (string) ($_SERVER['QUERY_STRING'] ?? ''),
            'cmf_target' => (int) ($this->cmf_target ?? 0),
            'cm_memberlist_pg' => (int) ($this->cm_memberlist_pg ?? 0),
            'jumpTo' => (int) ($this->jumpTo ?? 0),
        ]);
        $this->logError('finder_pre_redirect', [
            'hasSearch' => $hasSearch,
            'action'    => (string) ($this->Template->action ?? ''),
            'query'     => (string) ($_SERVER['QUERY_STRING'] ?? ''),
            'cmf_target' => (int) ($this->cmf_target ?? 0),
            'cm_memberlist_pg' => (int) ($this->cm_memberlist_pg ?? 0),
            'jumpTo' => (int) ($this->jumpTo ?? 0),
        ]);
        if ($hasSearch) {
            // Always navigate to the configured action (Weiterleitungsseite),
            // appending the current query string and enforcing default country when configured.
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            $params = [];
            if ($qs !== '') { parse_str($qs, $params); }
            if (!isset($params['cm_country']) && $defaultCountry !== '') {
                $params['cm_country'] = $defaultCountry;
                $qs = http_build_query($params);
            }
            $target = $this->Template->action . ($qs ? ('?' . $qs) : '');
            $this->dbg('redirect_force', [ 'target' => $target ]);
            $this->logError('redirect_force', [ 'target' => $target ]);
            \Contao\Controller::redirect($target);
        }

        // Hard-ensure action targets the configured Listenseite (last step)
        try {
            if (!empty($this->cm_memberlist_pg)) {
                if ($page = PageModel::findByPk((int) $this->cm_memberlist_pg)) {
                    $url = $page->getFrontendUrl();
                    $this->Template->action = $this->buildAbsoluteAction($url);
                } else {
                }
            } elseif (!empty($this->cmf_target)) {
                if ($page = PageModel::findByPk((int) $this->cmf_target)) {
                    $url = $page->getFrontendUrl();
                    $this->Template->action = $this->buildAbsoluteAction($url);
                } else {
                }
            } elseif (!empty($this->jumpTo)) {
                if ($page = PageModel::findByPk((int) $this->jumpTo)) {
                    $url = $page->getFrontendUrl();
                    $this->Template->action = $this->buildAbsoluteAction($url);
                } else {
                }
            }
        } catch (\Throwable $e) {}

        // Defaults for finder UI (match original: empty hiddens)
        $this->Template->order_by = '';
        $this->Template->sort = '';
        $this->Template->per_page = '';

        // Legacy config mapping (with DB fallbacks in case properties are not hydrated)
        $showAddress = (bool) $this->cm_memberlist_addressform;
        // Country input handling: select vs text field (already prepared above)
        $showCountry = (bool) ($countryCode || $selectCountry);
        $distForm = (bool) $this->cm_memberlist_distanceform;
        $distAsDrop = (bool) $this->cm_memberlist_distanceasdropdown;
        try {
            if (!$showAddress || !$distForm || !$distAsDrop || !$showCountry) {
                $db = \Contao\Database::getInstance();
                $res = $db->prepare('SELECT cm_memberlist_addressform, cm_memberlist_distanceform, cm_memberlist_distanceasdropdown, cm_map_country, cm_map_country_as_select FROM tl_module WHERE id=?')
                    ->limit(1)->execute((int) ($this->id ?? 0));
                if ($res->numRows) {
                    if (!$showAddress)      { $showAddress = (bool) $res->cm_memberlist_addressform; }
                    if (!$distForm)         { $distForm = (bool) $res->cm_memberlist_distanceform; }
                    if (!$distAsDrop)       { $distAsDrop = (bool) $res->cm_memberlist_distanceasdropdown; }
                    if (!$showCountry)      { $showCountry = (bool) ($res->cm_map_country || $res->cm_map_country_as_select); }
                    if ($countryCode === '' && (string)$res->cm_map_country !== '') { $countryCode = (string)$res->cm_map_country; $defaultCountry = strtolower(trim($countryCode)); }
                    if (!$selectCountry && (bool)$res->cm_map_country_as_select) { $selectCountry = true; }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }
        $showFulltext = (bool) ($this->cm_memberlist_multifieldseach || $this->cmf_show_fulltext);
        $privacyReq = (bool) ($this->cm_gc_acceptance_required || $this->cmf_privacy_required);

        // Labels from language (fallback to sensible defaults)
        try { Controller::loadLanguageFile('default'); } catch (\Throwable $e) {}
        $MSC = $GLOBALS['TL_LANG']['MSC'] ?? [];
        $this->Template->search_label = (string) ($MSC['search'] ?? 'Suchen');
        $this->Template->per_page_label = (string) ($MSC['list_perPage'] ?? 'Ergebnisse pro Seite');
        $this->Template->fields_label = (string) (($MSC['all_fields'][0] ?? null) ?: 'Feld');
        $this->Template->keywords_label = (string) ($MSC['keywords'] ?? 'Suchbegriffe');
        $this->Template->plz_search = (string) ($MSC['cm_plz_search'] ?? 'PLZ-Suche');
        $this->Template->plzarea_label = (string) ($MSC['plzarea'] ?? 'PLZ-Bereich');

        // PLZ area search (legacy)
        $this->Template->plzsearch = (bool) $this->cm_memberlist_plzsearch;
        $this->Template->plzarea = trim((string) (Input::get('plzarea') ?? ''));

        // Match legacy behavior: the whole radius section is controlled by addressform toggle
        // Show the whole radius section if either address fields or country select is enabled
        $this->Template->radiusform = ($showAddress || $selectCountry);
        $this->Template->show_address = $showAddress;
        $this->Template->show_country = $showCountry;
        $this->Template->radius_search = $this->cmf_heading ?: (string) ($MSC['cm_radius_search'] ?? 'Umkreissuche');
        // Wenn Adressfelder anzeigen aktiv ist, zeigen wir ein einziges Feld „PLZ / Ort“
        $this->Template->lbl_location = $showAddress ? 'PLZ / Ort' : ((string) ($MSC['cm_lbl_location'] ?? 'Adresse:'));
        $this->Template->lbl_country = (string) ($MSC['cm_lbl_country'] ?? 'Land:');
        $this->Template->lbl_max_dist = (string) ($MSC['cm_lbl_max_dist'] ?? 'Entfernung:');
        // Address field values and composed location
        $street = trim((string) (Input::get('cm_street') ?? ''));
        $postal = trim((string) (Input::get('cm_postal') ?? ''));
        $city   = trim((string) (Input::get('cm_city') ?? ''));
        $this->Template->cm_street = $street;
        $this->Template->cm_postal = $postal;
        $this->Template->cm_city   = $city;
        $composed = '';
        if ($showAddress) {
            $parts = array_filter([$street, $postal, $city], static function($v){ return $v !== ''; });
            if ($parts) {
                $composed = implode(' ', $parts);
            }
        }
        $this->Template->visitorlocation = $composed !== ''
            ? $composed
            : trim((string) (Input::get('cm_location') ?? ''));
        // Labels for address fields (de defaults)
        $this->Template->lbl_street = 'Straße';
        $this->Template->lbl_postal = 'PLZ';
        $this->Template->lbl_city   = 'Ort';

        // Remove service group filters in finder request form
        $this->Template->filter_fields = '';
        // Country select (match original name cm_country, default de)
        // Determine selected country: respect explicit input; if no preset configured, leave empty
        $selected = strtolower(trim((string) (Input::get('cm_country') ?? ($defaultCountry !== '' ? $defaultCountry : ''))));
        // Expose defaults/flags for templates (to optionally inject hidden inputs)
        $this->Template->cm_default_country = $defaultCountry;
        $this->Template->has_cm_country_param = ($selected !== '');
        if ($selectCountry) {
            $options = [];
            try {
                $countries = \Contao\System::getContainer()->get('contao.intl.countries')->getCountries();
                foreach ($countries as $code => $label) { $options[strtolower($code)] = $label; }
            } catch (\Throwable $e) { $options = ['de' => 'Deutschland', 'at' => 'Österreich', 'ch' => 'Schweiz']; }
            $html = '<select name="cm_country" class="cm_country">';
            if ($selected === '') { $html .= '<option value="" selected="selected"></option>'; }
            foreach ($options as $code => $label) {
                $sel = ($code === $selected) ? ' selected="selected"' : '';
                $html .= '<option value="'.$code.'"'.$sel.'>'.htmlspecialchars($label, ENT_QUOTES).'</option>';
            }
            $html .= '</select>';
            $this->Template->visitorcountry = $html;
        } else {
            $this->Template->visitorcountry = '<input class="cm_country" type="text" name="cm_country" value="'.htmlspecialchars($selected, ENT_QUOTES).'">';
        }

        $this->Template->distanceform = $distForm;
        $this->Template->distanceasdropdown = $distAsDrop;
        // Determine distance options: prefer legacy list with [default] marker
        $maxDist = (int) (Input::get('cm_max_dist') ?? 0);
        $optsHtml = '';
        $options = [];
        if ($distAsDrop && !empty($this->cm_memberlist_distancevalues)) {
            $raw = array_filter(array_map('trim', explode(',', (string) $this->cm_memberlist_distancevalues)), 'strlen');
            $def = null;
            foreach ($raw as $part) {
                if (preg_match('~^\[(\d+)\]$~', $part, $m)) {
                    $val = (int) $m[1];
                    $def = $val;
                    $options[] = $val;
                } else {
                    $options[] = (int) $part;
                }
            }
            if (!$maxDist) { $maxDist = $def ?: ($options[0] ?? 10); }
        }
        if (!$options) {
            $options = [10,25,50,100,200];
            if (!empty($this->cmf_dist_options)) {
                $opt = array_filter(array_map('trim', explode(',', (string) $this->cmf_dist_options)), 'strlen');
                $options = array_map('intval', $opt);
            }
            if (!$maxDist) { $maxDist = (int) ($this->cmf_default_dist ?: 10); }
        }
        foreach ($options as $opt) {
            $optsHtml .= '<option value="'.$opt.'"'.($opt===$maxDist?' selected':'').'>'.$opt.'</option>';
        }
        $this->Template->distvalues = $optsHtml;
        $this->Template->lbl_max_dist_drdn = (string) ($MSC['cm_lbl_max_dist_drdn'] ?? 'Umkreis auswählen:');
        $this->Template->cm_distitem = (string) ($MSC['cm_distitem'] ?? 'km');
        $this->Template->max_dist = $maxDist;

        $this->Template->cm_gc_acceptance_required = $privacyReq;
        $this->Template->cm_gc_privacy_label = (string) ($this->cm_gc_acceptance_label ?? '');

        // Fulltext search
        $this->Template->fieldsearch = (bool) $this->cm_memberlist_fieldsearch;
        $this->Template->multifieldsearch = $showFulltext;
        $this->Template->for = trim((string) (Input::get('for') ?? ''));
        $this->Template->distsearch_label = $this->cmf_submit_label ?: (string) ($MSC['cm_distsearch_label'] ?? 'Suchen');

        // Build dropdown for single-field search (legacy selector)
        try {
            Controller::loadDataContainer('tl_member');
            Controller::loadLanguageFile('tl_member');
        } catch (\Throwable $e) {}
        // Use the unified "Durchsuchte Felder" list from the module
        $searchFields = StringUtil::deserialize($this->ml_search_fields ?? '', true);
        // Fallbacks for legacy configs
        if (!$searchFields) {
            $rows = StringUtil::deserialize($this->cm_membergooglemaps_fieldslist ?? '', true);
            if ($rows && \is_array($rows)) {
                foreach ($rows as $r) { if (!empty($r['field'])) { $searchFields[] = (string)$r['field']; } }
            }
        }
        if (!$searchFields) {
            $searchFields = ['company','firstname','lastname','street','postal','city'];
        }
        $opts = '';
        $current = (string) (Input::get('search') ?? '');
        foreach ($searchFields as $field) {
            if ($field === 'cm_membergooglemaps_coords' || $field === 'cm_membergooglemaps_allowmap') { continue; }
            $label = $GLOBALS['TL_DCA']['tl_member']['fields'][$field]['label'][0] ?? ucfirst($field);
            $sel = ($field === $current) ? ' selected="selected"' : '';
            $opts .= '<option value="'.$field.'"'.$sel.'>'.htmlspecialchars($label, ENT_QUOTES).'</option>' . "\n";
        }
        $this->Template->search_fields = $opts;
    }

    private function buildAbsoluteAction(string $relative): string
    {
        $base = rtrim(Environment::get('base'), '/');
        $reqPath = parse_url(Environment::get('request'), PHP_URL_PATH) ?: '';
        $path = '/' . ltrim($relative, '/');
        if (str_starts_with($reqPath, '/preview.php/')) {
            $absolute = $base . '/preview.php' . $path;
            $this->logError('buildAbsoluteAction_preview', [ 'absolute' => $absolute ]);
            return $absolute;
        }
        $absolute = $base . $path;
        $this->logError('buildAbsoluteAction', [ 'absolute' => $absolute ]);
        return $absolute;
    }

    private function dbg(string $step, array $context = []): void
    {
        // Only log when cm_debug is present to avoid noise
        if (Input::get('cm_debug') === null) { return; }
        try {
            $dir = '';
            try {
                $base = \Contao\System::getContainer()->getParameter('kernel.project_dir');
                // Prefer Symfony 5/6 default var/log
                if (is_dir($base.'/var/log')) { $dir = $base.'/var/log'; }
                else { $dir = $base.'/var/logs'; }
            } catch (\Throwable $e) {
                // Fallback one level up from vendor bundle
                $base = dirname(__DIR__, 6);
                if (is_dir($base.'/var/log')) { $dir = $base.'/var/log'; }
                else { $dir = $base.'/var/logs'; }
            }
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $file = $dir.'/cm_member_finder.log';
            $entry = [
                'ts' => date('c'),
                'module' => (int) ($this->id ?? 0),
                'step' => $step,
                'uri' => (string) (Environment::get('request') ?? ''),
                'data' => $context,
            ];
            @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
            @error_log('cm_finder '+$step+' '+json_encode($entry, JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) { /* ignore */ }
    }

    private function logError(string $step, array $context = []): void
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
            $file = $dir.'/error.log';
            $entry = [
                'ts' => date('c'),
                'module' => (int) ($this->id ?? 0),
                'step' => $step,
                'uri' => (string) (Environment::get('request') ?? ''),
                'data' => $context,
            ];
            @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
            @error_log('cm_finder '+$step+' '+json_encode($entry, JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) { /* ignore */ }
    }
}
