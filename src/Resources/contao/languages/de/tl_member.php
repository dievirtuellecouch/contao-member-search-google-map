<?php

$GLOBALS['TL_LANG']['tl_member']['services_legend'] = 'Leistungen';
$GLOBALS['TL_LANG']['tl_member']['services_general'] = ['Leistungen Allgemein', ''];
$GLOBALS['TL_LANG']['tl_member']['services_supplier'] = ['Fördermitglied/Lieferant', ''];
$GLOBALS['TL_LANG']['tl_member']['services_expert'] = ['Sachverständiger', ''];

// Referenzen für Optionen (Schlüssel werden in DCA verwendet)
$GLOBALS['TL_LANG']['tl_member']['services_general_ref'] = [
    'per_reinigung' => 'PER-Reinigung',
    'kwl_reinigung' => 'KWL-Reinigung',
    'nassreinigung' => 'Nassreinigung',
    'co2_reinigung' => 'CO2-Reinigung',
    'alternative_reinigungsmittel' => 'Alternative Reinigungsmittel',
    'leder_pelz' => 'Leder-und Pelzreinigung',
    'braut_abendkleider' => 'Braut- und Abendkleiderreinigung',
    'kunststopfen' => 'Kunststopfen',
    'waescherei' => 'Wäscherei',
    'gardinen' => 'Gardinenwäscherei',
    'gardinenspanndienst' => 'Gardinenspanndienst',
    'hemden_kittel' => 'Hemden- und Kittelwäscherei',
    'heissmangel' => 'Heißmangel',
    'stoffwindelservice' => 'Stoffwindelservice',
    'abhol_lieferservice' => 'Wäsche-Abhol- und Lieferservice',
    'teppich' => 'Teppichreinigung',
    'polstermoebel' => 'Polstermöbelreinigung',
    'matratzen' => 'Matratzenreinigung',
    'betten' => 'Bettenreinigung',
    'lamellen' => 'Lamellenreinigung',
    'faerberei' => 'Färberei',
    'mietwaesche' => 'Mietwäscheservice',
    'mietberufskleidung' => 'Mietberufskleidungsservice',
    'krankenhauswaescherei' => 'Krankenhauswäscherei',
    'vollversorgung_krankenhaeuser' => 'Vollversorgung Krankenhäuser',
    'handtuchdienst' => 'Handtuchdienst und Waschraumhygiene',
    'schmutzmatten' => 'Schmutzmattenservice',
    'miet_putztuecher' => 'Miet-Putztücher',
    'vollversorgung_pflege' => 'Vollversorgung Alten- und Pflegeheime',
    'ausbildungsbetrieb' => 'Ausbildungsbetrieb',
    'dekontamination_reinraum' => 'Dekontamination von Reinraumkleidung',
    'hygienemanagement_zertifiziert' => 'Zertifiziertes Hygienemanagementsystem (RABC, RAL oder vergleichbar)',
];

$GLOBALS['TL_LANG']['tl_member']['services_supplier_ref'] = [
    'reinigungsmaschinen' => 'Reinigungsmaschinen',
    'waeschereimaschinen' => 'Wäschereimaschinen',
    'entsorgung' => 'Entsorgung',
    'dampferzeuger' => 'Dampferzeuger und Zubehör',
    'transporttechnik' => 'Transporttechnik',
    'waeschewagen' => 'Wäschewagen etc',
    'textilien' => 'Textilien',
    'konfektion' => 'Konfektion',
    'embleme_stickerei' => 'Embleme-Stickerei',
    'waschmittel' => 'Waschmittel-Reinigungsmittel-Hilfsmittel',
    'kennzeichnungstechnik' => 'Kennzeichnungstechnik',
    'schmutzfangmatten' => 'Schmutzfangmatten-Sauberlaufsysteme',
    'versicherungen' => 'Versicherungen',
    'beratungen' => 'Beratungen',
    'software_hardware_edv' => 'Software-Hardware-EDV-Beratungen',
    'institute_verbaende' => 'Institute-Vereinigungen-Verbände',
];

$GLOBALS['TL_LANG']['tl_member']['services_expert_ref'] = [
    'reinigung' => 'Reinigung',
    'waescherei' => 'Wäscherei',
    'textilien' => 'Textilien',
    'leder' => 'Leder',
    'pelz' => 'Pelz',
    'teppiche' => 'Teppiche',
    'heimtextilien' => 'Heimtextilien',
    'waeschereitechnik' => 'Wäschereitechnik',
    'reinigungstechnik' => 'Reinigungstechnik',
];

