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

        $stmt = Database::getInstance()->prepare("SELECT id,firstname,lastname,company,street,postal,city,phone,email,website,cm_membergooglemaps_coords,alias,services_general,services_supplier,services_expert FROM tl_member WHERE ".$where." AND disable!=1");
        $res = $stmt->execute(...$values);
        if (!$res->numRows) {
            $this->Template->invalid = true;
            return;
        }
        $row = $res->row();
        // Deserialize services
        $row['services_general'] = \Contao\StringUtil::deserialize($row['services_general'] ?? null, true);
        $row['services_supplier'] = \Contao\StringUtil::deserialize($row['services_supplier'] ?? null, true);
        $row['services_expert'] = \Contao\StringUtil::deserialize($row['services_expert'] ?? null, true);
        $this->Template->item = $row;
        $this->Template->servicesLabels = [
            'general' => $GLOBALS['TL_LANG']['tl_member']['services_general_ref'] ?? [],
            'supplier' => $GLOBALS['TL_LANG']['tl_member']['services_supplier_ref'] ?? [],
            'expert' => $GLOBALS['TL_LANG']['tl_member']['services_expert_ref'] ?? [],
        ];
        $this->Template->hasMap = !empty($row['cm_membergooglemaps_coords']);

        // Ensure unified CSS class on all module types of this bundle
        try {
            $cls = (string) ($this->Template->class ?? '');
            if (strpos($cls, 'mod_cm_memberfinder') === false) {
                $this->Template->class = trim($cls.' mod_cm_memberfinder');
            }
        } catch (\Throwable $e) { /* ignore */ }
    }
}
