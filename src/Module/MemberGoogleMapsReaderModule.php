<?php

namespace Cm\MemberGoogleMapsBundle\Module;

use Contao\BackendTemplate;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Module;
use Contao\PageModel;
use Contao\Database;

class MemberGoogleMapsReaderModule extends Module
{
    protected $strTemplate = 'mod_cm_memberlist_reader';

    public function generate()
    {
        if (TL_MODE === 'BE') {
            $tpl = new BackendTemplate('be_wildcard');
            $tpl->wildcard = '### CM MEMBER GOOGLE MAPS READER ###';
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
        // Support auto_item
        if (!Input::get('show') && \Contao\Config::get('useAutoItem') && Input::get('auto_item')) {
            Input::setGet('show', Input::get('auto_item'));
        }

        $show = Input::get('show');
        $where = '';
        $values = [];

        if (ctype_digit((string) $show)) {
            $where = 'id=?';
            $values[] = (int) $show;
        } else {
            $where = 'alias=?';
            $values[] = (string) $show;
        }

        $stmt = Database::getInstance()->prepare("SELECT id,firstname,lastname,company,street,postal,city,country,phone,fax,email,website,cm_membergooglemaps_coords,alias,LeistungenAllgemein,Lieferant,Sachverstaendiger,vereidigtreinigung,vereidigtreinigungvon FROM tl_member WHERE ".$where." AND disable!=1");
        $res = $stmt->execute(...$values);
        if (!$res->numRows) {
            $this->Template->invalid = true;
            return;
        }
        $row = $res->row();
        // Deserialize services
        $row['services_general'] = \Contao\StringUtil::deserialize($row['LeistungenAllgemein'] ?? null, true);
        $row['services_supplier'] = \Contao\StringUtil::deserialize($row['Lieferant'] ?? null, true);
        $row['services_expert'] = \Contao\StringUtil::deserialize($row['Sachverstaendiger'] ?? null, true);
        $this->Template->item = $row;
        $this->Template->servicesLabels = [
            'general' => $GLOBALS['TL_LANG']['tl_member']['services_general_ref'] ?? [],
            'supplier' => $GLOBALS['TL_LANG']['tl_member']['services_supplier_ref'] ?? [],
            'expert' => $GLOBALS['TL_LANG']['tl_member']['services_expert_ref'] ?? [],
        ];
        $this->Template->hasMap = !empty($row['cm_membergooglemaps_coords']);

        // Resolve Google Maps API key from container parameter first, then fallbacks
        $apiKey = '';
        try {
            $c = \Contao\System::getContainer();
            if ($c->hasParameter('google_maps_api_key')) {
                $apiKey = (string) $c->getParameter('google_maps_api_key');
            }
            if ($apiKey === '') {
                if (!empty($_SERVER['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_SERVER['GOOGLE_MAPS_API_KEY']; }
                elseif (!empty($_ENV['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_ENV['GOOGLE_MAPS_API_KEY']; }
                elseif (($tmp = getenv('GOOGLE_MAPS_API_KEY')) !== false && $tmp !== '') { $apiKey = (string) $tmp; }
                elseif ($c->hasParameter('env(GOOGLE_MAPS_API_KEY)')) { $apiKey = (string) $c->getParameter('env(GOOGLE_MAPS_API_KEY)'); }
            }
        } catch (\Throwable $e) {}
        $this->Template->googleApiKey = $apiKey;

        // Ensure unified CSS class on all module types of this bundle
        try {
            $cls = (string) ($this->Template->class ?? '');
            if (strpos($cls, 'mod_cm_memberfinder') === false) {
                $this->Template->class = trim($cls.' mod_cm_memberfinder');
            }
        } catch (\Throwable $e) { /* ignore */ }
    }
}
