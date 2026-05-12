<?php

// Add custom fields from legacy 3.5 (Leistungen/Lieferant/Sachverständiger, Vereidigung, Avatar, Koordinaten)

// Inject fields into default palette after gender (services + expert)
if (isset($GLOBALS['TL_DCA']['tl_member']['palettes']['default'])) {
    $pal = $GLOBALS['TL_DCA']['tl_member']['palettes']['default'];
    // Case 1: gender followed by comma (within same legend)
    $pal = str_replace(
        'gender,',
        'gender,LeistungenAllgemein,Lieferant,Sachverstaendiger,vereidigtreinigung,vereidigtreinigungvon,',
        $pal
    );
    // Case 2: gender ends the legend (followed by semicolon)
    $pal = str_replace(
        'gender;',
        'gender,LeistungenAllgemein,Lieferant,Sachverstaendiger,vereidigtreinigung,vereidigtreinigungvon;',
        $pal
    );
    $GLOBALS['TL_DCA']['tl_member']['palettes']['default'] = $pal;
}

// Global operation: Fehlende Koordinaten ergänzen
if (!isset($GLOBALS['TL_DCA']['tl_member']['list']['global_operations'])) {
    $GLOBALS['TL_DCA']['tl_member']['list']['global_operations'] = [];
}
$GLOBALS['TL_DCA']['tl_member']['list']['global_operations']['updCoords'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_member']['updCoords'],
    'href'  => 'key=updCoords',
    'class' => 'header_icon',
    'icon'  => 'sync.svg',
];

// Ensure avatar field is present and visible (mirror original 3.5 placement)
if (isset($GLOBALS['TL_DCA']['tl_member']['palettes']['default'])) {
    $pal = (string) $GLOBALS['TL_DCA']['tl_member']['palettes']['default'];
    if (strpos($pal, 'avatar') === false) {
        if (strpos($pal, ';{account_legend}') !== false) {
            // Preferred: insert avatar legend before account legend (as in 3.5)
            $pal = str_replace(';{account_legend}', ';{avatar_legend},avatar;{account_legend}', $pal);
        } elseif (str_contains($pal, 'website,language')) {
            // Alternative: place next to website/language
            $pal = str_replace('website,language', 'website,avatar,language', $pal);
        } elseif (str_contains($pal, 'website;')) {
            $pal = str_replace('website;', 'website,avatar;', $pal);
        } elseif (str_contains($pal, ';{alias_legend}')) {
            // Else: create own legend before alias
            $pal = str_replace(';{alias_legend}', ';{avatar_legend},avatar;{alias_legend}', $pal);
        } else {
            // Fallback: append at end in its own legend
            $pal .= ';{avatar_legend},avatar';
        }
    }
    // Insert alias and map legend similar to 3.5
    $pal = str_replace(
        ';{homedir_legend',
        ';{alias_legend},alias;{cm_membergooglemaps_legend},cm_membergooglemaps_allowmap,cm_membergooglemaps_autocoords,cm_membergooglemaps_attempts,cm_membergooglemaps_coords,cm_membergooglemaps_lat,cm_membergooglemaps_lng,cm_membergooglemaps_indivcenter,cm_membergooglemaps_indivzoom;{homedir_legend',
        $pal
    );
    $GLOBALS['TL_DCA']['tl_member']['palettes']['default'] = $pal;
}