// Zusätzliche Felder (Sachverständiger, vereidigt …)
$GLOBALS['TL_LANG']['tl_member']['vereidigtreinigung'] = ['Sachverständiger (vereidigt durch)', ''];
$GLOBALS['TL_LANG']['tl_member']['vereidigtreinigungvon'] = ['Sachverständiger (vereidigt von)', ''];
$GLOBALS['TL_LANG']['tl_member']['avatar'] = ['Avatar', 'Hier können Sie einen Avatar oder ein Logo hochladen.'];
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_attempts'] = ['Anzahl der Versuche zur Koordinatenermittlung', 'Anzahl der Google-Anfragen zur Koordinatenermittlung - wird beim Speichern des Mitglieds zurückgesetzt.'];
$GLOBALS['TL_LANG']['tl_member']['avatar_legend'] = 'Avatar';
$GLOBALS['TL_LANG']['tl_member']['alias_legend'] = 'Alias';
$GLOBALS['TL_LANG']['tl_member']['alias'] = [
    'Mitglied-Alias',
    'Der Mitglied-Alias ist eine eindeutige Referenz, die anstelle der numerischen Mitglied-ID aufgerufen werden kann. Wenn Sie keinen Eintrag vornehmen, wird der Alias automatisch gebildet als vorname-nachname-ort. Sofern kein Ort angegeben ist, entfällt der entsprechende Teil.'
];
$GLOBALS['TL_LANG']['tl_member']['alias'][0] = 'Alias';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_allowmap'][0] = 'Auf Karte anzeigen';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_autocoords'][0] = 'Koordinaten automatisch ermitteln';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_coords'][0] = 'Koordinaten (lat,lng)';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_lat'][0] = 'Breite';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_lng'][0] = 'Länge';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_indivcenter'][0] = 'Individuelles Karten-Zentrum';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_center'][0] = 'Karten-Zentrum (lat,lng)';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_indivzoom'][0] = 'Individueller Zoom';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_zoom'][0] = 'Zoom';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_legend'] = 'Kartendarstellung';


$GLOBALS['TL_LANG']['tl_member']['allowEmail']   = ['E-Mails erlauben', 'Hier können Sie auswählen, wer dem Benutzer E-Mails senden darf.'];
$GLOBALS['TL_LANG']['tl_member']['publicFields'] = ['Öffentliche Felder', 'Hier können Sie die öffentlich sichtbaren Felder festlegen.'];
$GLOBALS['TL_LANG']['tl_member']['email_all']      = 'von jedermann';
$GLOBALS['TL_LANG']['tl_member']['email_member']   = 'von anderen Mitgliedern';
$GLOBALS['TL_LANG']['tl_member']['email_nobody']   = 'E-Mails deaktivieren';

$GLOBALS['TL_LANG']['tl_member']['LeistungenAllgemein'][0] = 'Leistungen Allgemein';
$GLOBALS['TL_LANG']['tl_member']['LeistungenAllgemein'][1] = 'Bitte wählen Sie die angebotenen Leistungen.';

$GLOBALS['TL_LANG']['tl_member']['Lieferant'][0] = 'Fördermitglied/Lieferant';
$GLOBALS['TL_LANG']['tl_member']['Lieferant'][1] = 'Fördermitglied/Lieferant auswählen.';

$GLOBALS['TL_LANG']['tl_member']['Sachverstaendiger'][0] = 'Sachverständiger';
$GLOBALS['TL_LANG']['tl_member']['Sachverstaendiger'][1] = 'Sachverständiger auswählen.';

$GLOBALS['TL_LANG']['tl_member']['vereidigtreinigung'][0] = 'Vereidigt durch';
$GLOBALS['TL_LANG']['tl_member']['vereidigtreinigung'][1] = 'Sachverständiger vereidigt durch.';

$GLOBALS['TL_LANG']['tl_member']['vereidigtreinigungvon'][0] = 'Von';
$GLOBALS['TL_LANG']['tl_member']['vereidigtreinigungvon'][1] = 'Sachverständiger vereidigt von.';

$GLOBALS['TL_LANG']['tl_member']['avatar'][0] = 'Avatar';
$GLOBALS['TL_LANG']['tl_member']['avatar'][1] = 'Benutzerbild (Datei auswählen).';

$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_coords'][0] = 'Koordinaten der Markierung';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_coords'][1] = 'Geokoordinaten als "lat,lng".';

$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_lat'][0] = 'Breitengrad (Grad, dezimal)';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_lat'][1] = 'Breitengrad in Dezimalgrad.';

$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_lng'][0] = 'Längengrad (Grad, dezimal)';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_lng'][1] = 'Längengrad in Dezimalgrad.';

$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_center'][0] = 'Kartenzentrum';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_center'][1] = 'Zentrum als "lat,lng".';

$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_zoom'][0] = 'Kartenvergrößerung';
$GLOBALS['TL_LANG']['tl_member']['cm_membergooglemaps_zoom'][1] = 'Zoomstufe (0–21).';

// Global Operation: GEO-Koordinaten neu generieren
$GLOBALS['TL_LANG']['tl_member']['updCoords'] = ['GEO-Koordinaten neu generieren', 'GEO-Koordinaten anhand der Adresse neu berechnen.'];
