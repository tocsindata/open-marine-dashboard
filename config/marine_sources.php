<?php

return [
    'forecast_locations' => [
        'vancouver' => ['name' => 'Vancouver / English Bay', 'latitude' => 49.285, 'longitude' => -123.19],
        'victoria' => ['name' => 'Victoria / Juan de Fuca', 'latitude' => 48.40, 'longitude' => -123.42],
        'tofino' => ['name' => 'Tofino / offshore', 'latitude' => 49.15, 'longitude' => -126.00],
        'prince_rupert' => ['name' => 'Prince Rupert', 'latitude' => 54.30, 'longitude' => -130.32],
    ],
    'providers' => [
        'open_meteo_weather' => [
            'name' => 'Open-Meteo weather forecast', 'tier' => 'free_shared',
            'enabled' => (bool) env('MARINE_OPEN_METEO_ENABLED', true),
            'endpoint' => 'https://api.open-meteo.com/v1/forecast',
            'documentation' => 'https://open-meteo.com/en/docs', 'record_type' => 'weather_forecast',
            'stale_after_minutes' => 90,
        ],
        'open_meteo_marine' => [
            'name' => 'Open-Meteo marine forecast', 'tier' => 'free_shared',
            'enabled' => (bool) env('MARINE_OPEN_METEO_ENABLED', true),
            'endpoint' => 'https://marine-api.open-meteo.com/v1/marine',
            'documentation' => 'https://open-meteo.com/en/docs/marine-weather-api', 'record_type' => 'marine_forecast',
            'stale_after_minutes' => 180,
        ],
        'eccc_marine_bulletins' => [
            'name' => 'ECCC official Pacific marine forecasts and warnings', 'tier' => 'free_shared',
            'enabled' => (bool) env('MARINE_ECCC_ENABLED', true) && (bool) env('MARINE_ECCC_APPROVED', true),
            'endpoint' => 'https://dd.weather.gc.ca/today/marine_weather/pacific',
            'documentation' => 'https://eccc-msc.github.io/open-data/msc-data/marine-weather/readme_marine-weather-datamart_en/',
            'record_type' => 'official_marine_bulletin', 'stale_after_minutes' => 480,
        ],
        'eccc_alerts' => [
            'name' => 'Environment and Climate Change Canada alerts', 'tier' => 'free_shared',
            'enabled' => (bool) env('MARINE_ECCC_ENABLED', true) && (bool) env('MARINE_ECCC_APPROVED', true),
            'endpoint' => 'https://api.weather.gc.ca/collections/alerts-realtime/items',
            'documentation' => 'https://eccc-msc.github.io/open-data/msc-geomet/ogc_api_en/', 'record_type' => 'weather_alert',
            'stale_after_minutes' => 15,
        ],
        'chs_stations' => [
            'name' => 'Canadian Hydrographic Service IWLS stations', 'tier' => 'free_shared',
            'enabled' => (bool) env('MARINE_CHS_ENABLED', true) && (bool) env('MARINE_CHS_APPROVED', true),
            'endpoint' => 'https://api-iwls.dfo-mpo.gc.ca/api/v1/stations',
            'documentation' => 'https://tides.gc.ca/en/web-services-offered-canadian-hydrographic-service', 'record_type' => 'water_level_station',
            'stale_after_minutes' => 2160,
        ],
        'usgs_earthquakes' => [
            'name' => 'USGS earthquakes (supplemental tsunami flag)', 'tier' => 'free_shared',
            'enabled' => (bool) env('MARINE_USGS_EARTHQUAKES_ENABLED', true),
            'endpoint' => 'https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/all_day.geojson',
            'documentation' => 'https://earthquake.usgs.gov/earthquakes/feed/v1.0/geojson.php', 'record_type' => 'earthquake',
            'stale_after_minutes' => 15,
        ],
        'vesselfinder_livedata' => [
            'name' => 'VesselFinder LiveData AIS', 'tier' => 'user_token',
            'enabled' => (bool) env('MARINE_VESSELFINDER_ENABLED', true),
            'endpoint' => 'https://api.vesselfinder.com/livedata',
            'documentation' => 'https://api.vesselfinder.com/docs/livedata.html',
            'token_url' => 'https://www.vesselfinder.com/api', 'record_type' => 'ais_position',
            'stale_after_minutes' => 30,
        ],
        'stormglass' => [
            'name' => 'Stormglass marine weather', 'tier' => 'user_token', 'enabled' => (bool) env('MARINE_STORMGLASS_ENABLED', true),
            'endpoint' => 'https://api.stormglass.io/v2/weather/point', 'documentation' => 'https://docs.stormglass.io/', 'token_url' => 'https://stormglass.io/', 'record_type' => 'marine_forecast',
        ],
        'worldtides' => [
            'name' => 'WorldTides', 'tier' => 'user_token', 'enabled' => (bool) env('MARINE_WORLDTIDES_ENABLED', true),
            'endpoint' => 'https://www.worldtides.info/api/v3', 'documentation' => 'https://www.worldtides.info/apidocs', 'token_url' => 'https://www.worldtides.info/', 'record_type' => 'tide_forecast',
        ],
    ],
];
