<?php

namespace Cm\MemberGoogleMapsBundle\Dca;

use Contao\DataContainer;
use Contao\Environment;

class TlMemberCoordsWizard
{
    public function coordsPicker(DataContainer $dc): string
    {
        $base = rtrim(Environment::get('base'), '/');
        $fieldId = 'ctrl_cm_membergooglemaps_coords';
        $val = (string) ($dc->activeRecord->cm_membergooglemaps_coords ?? '');
        $lat = '';
        $lng = '';
        if (preg_match('~^\s*([+-]?[0-9]*\.?[0-9]+)\s*,\s*([+-]?[0-9]*\.?[0-9]+)\s*$~', $val, $m)) {
            $lat = $m[1];
            $lng = $m[2];
        }
        // Pass API key via query param so the static HTML can load Maps
        $apiKey = getenv('GOOGLE_MAPS_API_KEY') ?: '';
        if ($apiKey === '') {
            try {
                $c = \Contao\System::getContainer();
                if ($c->hasParameter('env(GOOGLE_MAPS_API_KEY)')) {
                    $apiKey = (string) $c->getParameter('env(GOOGLE_MAPS_API_KEY)');
                }
            } catch (\Throwable $e) {}
        }
        // Fallback: parse .env.local if accessible (last resort)
        if ($apiKey === '') {
            try {
                $c = \Contao\System::getContainer();
                $proj = (string) $c->getParameter('kernel.project_dir');
                $envFile = rtrim($proj, '/').'/.env.local';
                if (is_file($envFile)) {
                    $content = (string) @file_get_contents($envFile);
                    if ($content !== '') {
                        if (preg_match('~^\s*GOOGLE_MAPS_API_KEY\s*=\s*(?:\"([^\"]+)\"|([^\r\n]+))~m', $content, $m)) {
                            $apiKey = trim($m[1] !== '' ? $m[1] : $m[2]);
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }
        $href = $base.'/tools/coords-picker.php?field='.rawurlencode($fieldId);
        if ($lat !== '' && $lng !== '') { $href .= '&lat='.$lat.'&lng='.$lng; }
        if ($apiKey !== '') { $href .= '&gkey='.rawurlencode($apiKey); }
        $label = 'Koordinaten wählen';
        $title = 'Koordinate auf Karte wählen';
        $btnId = 'btn-cm-coords-'.(int) $dc->id;

        // Build inline JS for overlay once
        $script = <<<'HTML'
<script>
  (function(){
    if (window.cmCoordsOverlayInit) return; window.cmCoordsOverlayInit = true;
    window.cmOpenCoordsOverlay = function(fieldId, lat, lng, key, mapId){
      var ov = document.createElement('div');
      ov.id = 'cm-coords-overlay';
      ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;';
      var box = document.createElement('div');
      box.style.cssText = 'position:relative;width:920px;height:640px;background:#fff;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.35);overflow:hidden;';
      var close = document.createElement('button');
      close.type='button';
      close.textContent='×';
      close.title='Schließen';
      close.style.cssText='position:absolute;top:6px;right:10px;font-size:24px;line-height:24px;border:0;background:transparent;cursor:pointer;z-index:2;';
      close.onclick=function(){ document.body.removeChild(ov); };
      var url = (window.location.origin + '/tools/coords-picker.php');
      var qs = [];
      if (key) qs.push('gkey='+encodeURIComponent(key));
      if (lat && lng){ qs.push('lat='+encodeURIComponent(lat)); qs.push('lng='+encodeURIComponent(lng)); }
      if (mapId){ qs.push('mapId='+encodeURIComponent(mapId)); }
      if (qs.length){ url += '?' + qs.join('&'); }
      var ifr = document.createElement('iframe');
      ifr.src = url;
      ifr.style.cssText='width:100%;height:100%;border:0;';
      box.appendChild(close);
      box.appendChild(ifr);
      ov.appendChild(box);
      document.body.appendChild(ov);
      function onMsg(e){
        try{
          if (e.origin !== window.location.origin) return;
          if (!e.data || typeof e.data !== 'object') return;
          if (e.data.type === 'cm_coords'){
            var v = e.data.value || '';
            var el = document.getElementById(fieldId) || document.querySelector('input[name="cm_membergooglemaps_coords"]');
            if (el) el.value = v;
            window.removeEventListener('message', onMsg);
            if (ov && ov.parentNode) ov.parentNode.removeChild(ov);
          } else if (e.data.type === 'cm_close'){
            window.removeEventListener('message', onMsg);
            if (ov && ov.parentNode) ov.parentNode.removeChild(ov);
          }
        }catch(err){}
      }
      window.addEventListener('message', onMsg);
      document.addEventListener('keydown', function esc(e){ if (e.key==='Escape'){ window.removeEventListener('message', onMsg); if (ov&&ov.parentNode) ov.parentNode.removeChild(ov); document.removeEventListener('keydown', esc); } });
      ov.addEventListener('click', function(e){ if (e.target===ov){ window.removeEventListener('message', onMsg); if (ov&&ov.parentNode) ov.parentNode.removeChild(ov);} });
    };
  })();
</script>
HTML;

        $onclick = "cmOpenCoordsOverlay('".$fieldId."','".$lat."','".$lng."','".$apiKey."',''); return false;";
        $btn = ' <a id="'.$btnId.'" href="#" onclick="'.$onclick.'" class="tl_submit" title="'.$title.'">'.$label.'</a>';
        return $script.$btn;
    }

    public function coordsInlineMap(DataContainer $dc): string
    {
        $fieldId = 'ctrl_cm_membergooglemaps_coords';
        $wrapId  = 'cm-inline-map-wrap-'.$dc->id;
        $mapId   = 'cm-inline-map-'.$dc->id;
        $key = getenv('GOOGLE_MAPS_API_KEY') ?: '';
        if ($key === '') {
            try {
                $c = \Contao\System::getContainer();
                if ($c->hasParameter('env(GOOGLE_MAPS_API_KEY)')) {
                    $key = (string) $c->getParameter('env(GOOGLE_MAPS_API_KEY)');
                }
            } catch (\Throwable $e) {}
        }
        if ($key === '') {
            try {
                $c = \Contao\System::getContainer();
                $proj = (string) $c->getParameter('kernel.project_dir');
                $envFile = rtrim($proj, '/').'/.env.local';
                if (is_file($envFile)) {
                    $content = (string) @file_get_contents($envFile);
                    if ($content !== '') {
                        if (preg_match('~^\s*GOOGLE_MAPS_API_KEY\s*=\s*(?:\"([^\"]+)\"|([^\r\n]+))~m', $content, $m)) {
                            $key = trim($m[1] !== '' ? $m[1] : $m[2]);
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }
        // Container + controls
        $html = '<div id="'.$wrapId.'" style="margin-top:8px;border:1px solid #ddd;border-radius:4px;">'
              . '<div style="padding:6px;background:#f6f6f6;border-bottom:1px solid #ddd;display:flex;gap:6px;align-items:center;">'
              . '<strong>Karte</strong>'
              . '<span style="flex:1"></span>'
              . '<button type="button" class="tl_submit" id="btn-cm-save-'.$dc->id.'">Speichern</button>'
              . '<button type="button" class="tl_submit" id="btn-cm-close-'.$dc->id.'">Schließen</button>'
              . '</div>'
              . '<div id="'.$mapId.'" style="width:100%;height:320px"></div>'
              . '</div>';

        // Inline script to init map and sync with input field (HEREDOC to avoid escaping issues)
        $encodedKey = json_encode($key);
        $mapInitCb = 'cmInitInlineMap_' . $dc->id;
        $js = <<<HTML
<script>(function(){
var key = {$encodedKey};
var fieldId = '{$fieldId}'; var mapElId = '{$mapId}'; var wrapId = '{$wrapId}';
var mapIdStr = '';
function parseCoords(v){var m=(v||'').match(/^\s*([+-]?\d+(?:\.\d+)?)\s*,\s*([+-]?\d+(?:\.\d+)?)\s*$/);return m?{lat:parseFloat(m[1]),lng:parseFloat(m[2])}:null;}
function setInput(lat,lng){var el=document.getElementById(fieldId); if(el){ el.value=(lat.toFixed(6)+','+lng.toFixed(6)); }}
function load(cb){ if(!key){ var warn=document.createElement('div'); warn.style.cssText='padding:8px;color:#b00;background:#fee;border-top:1px solid #f99'; warn.textContent='Google Maps API Key fehlt. Bitte in .env.local setzen.'; var w=document.getElementById(wrapId); if(w) w.appendChild(warn); return;} if(window.google&&google.maps){cb();return;} var s=document.createElement('script'); var libs = (mapIdStr? '&libraries=marker' : ''); s.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(key)+libs+'&loading=async&callback={$mapInitCb}'; s.async=true; s.defer=true; document.body.appendChild(s); window['{$mapInitCb}']=cb; }
function init(){
  var el=document.getElementById(fieldId); var def={lat:51.163,lng:10.447}; var p=parseCoords(el?el.value:''); var c=p||def;
  var map=new google.maps.Map(document.getElementById(mapElId), {zoom:(p?10:6), center:c});
  var marker; if (mapIdStr && google.maps.marker && google.maps.marker.AdvancedMarkerElement){ marker=new google.maps.marker.AdvancedMarkerElement({map:map, position:c, gmpDraggable:true}); } else { marker=new google.maps.Marker({position:c,map:map,draggable:true}); }
  function moveTo(latlng){ marker.setPosition(latlng); map.panTo(latlng); setInput(latlng.lat(), latlng.lng()); }
  map.addListener('click', function(e){ moveTo(e.latLng); });
  if (marker.addListener){ marker.addListener('dragend', function(e){ var ll=e&&e.latLng?e.latLng:null; if(ll) setInput(ll.lat(), ll.lng()); }); }
  if (el){ el.addEventListener('input', function(){ var p=parseCoords(el.value); if(p){ marker.setPosition(p); map.panTo(p);} }); }
  var btnClose=document.getElementById('btn-cm-close-{$dc->id}'); if(btnClose){ btnClose.addEventListener('click', function(){ var w=document.getElementById(wrapId); if(w) w.style.display='none'; }); }
  var btnSave=document.getElementById('btn-cm-save-{$dc->id}'); if(btnSave){ btnSave.addEventListener('click', function(){ var save=document.querySelector('input[name="save"]'); if(save){ save.click(); } }); }
}
load(init);
})();</script>
HTML;

        return $html.$js;
    }
}