$GLOBALS['TL_DCA']['tl_member']['fields']['LeistungenAllgemein'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['LeistungenAllgemein'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'checkbox',
    'options'   => [
        'PER-Reinigung','KWL-Reinigung','Nassreinigung','CO2-Reinigung','Alternative Reinigungsmittel',
        'Leder-und Pelzreinigung','Braut- und Abendkleiderreinigung','Kunststopfen','Wäscherei','Gardinenwäscherei',
        'Gardinenspanndienst','Hemden- und Kittelwäscherei','Heißmangel','Stoffwindelservice','Wäsche-Abhol- und Lieferservice',
        'Teppichreinigung','Polstermöbelreinigung','Matratzenreinigung','Bettenreinigung','Lamellenreinigung','Färberei',
        'Mietwäscheservice','Mietberufskleidungsservice','Krankenhauswäscherei','Vollversorgung Krankenhäuser',
        'Handtuchdienst und Waschraumhygiene','Schmutzmattenservice','Miet-Putztücher','Vollversorgung Alten- und Pflegeheime',
        'Ausbildungsbetrieb','Dekontamination von Reinraumkleidung','Zertifiziertes Hygienemanagementsystem (RABC, RAL oder vergleichbar)'
    ],
    'eval'      => ['multiple'=>true, 'tl_class'=>'clr', 'feViewable'=>true, 'feEditable'=>true],
    'sql'       => "text NULL",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['Lieferant'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['Lieferant'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'checkbox',
    'options'   => [
        'Reinigungsmaschinen','Wäschereimaschinen','Entsorgung','Dampferzeuger und Zubehör','Transporttechnik','Wäschewagen etc',
        'Textilien','Konfektion','Embleme-Stickerei','Waschmittel-Reinigungsmittel-Hilfsmittel','Kennzeichnungstechnik',
        'Schmutzfangmatten-Sauberlaufsysteme','Versicherungen','Beratungen','Software-Hardware-EDV-Beratungen','Institute-Vereinigungen-Verbände'
    ],
    'eval'      => ['multiple'=>true, 'tl_class'=>'clr', 'feViewable'=>true, 'feEditable'=>true],
    'sql'       => "text NULL",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['Sachverstaendiger'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['Sachverstaendiger'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'checkbox',
    'options'   => ['Reinigung','Wäscherei','Textilien','Leder','Pelz','Teppiche','Heimtextilien','Wäschereitechnik','Reinigungstechnik'],
    'eval'      => ['multiple'=>true, 'tl_class'=>'clr', 'feViewable'=>true, 'feEditable'=>true],
    'sql'       => "text NULL",
];

// Additional legacy fields
$GLOBALS['TL_DCA']['tl_member']['fields']['vereidigtreinigung'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['vereidigtreinigung'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['maxlength'=>255, 'tl_class'=>'w50', 'feViewable'=>true],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['vereidigtreinigungvon'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['vereidigtreinigungvon'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['maxlength'=>255, 'tl_class'=>'w50', 'feViewable'=>true],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['avatar'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['avatar'],
    'exclude'   => true,
    'inputType' => 'fileTree',
    'eval'      => [
        'files'         => true,
        'filesOnly'     => true,
        'fieldType'     => 'radio',
        'tl_class'      => 'clr w50',
        'feViewable'    => true,
        'feEditable'    => true,
        // FE upload handling
        'storeFile'     => true,
        'useHomeDir'    => true,               // bevorzugt Home-Verzeichnis des Mitglieds
        'uploadFolder'  => 'files/members',    // Fallback, falls kein HomeDir vorhanden
        'doNotOverwrite'=> true,
        'isImage'       => true,
        // Erlaubte Bildformate (ohne SVG)
        'extensions'    => 'jpg,jpeg,png,gif,webp,bmp,tif,tiff,heic,heif,ico',
    ],
    'sql'       => "binary(16) NULL",
];

// Coordinates and map preferences (basic subset)
$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_coords'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_coords'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength'=>255, 'tl_class'=>'w50 clr'],
    'wizard'    => [
        [\Cm\MemberGoogleMapsBundle\Dca\TlMemberCoordsWizard::class, 'coordsInlineMap'],
        [\Cm\MemberGoogleMapsBundle\Dca\TlMemberCoordsWizard::class, 'generateCoordsButton']
    ],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_lat'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_lat'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength'=>64, 'tl_class'=>'w50'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_lng'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_lng'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength'=>64, 'tl_class'=>'w50'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_center'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_center'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength'=>255, 'tl_class'=>'w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_zoom'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_zoom'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp'=>'digit', 'tl_class'=>'w50'],
    'sql'       => "int(10) unsigned NOT NULL default '0'",
];

// Additional map fields + selectors/subpalettes (legacy-compatible)
$GLOBALS['TL_DCA']['tl_member']['palettes']['__selector__'][] = 'cm_membergooglemaps_indivcenter';
$GLOBALS['TL_DCA']['tl_member']['palettes']['__selector__'][] = 'cm_membergooglemaps_indivzoom';
$GLOBALS['TL_DCA']['tl_member']['subpalettes']['cm_membergooglemaps_indivcenter'] = 'cm_membergooglemaps_center';
$GLOBALS['TL_DCA']['tl_member']['subpalettes']['cm_membergooglemaps_indivzoom'] = 'cm_membergooglemaps_zoom';

$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_allowmap'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_allowmap'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_autocoords'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_autocoords'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_attempts'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_attempts'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp'=>'natural','readonly'=>true,'tl_class'=>'w50'],
    'sql'       => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_indivcenter'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_indivcenter'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange'=>true,'tl_class'=>'w50 clr'],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cm_membergooglemaps_indivzoom'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_indivzoom'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange'=>true,'tl_class'=>'w50'],
    'sql'       => "char(1) NOT NULL default ''",
];

// Ensure the PersonalData module lists the required fields as editable
(function() {
    $fields = [
        'company','street','postal','city','firstname','lastname',
        'phone','fax','email','website',
        'LeistungenAllgemein','Lieferant','dateOfBirth','gender','Sachverstaendiger',
        'vereidigtreinigung','vereidigtreinigungvon','state','country','mobile','language',
        'avatar'
    ];
    foreach ($fields as $f) {
        if (isset($GLOBALS['TL_DCA']['tl_member']['fields'][$f])) {
            $eval = (array) ($GLOBALS['TL_DCA']['tl_member']['fields'][$f]['eval'] ?? []);
            $eval['feEditable'] = true;
            if (!isset($eval['feViewable'])) {
                $eval['feViewable'] = true;
            }
            $GLOBALS['TL_DCA']['tl_member']['fields'][$f]['eval'] = $eval;
        }
    }
})();

// Alias configuration (if not present already)
if (!isset($GLOBALS['TL_DCA']['tl_member']['fields']['alias'])) {
    $GLOBALS['TL_DCA']['tl_member']['fields']['alias'] = [
        'label'         => &$GLOBALS['TL_LANG']['tl_member']['alias'],
        'exclude'       => true,
        'search'        => true,
        'inputType'     => 'text',
        'eval'          => ['rgxp'=>'alias', 'unique'=>true, 'maxlength'=>128, 'tl_class'=>'w50'],
        'save_callback' => [[\Cm\MemberGoogleMapsBundle\Dca\TlMemberAlias::class, 'generateAlias']],
        'sql'           => "varchar(128) BINARY NOT NULL default ''",
    ];
}
