<?php

namespace Cm\MemberGoogleMapsBundle\Service;

use Contao\Backend;
use Contao\Controller;
use Contao\Database;
use Contao\Environment;
use Contao\Input;
use Contao\Message;
use Contao\System;
use Symfony\Component\Security\Csrf\CsrfToken;

class CoordsUpdater extends Backend
{
    public function handle(): void
    {
        $key = (string) (Input::get('key') ?? '');
        if ($key !== 'updCoords' && $key !== 'genCoords') {
            return;
        }

        if (!$this->isValidRequestToken()) {
            Message::addError('Ungültiger Request-Token.');
            Controller::redirect('contao?do=member');
        }

        $db = Database::getInstance();
        if ($key === 'genCoords') {
            $id = (int) (Input::get('id') ?? 0);
            if ($id <= 0) {
                Message::addError('Ungültige Mitglieds-ID.');
                Controller::redirect('contao?do=member');
            }
            $row = $db->prepare('SELECT id,street,postal,city,country,cm_membergooglemaps_coords,cm_membergooglemaps_lat,cm_membergooglemaps_lng FROM tl_member WHERE id=?')->limit(1)->execute($id)->row();
            if (!$row) {
                Message::addError('Mitglied nicht gefunden.');
                Controller::redirect('contao?do=member');
            }
            $res = $this->updateMemberCoordsIfNeeded($row);
            if ($res['status'] === 'updated') {
                Message::addConfirmation('GEO-Koordinaten wurden neu berechnet und gespeichert.');
            } elseif ($res['status'] === 'unchanged') {
                Message::addInfo('GEO-Koordinaten sind bereits aktuell.');
            } elseif ($res['status'] === 'skipped') {
                Message::addInfo('Adresse unvollständig – keine Berechnung durchgeführt.');
            } else {
                Message::addError('Koordinaten konnten nicht berechnet werden.');
            }
            Controller::redirect('contao?do=member&act=edit&id='.$id);
        }

        // updCoords: members with missing coordinates first, then stale auto-generated coordinates.
        $limit = min(50, max(1, (int) (Input::get('limit') ?: 25)));
        $sql = "SELECT id,street,postal,city,country,cm_membergooglemaps_coords,cm_membergooglemaps_lat,cm_membergooglemaps_lng FROM tl_member "
             . "WHERE (street<>'' OR city<>'' OR postal<>'') "
             . "AND (cm_membergooglemaps_coords='' OR cm_membergooglemaps_lat='' OR cm_membergooglemaps_lng='' OR cm_membergooglemaps_autocoords='1') "
             . "ORDER BY (cm_membergooglemaps_coords='') DESC, cm_membergooglemaps_attempts ASC, id ASC LIMIT ".(int)$limit;
        $members = $db->execute($sql);
        if (!$members->numRows) {
            Message::addInfo('Keine Mitglieder mit Adressangaben gefunden.');
            Controller::redirect('contao?do=member');
        }
        $updated = 0; $unchanged = 0; $skipped = 0; $errors = 0;
        while ($members->next()) {
            $res = $this->updateMemberCoordsIfNeeded($members->row());
            switch ($res['status']) {
                case 'updated':   $updated++; break;
                case 'unchanged': $unchanged++; break;
                case 'skipped':   $skipped++; break;
                default:          $errors++; break;
            }
        }
        Message::addConfirmation(sprintf('GEO-Koordinaten – aktualisiert: %d, unverändert: %d, übersprungen: %d, Fehler: %d', $updated, $unchanged, $skipped, $errors));
        Controller::redirect('contao?do=member');
    }

    private function geocode(string $address, string $country = 'DE'): array
    {
        $apiKey = '';
        try {
            if (System::getContainer()->hasParameter('google_maps_api_key')) { $apiKey = (string) System::getContainer()->getParameter('google_maps_api_key'); }
            elseif (!empty($_SERVER['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_SERVER['GOOGLE_MAPS_API_KEY']; }
            elseif (!empty($_ENV['GOOGLE_MAPS_API_KEY'])) { $apiKey = (string) $_ENV['GOOGLE_MAPS_API_KEY']; }
            elseif (($tmp = getenv('GOOGLE_MAPS_API_KEY')) !== false && $tmp !== '') { $apiKey = (string) $tmp; }
            elseif (System::getContainer()->hasParameter('env(GOOGLE_MAPS_API_KEY)')) { $apiKey = (string) System::getContainer()->getParameter('env(GOOGLE_MAPS_API_KEY)'); }
        } catch (\Throwable $e) {}
        if ($apiKey === '') { return [null, null]; }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?language=de&key='.rawurlencode($apiKey)
             .'&address='.rawurlencode($address.' '.$country);
        $opts = ['http' => ['timeout' => 2.0]];
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

    /**
     * Update member coordinates if missing or if they deviate from the geocoded address.
     * @param array $row tl_member row (must include id, street, postal, city, country, coords/lat/lng)
     * @return array {status: updated|unchanged|skipped|error}
     */
    private function updateMemberCoordsIfNeeded(array $row): array
    {
        $addr = trim((($row['street'] ?? '') ? $row['street'].' ' : '').(($row['postal'] ?? '') ? $row['postal'].' ' : '').($row['city'] ?? ''));
        $country = strtoupper(trim((string) ($row['country'] ?? 'DE')));
        if ($addr === '') {
            return ['status' => 'skipped'];
        }
        [$newLat, $newLng] = $this->geocode($addr, $country);
        if ($newLat === null || $newLng === null) {
            return ['status' => 'error'];
        }
        $have = trim((string) ($row['cm_membergooglemaps_coords'] ?? ''));
        $oldLat = (string) ($row['cm_membergooglemaps_lat'] ?? '');
        $oldLng = (string) ($row['cm_membergooglemaps_lng'] ?? '');
        $curLat = null; $curLng = null;
        if ($oldLat !== '' && $oldLng !== '') {
            $curLat = (float) $oldLat; $curLng = (float) $oldLng;
        } elseif ($have !== '' && strpos($have, ',') !== false) {
            [$a,$b] = array_map('trim', explode(',', $have, 2));
            if ($a !== '' && $b !== '') { $curLat = (float)$a; $curLng = (float)$b; }
        }
        $needsUpdate = true;
        if ($curLat !== null && $curLng !== null) {
            $dist = $this->haversine($curLat, $curLng, (float)$newLat, (float)$newLng);
            // Update only if deviation > 100 m
            $needsUpdate = ($dist > 0.1);
        }
        if (!$needsUpdate) {
            return ['status' => 'unchanged'];
        }
        $coords = $newLat.','.$newLng;
        try {
            Database::getInstance()->prepare("UPDATE tl_member SET cm_membergooglemaps_coords=?, cm_membergooglemaps_lat=?, cm_membergooglemaps_lng=?, cm_membergooglemaps_attempts=cm_membergooglemaps_attempts+1 WHERE id=?")
                ->execute($coords, (string)$newLat, (string)$newLng, (int)$row['id']);
            return ['status' => 'updated'];
        } catch (\Throwable $e) {
            return ['status' => 'error'];
        }
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // returns distance in km
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            return $R * $c;
    }

    private function isValidRequestToken(): bool
    {
        try {
            $container = System::getContainer();
            $token = (string) (Input::get('rt') ?? '');

            return $token !== ''
                && $container->get('contao.csrf.token_manager')->isTokenValid(
                    new CsrfToken((string) $container->getParameter('contao.csrf_token_name'), $token)
                );
        } catch (\Throwable $e) {
            return false;
        }
    }
}
