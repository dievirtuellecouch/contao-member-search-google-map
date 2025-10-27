<?php

// Selectors and subpalettes (mirror legacy behaviour)
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cm_memberlist_plzsearch';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'] = array_values(array_diff(
    (array) ($GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'] ?? []),
    ['cm_memberlist_multifieldseach']
));
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cm_memberlist_fieldsearch';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cm_memberlist_distanceasdropdown';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cm_map_onlist';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cm_membergooglemaps_tableless';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cm_map_showmaponempty';

$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cm_memberlist_plzsearch'] = 'cm_memberlist_plznumberdigits';
// Show only one list for search fields (ml_search_fields) in the main palette
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cm_memberlist_multifieldseach'] = '';
// Do not show a separate list for single-field search
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cm_memberlist_fieldsearch'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cm_memberlist_distanceasdropdown'] = 'cm_memberlist_distancevalues';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cm_map_onlist'] = 'cm_map_poslist,cm_map_heightlist';
// Do not show an extra table-template select; keep tableless purely as a layout toggle
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cm_membergooglemaps_tableless'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cm_map_showmaponempty'] = 'cm_map_centerempty,cm_map_zoomempty';

// Palette for the member finder module configuration (mirror 3.5)
$GLOBALS['TL_DCA']['tl_module']['palettes']['cm_memberfinder'] = '{title_legend},name,headline,type;{config_legend},cmf_target,cm_membergooglemaps_fieldslist;{cm_search_legend},cm_usetags,cm_memberlist_fieldsearch,cm_memberlist_multifieldseach,ml_search_fields,cm_memberlist_addressform;{cm_memberlist_distancesearch},cm_memberlist_plzsearch,cm_map_country,cm_map_country_as_select,cm_gc_acceptance_required,cm_memberlist_distanceform,cm_memberlist_distanceasdropdown;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

// Palette for list view with Google Maps (based on 3.5)
$GLOBALS['TL_DCA']['tl_module']['palettes']['cm_membergooglemapsList'] =
    '{title_legend},name,headline,type;'
   .'{config_legend},cmf_target,ml_groups,perPage;'
   .'{cm_membergooglemaps_layout},cm_membergooglemaps_tableless,cm_membergooglemaps_fieldslist,cm_memberlist_showall_on_empty,cm_memberlist_hidedetaillink,cm_membergooglemaps_linktowebsite,cm_membergooglemaps_linkroute;'
   .'{cm_search_legend},cm_usetags,cm_memberlist_fieldsearch,cm_memberlist_multifieldseach,ml_search_fields,cm_memberlist_addressform;'
   .'{cm_memberlist_distancesearch},cm_memberlist_plzsearch,cm_map_country,cm_map_country_as_select,cm_gc_acceptance_required,cm_memberlist_distanceform,cm_memberlist_distanceasdropdown;'
   .'{templatelst_legend:hide},map_maintemplate,map_lsttemplate,map_infotemplate;'
   .'{cm_membergooglemaps_showmaplist},cm_map_onlist;'
   .'{cm_map_empty_legend},cm_memberlist_notfound,cm_map_showmaponempty;'
   .'{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

// Target page for results
$GLOBALS['TL_DCA']['tl_module']['fields']['cmf_target'] = [
    'label' => ['Weiterleitungsseite', 'Seite, auf die nach der Suche weitergeleitet wird und auf der die Ergebnisse angezeigt werden.'],
    'exclude' => true,
    'inputType' => 'pageTree',
    'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql'  => "int(10) unsigned NOT NULL default '0'",
];

