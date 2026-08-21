<section class="marine-map-panel" aria-labelledby="{{ $mapId }}-title">
<div class="map-heading"><div><p class="eyebrow">Operational map layers</p><h2 id="{{ $mapId }}-title">Weather, sea conditions, tides and vessel positions</h2></div><p class="map-updated" data-map-updated role="status">Loading map response…</p></div>
<div class="layer-controls" role="group" aria-label="Map layers">
<label><input type="checkbox" data-layer="radar" checked> ECCC rain radar</label>
<label><input type="checkbox" data-layer="weather" checked> Forecast locations</label>
<label><input type="checkbox" data-layer="temperature"> ECCC temperature field</label>
<label><input type="checkbox" data-layer="cloud"> ECCC cloud field</label>
<label><input type="checkbox" data-layer="wind"> ECCC wind field</label>
<label><input type="checkbox" data-layer="gust"> ECCC wind-gust field</label>
<label><input type="checkbox" data-layer="precipitation"> ECCC precipitation field</label>
<label><input type="checkbox" data-layer="marine" checked> Waves / currents</label>
<label><input type="checkbox" data-layer="alerts" checked> Weather alerts</label>
<label><input type="checkbox" data-layer="stations"> CHS tide stations</label>
<label><input type="checkbox" data-layer="earthquakes" checked> Earthquakes / tsunami flag</label>
<label><input type="checkbox" data-layer="vessels" checked> My vessels</label>
<label><input type="checkbox" data-layer="ais" checked> AIS traffic</label>
</div>
<div id="{{ $mapId }}" class="marine-map{{ $large ? ' marine-map-large' : '' }}" role="region" aria-label="Interactive marine information map" data-marine-map data-endpoint="{{ route('map.data') }}" data-radar-wms="https://geo.weather.gc.ca/geomet" data-radar-layer="RADAR_1KM_RRAI" data-temperature-layer="HRDPS.CONTINENTAL_TT" data-cloud-layer="RDPS.ETA_NT" data-wind-layer="RDPS.ETA_WSPD" data-gust-layer="RDPS.ETA_WGX" data-precipitation-layer="RDPS.ETA_RT"></div>
<noscript><p class="error">JavaScript is required for the interactive map. Use the linked official sources when map layers are unavailable.</p></noscript>
<div class="conditions-grid" aria-live="polite">
<details class="condition-section" data-condition-section="weather"><summary><span>Weather forecast</span><small data-report-summary="weather">Loading…</small></summary><div data-weather-report><p>Loading weather reports…</p></div></details>
<details class="condition-section" data-condition-section="marine"><summary><span>Marine conditions</span><small data-report-summary="marine">Loading…</small></summary><div data-marine-report><p>Loading wave and current forecasts…</p></div></details>
<details class="condition-section" data-condition-section="tides"><summary><span>Tide and water level</span><small data-report-summary="tides">Loading…</small></summary><div data-tide-report><p>Loading tide and water-level information…</p></div></details>
<details class="condition-section" data-condition-section="alerts"><summary><span>Official marine forecasts and alerts</span><small data-report-summary="alerts">Loading…</small></summary><div data-alert-report><p>Loading alert details…</p></div></details>
</div>
<div class="source-status" aria-labelledby="{{ $mapId }}-sources-title"><h3 id="{{ $mapId }}-sources-title">Source freshness and imports</h3><ul data-source-status aria-live="polite"><li>Loading imported-source timestamps…</li></ul></div>
<p class="map-attribution">Base map © <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap contributors</a>. Radar and alerts: <a href="https://eccc-msc.github.io/open-data/msc-geomet/ogc_api_en/" rel="noopener noreferrer">ECCC GeoMet</a>. Forecasts: <a href="https://open-meteo.com/en/docs" rel="noopener noreferrer">Open-Meteo</a> with underlying model attribution. Stations: <a href="https://tides.gc.ca/en/web-services-offered-canadian-hydrographic-service" rel="noopener noreferrer">CHS</a>. Earthquakes: <a href="https://earthquake.usgs.gov/earthquakes/feed/v1.0/geojson.php" rel="noopener noreferrer">USGS</a>. Modelled sea level is not an official coastal tide prediction. Verify safety-sensitive information with the issuing authority.</p>
</section>
