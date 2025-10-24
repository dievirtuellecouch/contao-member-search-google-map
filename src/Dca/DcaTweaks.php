<?php

namespace Cm\MemberGoogleMapsBundle\Dca;

class DcaTweaks
{
    public function onLoadDca(string $table): void
    {
        if ($table !== 'tl_module') {
            return;
        }
        // Ensure only one search field list exists in modules (remove legacy single-field list)
        if (!isset($GLOBALS['TL_DCA']['tl_module'])) {
            return;
        }
        // Change the legacy field label so we can verify we are affecting the right list
        if (isset($GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_seachfieldslist'])) {
            // Update both the language label and DCA label reference
            $GLOBALS['TL_LANG']['tl_module']['cm_memberlist_seachfieldslist'][0] = 'test';
            $GLOBALS['TL_LANG']['tl_module']['cm_memberlist_seachfieldslist'][1] = '';
            $GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_seachfieldslist']['label'] = &$GLOBALS['TL_LANG']['tl_module']['cm_memberlist_seachfieldslist'];
        }
        // Remove from all palettes if present
        foreach ((array) ($GLOBALS['TL_DCA']['tl_module']['palettes'] ?? []) as $palKey => $palStr) {
            if (!is_string($palStr)) { continue; }
            $palStr = str_replace(',cm_memberlist_seachfieldslist', '', $palStr);
            $palStr = str_replace('cm_memberlist_seachfieldslist,', '', $palStr);
            $palStr = str_replace('cm_memberlist_seachfieldslist', '', $palStr);
            $GLOBALS['TL_DCA']['tl_module']['palettes'][$palKey] = $palStr;
        }
        // Ensure subpalettes do not reference the legacy field
        foreach ((array) ($GLOBALS['TL_DCA']['tl_module']['subpalettes'] ?? []) as $subKey => $subStr) {
            if (!is_string($subStr) || $subStr === '') { continue; }
            $new = str_replace(',cm_memberlist_seachfieldslist', '', $subStr);
            $new = str_replace('cm_memberlist_seachfieldslist,', '', $new);
            $new = str_replace('cm_memberlist_seachfieldslist', '', $new);
            $GLOBALS['TL_DCA']['tl_module']['subpalettes'][$subKey] = $new;
        }
        // Explicitly blank the subpalettes for these toggles
        $GLOBALS['TL_DCA']['tl_module']['subpalettes']['cm_memberlist_fieldsearch'] = '';
        $GLOBALS['TL_DCA']['tl_module']['subpalettes']['cm_memberlist_multifieldseach'] = '';
        // Remove selectors so Contao won’t attempt rendering subpalettes for them
        if (!empty($GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'])) {
            $GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'] = array_values(array_diff(
                (array) $GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'],
                ['cm_memberlist_multifieldseach','cm_memberlist_fieldsearch']
            ));
        }
    }
}