// Listenseite (Original 3.5: Seite mit der Mitgliederliste)
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_pg'] = [
    'label' => ['Listenseite', 'Wählen Sie die zugehörige Seite mit der Mitgliederliste.'],
    'exclude' => true,
    'inputType' => 'pageTree',
    'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql'  => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cmf_heading'] = [
    'label' => ['Überschrift (Umkreissuche)', 'Überschrift des Suchbereichs.'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql'  => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cmf_show_fulltext'] = [
    'label' => ['Volltextsuche anzeigen', 'Volltextsuche (Firma / Name / Leistungen) anzeigen.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cmf_submit_label'] = [
    'label' => ['Beschriftung Suchen-Button', 'Text des Suchen-Buttons.'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 64, 'tl_class' => 'w50'],
    'sql'  => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cmf_dist_options'] = [
    'label' => ['Umkreis-Optionen', 'Kommagetrennte Werte in km, z. B. 10,25,50,100,200'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
    'sql'  => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cmf_default_dist'] = [
    'label' => ['Standard-Umkreis (km)', 'Vorauswahl in km, z. B. 10'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'  => "int(10) NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cmf_show_countries'] = [
    'label' => ['Länder-Auswahl anzeigen', 'Länderauswahl (Dropdown) anzeigen.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cmf_default_country'] = [
    'label' => ['Standard-Land', 'Zweibuchstabiger ISO-Code, z. B. de, at, ch'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 2, 'tl_class' => 'w50'],
    'sql'  => "varchar(2) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cmf_privacy_required'] = [
    'label' => ['Datenschutzhinweis erforderlich', 'Benutzer muss Datenschutz bestätigen.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'clr w50 m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

// Felder der Liste/Tabelle
$GLOBALS['TL_DCA']['tl_module']['fields']['ml_fields'] = [
    'label' => ['Felder', 'Diese Felder werden (vorbehaltlich individueller Einstellungen) veröffentlicht.'],
    'exclude' => true,
    'inputType' => 'checkboxWizard',
    'options' => [
        'company' => 'Firma',
        'street' => 'Straße',
        'postal' => 'Postleitzahl',
        'city' => 'Ort',
        'firstname' => 'Vorname',
        'lastname' => 'Nachname',
        'phone' => 'Telefonnummer',
        'fax' => 'Faxnummer',
        'email' => 'E-Mail-Adresse',
        'website' => 'Website (bitte mit „http://“ beginnen)',
        'LeistungenAllgemein' => 'Leistungen Allgemein',
        'Lieferant' => 'Fördermitglied/Lieferant',
        'dateOfBirth' => 'Geburtsdatum',
        'gender' => 'Geschlecht',
        'Sachverstaendiger' => 'Sachverständiger',
        'vereidigtreinigung' => 'Vereidigt durch',
        'vereidigtreinigungvon' => 'Von',
        'state' => 'Staat',
        'country' => 'Land',
        'mobile' => 'Handynummer',
        'language' => 'Sprache',
        'username' => 'Benutzername',
        'avatar' => 'Avatar',
        'cm_membergooglemaps_coords' => 'Koordinaten der Markierung',
        'cm_membergooglemaps_lat' => 'Breitengrad (Grad, dezimal)',
        'cm_membergooglemaps_lng' => 'Längengrad (Grad, dezimal)',
        'cm_membergooglemaps_center' => 'Kartenzentrum',
        'cm_membergooglemaps_zoom' => 'Kartenvergrößerung',
    ],
    'eval' => ['multiple' => true, 'mandatory'=>true],
    'sql'  => 'blob NULL',
];

// List module specific fields
$GLOBALS['TL_DCA']['tl_module']['fields']['ml_groups'] = [
    'label' => ['Gruppen', 'Diese Gruppen werden in der Mitgliederliste veröffentlicht.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'foreignKey' => 'tl_member_group.name',
    'eval' => ['multiple'=>true],
    'sql'  => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['perPage'] = $GLOBALS['TL_DCA']['tl_module']['fields']['perPage'] ?? [
    'label' => ['Elemente pro Seite', ''],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp'=>'digit','tl_class'=>'w50'],
    'sql'  => "smallint(5) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_membergooglemaps_tableless'] = [
    'label' => ['Tabellenlose Ausgabe', 'Tabellenlose Listenansicht verwenden.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class'=>'clr m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

// New: Show all results when no filter is set
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_showall_on_empty'] = [
    'label' => ['Alle Ergebnisse anzeigen', 'Wenn kein Filter gesetzt ist, alle Ergebnisse anzeigen.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    // Place on a new row (above "Detaillink ausblenden") and half width
    'eval' => ['tl_class'=>'clr w50 m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_hidedetaillink'] = [
    'label' => ['Detaillink ausblenden', 'Keinen Link zur Detailansicht anzeigen.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class'=>'w50 m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_membergooglemaps_linktowebsite'] = [
    'label' => ['Website verlinken', 'Firmenwebsite in der Liste verlinken.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class'=>'w50 m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

// New: append a column with a route planning link (Google)
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_membergooglemaps_linkroute'] = [
    'label' => ['Spalte mit Link zur Routenplanung anfügen', 'Aktivieren Sie das Kontrollfeld, wenn zur Google Routenplanung verlinkt werden soll.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class'=>'w50 m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['map_lsttemplate'] = [
    'label' => ['Listen-Template', 'Template der Listenansicht (3.5-kompatibel)'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => static function(){
        // Exactly the two list variants from the legacy module
        return ['mod_cm_memberlist_googlemaps_table','mod_cm_memberlist_googlemaps_tabless'];
    },
    'eval' => ['includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
    'sql'  => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['map_tbltemplate'] = [
    'label' => ['Tabellen-Template', 'Template für Tabellenansicht'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => static function(){
        $legacy = \Contao\Controller::getTemplateGroup('mod_cm_memberlist_googlemaps_');
        $current = ['mod_cm_memberlist_simple'];
        return array_values(array_unique(array_merge($legacy, $current)));
    },
    'eval' => ['tl_class'=>'w50'],
    'sql'  => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['map_infotemplate'] = [
    'label' => ['Info-Template', 'Infobox-Template für Karten-Pins (3.5-kompatibel)'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => static function(){
        // Exactly the info templates shipped by the legacy module
        return ['info_cm_membergooglemaps','info_cm_membergooglemaps_list'];
    },
    'eval' => ['includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
    'sql'  => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_map_onlist'] = [
    'label' => ['Karte in der Listenansicht', 'In der Listenansicht eine Karte anzeigen.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange'=>true, 'tl_class'=>'clr m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_map_poslist'] = [
    'label' => ['Platzierung der Karte', 'Position der Karte relativ zur Liste'],
    'exclude' => true,
    'inputType' => 'radioTable',
    'options' => ['above','below'],
    'eval' => ['cols'=>2,'tl_class'=>'w50'],
    'sql'  => "varchar(16) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_map_heightlist'] = [
    'label' => ['Höhe (Karte)', 'Höhe der Karte in der Listenansicht'],
    'exclude' => true,
    'inputType' => 'inputUnit',
    'options' => ['px','%','em','pt','pc','in','cm','mm'],
    'eval' => ['includeBlankOption'=>true,'rgxp'=>'alnum','tl_class'=>'w50'],
    'sql'  => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_notfound'] = [
    'label' => ['Text bei leerem Ergebnis', 'Auszugebender Text, wenn keine Einträge gefunden wurden.'],
    'exclude' => true,
    'inputType' => 'textarea',
    'eval' => ['rte'=>'tinyMCE','helpwizard'=>false],
    'sql'  => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_map_showmaponempty'] = [
    'label' => ['Karte bei leerem Ergebnis', 'Karte anzeigen, auch wenn keine Einträge gefunden wurden.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange'=>true,'tl_class'=>'clr m12'],
    'sql'  => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_map_centerempty'] = [
    'label' => ['Kartenzentrum (leer)', 'Kartenzentrum bei leerem Ergebnis (lat,lng)'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength'=>255,'tl_class'=>'w50'],
    'sql'  => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_map_zoomempty'] = [
    'label' => ['Zoom (leer)', 'Zoomstufe bei leerem Ergebnis'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp'=>'digit','tl_class'=>'w50'],
    'sql'  => "int(10) unsigned NOT NULL default '0'",
];

// Durchsuchte Felder
$GLOBALS['TL_DCA']['tl_module']['fields']['ml_search_fields'] = [
    'label' => ['Durchsuchte Felder', 'Wählen Sie die Felder, die durchsucht werden sollen.'],
    'exclude' => true,
    'inputType' => 'checkboxWizard',
    // identisch zu den Feldern der Einzelfeldsuche (cm_memberlist_seachfieldslist)
    'options' => [
        'company' => 'Firma',
        'street' => 'Straße',
        'postal' => 'Postleitzahl',
        'city' => 'Ort',
        'firstname' => 'Vorname',
        'lastname' => 'Nachname',
        'phone' => 'Telefonnummer',
        'fax' => 'Faxnummer',
        'email' => 'E-Mail-Adresse',
        'website' => 'Website (bitte mit „http://“ beginnen)',
        'LeistungenAllgemein' => 'Leistungen Allgemein',
        'Lieferant' => 'Fördermitglied/Lieferant',
        'dateOfBirth' => 'Geburtsdatum',
        'gender' => 'Geschlecht',
        'Sachverstaendiger' => 'Sachverständiger',
        'vereidigtreinigung' => 'Vereidigt durch',
        'vereidigtreinigungvon' => 'Von',
        'state' => 'Staat',
        'country' => 'Land',
        'mobile' => 'Handynummer',
        'language' => 'Sprache',
        'username' => 'Benutzername',
        'avatar' => 'Avatar',
        'cm_membergooglemaps_coords' => 'Koordinaten der Markierung',
        'cm_membergooglemaps_lat' => 'Breitengrad (Grad, dezimal)',
        'cm_membergooglemaps_lng' => 'Längengrad (Grad, dezimal)',
        'cm_membergooglemaps_center' => 'Kartenzentrum',
        'cm_membergooglemaps_zoom' => 'Kartenvergrößerung',
    ],
    'eval' => ['multiple' => true, 'tl_class' => 'w50'],
    'sql'  => 'blob NULL',
];

// Legacy-compatible field names from 3.5 to simplify migration
$defCb = ['inputType' => 'checkbox', 'eval' => ['tl_class'=>'w50 m12'], 'sql' => "char(1) NOT NULL default ''", 'exclude'=>true];
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_usetags'] = [
    'label' => ['Tags verwenden','Tags für die Suche verwenden'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'clr m12'],
    'sql'  => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_fieldsearch'] = [
    'label' => ['Suche in einzelnen Feldern',''],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'clr m12'],
    'sql'  => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_multifieldseach'] = [
    'label' => ['Suche in mehreren Feldern','Der vom Besucher eingebene Suchbegriff wird in mehrerer Feldern gesucht.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'clr m12'],
    'sql'  => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_plzsearch'] = array_merge(['label'=>['PLZ-Suche anzeigen','PLZ-Areafeld anzeigen']], $defCb);
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_addressform'] = [
    'label' => ['Adressfelder anzeigen','Feld zur Standorteingabe einblenden'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'default' => true,
    'eval' => ['tl_class' => 'clr m12'],
    'sql'  => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_map_country'] = [
    'label' => ['Land (Voreinstellung)','Voreinstellung für das Ländereingabefeld/die Länderauswahl'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength'=>5, 'tl_class'=>'w50'],
    'sql'  => "varchar(5) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_map_country_as_select'] = [
    'label' => ['Länderfeld als Auswahl','Stellt das Länderfeld als Auswahl statt als Eingabefeld bereit.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class'=>'w50 m12 cbx'],
    'sql'  => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_gc_acceptance_required'] = [
    'label' => ['Bestätigung Datenschutz Geocoding','Bestätigung das die angegebene Adresse für das Geocoding an Google gesendet wird.'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class'=>'w50 m12 clr cbx'],
    'sql'  => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_distanceform'] = [
    'label' => ['Entfernungsfeld anzeigen','Feld zur Eingabe einer maximalen Entfernung eingeben'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class'=>'w50 clr cbx'],
    'sql'  => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_distanceasdropdown'] = [
    'label' => ['Entfernungsfeld als Dropdown','Maximale Entfernung in einem Dropdown zur Auswahl bereitstellen'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange'=>true, 'tl_class'=>'w50 clr cbx'],
    'sql'  => "char(1) NOT NULL default ''",
];

// Additional legacy fields used in templates/logic
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_plznumberdigits'] = [
    'label' => ['PLZ-Stellen','Anzahl der erforderlichen PLZ-Ziffern (1–5)'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'  => "int(10) unsigned NOT NULL default '0'",
];

// Removed legacy field "cm_memberlist_seachfieldslist" from UI to avoid duplicate lists

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_memberlist_distancevalues'] = [
    'label' => ['Werte des DropDown-Feldes','Werte mit Komma getrennt eingeben. ein Wert muss in [] eingeschlossen und kennzeichnet damit den standardmäßig voreingestellten Wert.'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql'  => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cm_gc_acceptance_label'] = [
    'label' => ['Text Datenschutzhinweis','Beschriftung des Zustimmungshinweises'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'clr'],
    'sql'  => "varchar(255) NOT NULL default ''",
];

// Field list for single-field search dropdown (legacy name)
$GLOBALS['TL_DCA']['tl_module']['fields']['cm_membergooglemaps_fieldslist'] = [
    'label' => ['Felder der Liste/Tabelle','Legen Sie fest, welche Felder in der Tabelle aufgeführt werden sollen und bestimmen Sie die Reihenfolge.'],
    'exclude' => true,
    'inputType' => 'cm_ListWizard',
    'options' => [
        'company' => 'Firma',
        'street' => 'Straße',
        'postal' => 'Postleitzahl',
        'city' => 'Ort',
        'firstname' => 'Vorname',
        'lastname' => 'Nachname',
        'phone' => 'Telefonnummer',
        'fax' => 'Faxnummer',
        'email' => 'E-Mail-Adresse',
        'website' => 'Website (bitte mit „http://“ beginnen)',
        'LeistungenAllgemein' => 'Leistungen Allgemein',
        'Lieferant' => 'Fördermitglied/Lieferant',
        'dateOfBirth' => 'Geburtsdatum',
        'gender' => 'Geschlecht',
        'Sachverstaendiger' => 'Sachverständiger',
        'vereidigtreinigung' => 'Vereidigt durch',
        'vereidigtreinigungvon' => 'Von',
        'state' => 'Staat',
        'country' => 'Land',
        'mobile' => 'Handynummer',
        'language' => 'Sprache',
        'username' => 'Benutzername',
        'avatar' => 'Avatar',
        'cm_membergooglemaps_coords' => 'Koordinaten der Markierung',
        'cm_membergooglemaps_lat' => 'Breitengrad (Grad, dezimal)',
        'cm_membergooglemaps_lng' => 'Längengrad (Grad, dezimal)',
        'cm_membergooglemaps_center' => 'Kartenzentrum',
        'cm_membergooglemaps_zoom' => 'Kartenvergrößerung',
    ],
    'sql'  => 'blob NULL',
];

// Haupt-Template Auswahl (legacy-kompatibel)
$GLOBALS['TL_DCA']['tl_module']['fields']['map_maintemplate'] = [
    'label' => ['Haupt-Template', 'Übergeordnetes Google-Maps-Template (3.5-kompatibel)'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => static function() {
        // Restrict to the legacy main template
        return ['mod_cm_memberlist_googlemaps'];
    },
    'eval' => ['includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
    'sql'  => "varchar(64) NOT NULL default ''",
];
