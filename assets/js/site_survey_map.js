// Interactive map for Site Survey
// Dependencies: Leaflet, Leaflet.draw, turf (loaded via CDN in page)
(function () {
    // Global variable to store current polygon coordinates
    window.currentMapPolygonCoords = null;
    function formatLatLng(latlng) {
        return latlng.lat.toFixed(6) + ', ' + latlng.lng.toFixed(6);
    }

    function toFixedOrEmpty(v) { return (v === null || v === undefined || Number.isNaN(v)) ? '' : ('' + Number(v).toFixed(2)); }

    // Compute bearing/azimuth from p1 to p2 in degrees (0..360)
    function computeAzimuth(lat1, lon1, lat2, lon2) {
        var toRad = Math.PI / 180;
        var toDeg = 180 / Math.PI;
        var φ1 = lat1 * toRad;
        var φ2 = lat2 * toRad;
        var Δλ = (lon2 - lon1) * toRad;
        var y = Math.sin(Δλ) * Math.cos(φ2);
        var x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(Δλ);
        var θ = Math.atan2(y, x);
        var bearing = (θ * toDeg + 360) % 360;
        return bearing;
    }

    function init() {
        if (!document.getElementById('survey_map')) {
            console.log('Map container not found, skipping initialization');
            return;
        }

        if (window._survey_map) {
            console.log('Map already initialized, skipping');
            return;
        }

        // Check if Leaflet is loaded
        if (typeof L === 'undefined') {
            console.error('Leaflet not loaded, retrying in 500ms...');
            setTimeout(init, 500);
            return;
        }

        console.log('Initializing survey map...');

        // Tile layers
        var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        });

        // Mapbox satellite (user must provide MAPBOX_API_KEY in config and load token into JS via server if desired)
        var mbSat = L.tileLayer('https://api.mapbox.com/styles/v1/mapbox/satellite-v9/tiles/{z}/{x}/{y}?access_token={accessToken}', {
            maxZoom: 21,
            tileSize: 512,
            zoomOffset: -1,
            attribution: '© Mapbox',
            accessToken: window.MAPBOX_API_KEY || ''
        });

        var map = L.map('survey_map', { center: [39.5, -8.0], zoom: 6, layers: [osm] });
        // expose for external resize calls (e.g., when tab becomes visible)
        window._survey_map = map;

        console.log('Map created successfully');

        var baseMaps = { 'OSM': osm, 'Satellite': mbSat };
        L.control.layers(baseMaps, null, { position: 'topleft', collapsed: true }).addTo(map);

        // second map initialization removed to avoid double-initialization

        // Draw control
        var drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);
        var drawControl = new L.Control.Draw({
            edit: { featureGroup: drawnItems },
            draw: { polygon: true, polyline: false, rectangle: true, circle: false, marker: false }
        });

        var measuring = false;

        document.getElementById('survey_map_measure_area').addEventListener('click', function () {
            // toggle draw polygon tool
            measuring = !measuring;
            if (measuring) {
                map.addControl(drawControl);
                this.classList.add('active');
            } else {
                try { map.removeControl(drawControl); } catch (e) { }
                this.classList.remove('active');
            }
        });

        map.on(L.Draw.Event.CREATED, function (e) {
            var layer = e.layer;
            drawnItems.clearLayers();
            drawnItems.addLayer(layer);

            // compute area using turf if available
            try {
                var geojson = layer.toGeoJSON();
                var area = turf.area(geojson); // in m^2
                document.getElementById('survey_map_area_m2').value = area.toFixed(2);

                // Save polygon coordinates
                if (geojson.geometry && geojson.geometry.coordinates && geojson.geometry.coordinates[0]) {
                    window.currentMapPolygonCoords = geojson.geometry.coordinates[0].map(coord => [coord[1], coord[0]]); // Convert to [lat, lng]
                }
            } catch (err) {
                document.getElementById('survey_map_area_m2').value = '';
                window.currentMapPolygonCoords = null;
            }
        });

        // Click to set point
        var clickMarker = null; // marker for selected GPS
        var clickPoints = [];
        map.on('click', function (ev) {
            var latlng = ev.latlng;
            if (clickMarker) map.removeLayer(clickMarker);
            clickMarker = L.marker(latlng, { draggable: true }).addTo(map);
            document.getElementById('survey_map_gps').value = formatLatLng(latlng);
            // also populate main GPS input if present
            var mainGps = document.getElementById('gps');
            if (mainGps) mainGps.value = formatLatLng(latlng);

            // Track up to two points to compute azimuth
            clickPoints.push([latlng.lat, latlng.lng]);
            if (clickPoints.length > 2) clickPoints.shift();
            if (clickPoints.length === 2) {
                var a = clickPoints[0], b = clickPoints[1];
                var az = computeAzimuth(a[0], a[1], b[0], b[1]);
                document.getElementById('survey_map_azimuth_deg').value = az.toFixed(1);
                // optionally set roof_orientation_deg when user clicks a roof orientation field exists
                var orientInput = document.getElementById('roof_orientation_deg');
                if (orientInput) orientInput.value = Math.round(az);
            }

            clickMarker.on('dragend', function (e) {
                var ll = e.target.getLatLng();
                document.getElementById('survey_map_gps').value = formatLatLng(ll);
                if (mainGps) mainGps.value = formatLatLng(ll);
            });
        });

        document.getElementById('survey_map_clear').addEventListener('click', function () {
            drawnItems.clearLayers();
            if (clickMarker) { map.removeLayer(clickMarker); clickMarker = null; }
            clickPoints = [];
            document.getElementById('survey_map_gps').value = '';
            document.getElementById('survey_map_area_m2').value = '';
            document.getElementById('survey_map_azimuth_deg').value = '';
            window.currentMapPolygonCoords = null;
        });

        // Satellite toggle button
        var satBtn = document.getElementById('survey_map_toggle_sat');
        satBtn.addEventListener('click', function () {
            if (map.hasLayer(mbSat)) {
                map.removeLayer(mbSat);
                map.addLayer(osm);
                satBtn.classList.remove('active');
            } else {
                // if no access token, show a small warning console message
                if (!window.MAPBOX_API_KEY) console.warn('MAPBOX_API_KEY not set: Satellite tiles may not load.');
                map.addLayer(mbSat);
                map.removeLayer(osm);
                satBtn.classList.add('active');
            }
        });

        // center map on existing GPS value if present
        var gpsVal = document.getElementById('gps');
        var surveyGpsInput = document.getElementById('survey_map_gps');

        function goToCoords(coordStr) {
            if (!coordStr) return false;
            var parts = coordStr.split(/[\s,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
            if (parts.length >= 2) {
                var la = parseFloat(parts[0]);
                var lo = parseFloat(parts[1]);
                if (!Number.isNaN(la) && !Number.isNaN(lo) && la >= -90 && la <= 90 && lo >= -180 && lo <= 180) {
                    if (clickMarker) map.removeLayer(clickMarker);
                    map.setView([la, lo], 17);
                    clickMarker = L.marker([la, lo], { draggable: true }).addTo(map);
                    surveyGpsInput.value = formatLatLng({ lat: la, lng: lo });
                    if (gpsVal) gpsVal.value = formatLatLng({ lat: la, lng: lo });

                    clickMarker.on('dragend', function (e) {
                        var ll = e.target.getLatLng();
                        surveyGpsInput.value = formatLatLng(ll);
                        if (gpsVal) gpsVal.value = formatLatLng(ll);
                    });
                    return true;
                }
            }
            return false;
        }

        // Initial load from existing GPS field
        if (gpsVal && gpsVal.value) {
            goToCoords(gpsVal.value);
        }

        // Listen for manual input in main GPS field
        if (gpsVal) {
            gpsVal.addEventListener('change', function () {
                goToCoords(this.value);
            });
            gpsVal.addEventListener('keyup', function (e) {
                if (e.key === 'Enter') {
                    goToCoords(this.value);
                }
            });
        }

        // Listen for manual input in map's GPS field
        if (surveyGpsInput) {
            surveyGpsInput.removeAttribute('readonly');
            surveyGpsInput.addEventListener('change', function () {
                goToCoords(this.value);
            });
            surveyGpsInput.addEventListener('keyup', function (e) {
                if (e.key === 'Enter') {
                    goToCoords(this.value);
                }
            });
        }

        // Force map resize after short delay (fixes tiles not loading when tab is hidden at init)
        setTimeout(function () {
            try {
                map.invalidateSize();
                console.log('Initial map resize completed');
            } catch (e) {
                console.error('Error in initial map resize:', e);
            }
        }, 500);

        // Additional resize attempts for hidden tabs
        setTimeout(function () {
            try {
                map.invalidateSize();
                console.log('Second map resize attempt');
            } catch (e) { }
        }, 1000);

        setTimeout(function () {
            try {
                map.invalidateSize();
                console.log('Third map resize attempt');
            } catch (e) { }
        }, 2000);

        // Load existing polygon if available
        loadExistingPolygon(map, drawnItems);
    }

    // Ensure map redraws when the Installation Site tab becomes visible
    function ensureMapOnTabShow() {
        var siteTabBtn = document.getElementById('site-tab');
        if (!siteTabBtn) return;
        siteTabBtn.addEventListener('click', function () {
            setTimeout(function () {
                try { if (window._survey_map) window._survey_map.invalidateSize(); } catch (e) { }
            }, 300);
        });

        // Also use MutationObserver to detect when #site tab-pane becomes visible
        var sitePane = document.getElementById('site');
        if (sitePane) {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.attributeName === 'class' && sitePane.classList.contains('active')) {
                        console.log('Site tab became active, checking map...');
                        setTimeout(function () {
                            try {
                                if (window._survey_map) {
                                    window._survey_map.invalidateSize();
                                    console.log('Map resized successfully');
                                } else {
                                    // Map not initialized yet, initialize now
                                    console.log('Map not initialized, initializing now...');
                                    init();
                                }
                            } catch (e) {
                                console.error('Error resizing/initializing map:', e);
                            }
                        }, 100);
                    }
                });
            });
            observer.observe(sitePane, { attributes: true });
        }

        // Also listen for Bootstrap tab shown event
        document.addEventListener('shown.bs.tab', function (e) {
            if (e.target.id === 'site-tab') {
                console.log('Bootstrap tab shown event for site tab');
                setTimeout(function () {
                    try {
                        if (window._survey_map) {
                            window._survey_map.invalidateSize();
                            console.log('Map resized via Bootstrap event');
                        } else {
                            // Map not initialized yet, initialize now
                            console.log('Map not initialized via Bootstrap event, initializing now...');
                            init();
                        }
                    } catch (e) {
                        console.error('Error resizing/initializing map via Bootstrap event:', e);
                    }
                }, 200);
            }
        });
    }

    // initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOM ready, initializing map...');
            init();
            ensureMapOnTabShow();
        });
    } else {
        console.log('DOM already ready, initializing map...');
        init();
        ensureMapOnTabShow();
    }

    // Also initialize when page is fully loaded (for safety)
    window.addEventListener('load', function () {
        console.log('Page fully loaded, checking map...');
        if (!window._survey_map && document.getElementById('survey_map')) {
            console.log('Map not initialized, initializing now...');
            init();
        }
        ensureMapOnTabShow();
    });

    function loadExistingPolygon(map, drawnItems) {
        // Check if there's existing polygon data from PHP
        var existingCoords = document.getElementById('existing_polygon_coords');
        if (existingCoords && existingCoords.value) {
            try {
                var coords = JSON.parse(existingCoords.value);
                if (Array.isArray(coords) && coords.length > 0) {
                    // Create polygon from coordinates
                    var polygon = L.polygon(coords);
                    drawnItems.addLayer(polygon);

                    // Set global variable
                    window.currentMapPolygonCoords = coords;

                    // Fit map to polygon bounds
                    map.fitBounds(polygon.getBounds());

                    // Recalculate area
                    try {
                        var geojson = polygon.toGeoJSON();
                        var area = turf.area(geojson);
                        document.getElementById('survey_map_area_m2').value = area.toFixed(2);
                    } catch (err) {
                        console.error('Error calculating area from loaded polygon:', err);
                    }

                    console.log('Existing polygon loaded successfully');
                }
            } catch (e) {
                console.error('Error loading existing polygon:', e);
            }
        }
    }

})();
