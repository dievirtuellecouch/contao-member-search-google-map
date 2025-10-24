<?php

namespace Cm\MemberGoogleMapsBundle\Service;

use Contao\Backend;
use Contao\Controller;
use Contao\Database;
use Contao\Environment;
use Contao\Input;
use Contao\Message;
use Contao\System;

class CoordsUpdater extends Backend
{
    public function handle(): void
    {
        // Only react to our key
        if (Input::get('key') !== 'updCoords') {
            return;
        }

        $db = Database::getInstance();
        $limit = max(1, (int) (Input::get('limit') ?: 250));

        // DBAL does not support binding LIMIT reliably across drivers – inject validated int
        $sql = "SELECT id,street,postal,city,country,cm_membergooglemaps_coords FROM tl_member "
             . "WHERE (cm_membergooglemaps_coords='' OR cm_membergooglemaps_coords IS NULL) "
             . "AND (street<>'' OR city<>'' OR postal<>'') ORDER BY id ASC LIMIT ".(int)$limit;
        $members = $db->execute($sql);

        if (!$members->numRows) {
            Message::addInfo('Es gibt keine Mitglieder mit fehlenden Koordinaten.');
            Controller::redirect('contao?do=member');
        }

        $ok = 0; $skip = 0; $err = 0;
        while ($members->next()) {
            $row = $members->row();
            $addr = trim(($row['street'] ? $row['street'].' ' : '').($row['postal'] ? $row['postal'].' ' : '').($row['city'] ?: ''));
            $country = strtoupper(trim((string) ($row['country'] ?: 'DE')));
            if ($addr === '') { $skip++; continue; }
            [$lat, $lng] = $this->geocode($addr, $country);
            if ($lat === null || $lng === null) { $err++; continue; }
            $coords = $lat.','.$lng;
            try {
                $db->prepare("UPDATE tl_member SET cm_membergooglemaps_coords=?, cm_membergooglemaps_lat=?, cm_membergooglemaps_lng=?, cm_membergooglemaps_attempts=cm_membergooglemaps_attempts+1 WHERE id=?")
                    ->execute($coords, (string)$lat, (string)$lng, (int)$row['id']);
                $ok++;
            } catch (\Throwable $e) {
                $err++;
            }
        }

        Message::addConfirmation(sprintf('Koordinaten aktualisiert: %d, übersprungen: %d, Fehler: %d', $ok, $skip, $err));
        Controller::redirect('contao?do=member');
    }

    private function geocode(string $address, string $country = 'DE'): array
    {
        $apiKey = '';
        try {
            if (!empty($_SERVER['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_SERVER['GOOGLE_MAPS_API_KEY']; }
            elseif (!empty($_ENV['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_ENV['GOOGLE_MAPS_API_KEY']; }
            elseif (($tmp = getenv('GOOGLE_MAPS_API_KEY')) !== false && $tmp !== '') { $apiKey = (string) $tmp; }
            elseif (System::getContainer()->hasParameter('env(GOOGLE_MAPS_API_KEY)')) { $apiKey = (string) System::getContainer()->getParameter('env(GOOGLE_MAPS_API_KEY)'); }
        } catch (\Throwable $e) {}
        if ($apiKey === '') { return [null, null]; }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?language=de&key='.rawurlencode($apiKey)
             .'&address='.rawurlencode($address.' '.$country);
        $opts = ['http' => ['timeout' => 4.0]];
        try {
            $json = @file_get_contents($url, false, stream_context_create($opts));
            if ($json === false) { return [null, null]; }
            $data = json_decode($json, true);
            if (!\is_array($data) || ($data['status'] ?? '') !== 'OK') { return [null, null]; }
            $loc = $data['results'][0]['geometry']['location'] ?? null;
            if (!$loc || !isset($loc['lat'], $loc['lng'])) { return [null, null]; }
            return [round((float)$loc['lat'], 6), round((float)$loc['lng'], 6)];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }
}
