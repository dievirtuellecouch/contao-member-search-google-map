<?php

namespace Cm\MemberGoogleMapsBundle\Dca;

use Contao\Controller;

class TlModuleMemberlist
{
    /**
     * Return all viewable tl_member fields (feViewable)
     */
    public static function getViewableMemberProperties(): array
    {
        Controller::loadLanguageFile('tl_member');
        Controller::loadDataContainer('tl_member');
        $out = [];
        foreach ($GLOBALS['TL_DCA']['tl_member']['fields'] as $k => $cfg) {
            if (in_array($k, ['password','newsletter','publicFields','allowEmail'], true)) {
                continue;
            }
            if (!empty($cfg['eval']['feViewable'])) {
                $out[$k] = $GLOBALS['TL_DCA']['tl_member']['fields'][$k]['label'][0] ?? ucfirst($k);
            }
        }
        return $out;
    }
}

