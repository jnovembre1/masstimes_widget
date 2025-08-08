/**
 * @file
 * Attach a Leaflet map, search form, autocomplete, and “click to open” behavior.
 */
(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.masstimesMap = {
    attach(context) {
      const mapEl = once('masstimesMap', '#masstimes-map', context);
      if (!mapEl.length) {
        return;
      }

      const settings = drupalSettings.masstimes_widget || {};
      const params = new URLSearchParams(window.location.search);

      // autocomplete and form hook
      const input = once('massSearchInput', '#mass-search-input', context)[0];
      const suggList = document.getElementById('mass-search-suggestions');
      const form = document.getElementById('mass-search-form');

      // function to clear suggestions
      function clearSuggestions() {
        suggList.innerHTML = '';
      }

      // when user types, fetch suggestions from Nominatim (US only)
      input.addEventListener('input', () => {
        const q = input.value.trim();
        if (q.length < 3) {
          clearSuggestions();
          return;
        }
        fetch(
          `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&countrycodes=us&limit=5&q=${encodeURIComponent(q)}`
        )
          .then((r) => r.json())
          .then((items) => {
            clearSuggestions();
            items.forEach((itm) => {
              const li = document.createElement('li');
              li.textContent = itm.display_name;
              li.dataset.lat = itm.lat;
              li.dataset.lon = itm.lon;
              li.addEventListener('click', () => {
                // on select: reload with coords
                params.set('lat', itm.lat);
                params.set('long', itm.lon);
                window.location.replace(window.location.pathname + '?' + params.toString());
              });
              suggList.appendChild(li);
            });
          })
          .catch(() => clearSuggestions());
      });

      // hide suggestions when clicking outside
      document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !suggList.contains(e.target)) {
          clearSuggestions();
        }
      });

      // prevent form submit from reloading page without coords
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        
      });

      // build map function
      function buildMap(lat, lon) {
        const map = L.map('masstimes-map').setView([lat, lon], 12);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
          attribution: '&copy; OpenStreetMap contributors & CARTO',
          subdomains: 'abcd',
          maxZoom: 19,
        }).addTo(map);

        const churchIcon = L.divIcon({
          className: 'church-marker',
          html: '<i class="fa fa-church" aria-hidden="true"></i>',
          iconSize: [24, 24],
          iconAnchor: [12, 24],
        });

        const geo = settings.geojson || { type: 'FeatureCollection', features: [] };
        L.geoJSON(geo, {
          pointToLayer(_feat, latlng) {
            return L.marker(latlng, { icon: churchIcon });
          },
          onEachFeature(feature, layer) {
            layer.bindPopup(feature.properties.name);
            layer.on('click', () => {
              const props = feature.properties;
              let popupContent = `<div class="leaflet-popup-content" style="font-size: 1.2rem; font-weight: bold;">${props.name}</div>`;
              
              if (props.distance !== null && props.distance !== undefined) {
                popupContent += `<div class="leaflet-popup-content" style="font-size: 1rem;">${props.distance} miles away</div>`;
              }
              
              layer.bindPopup(popupContent);
              const idx = feature.properties.index;
              const detail = document.querySelector(`.parish-card[data-index="${idx}"]`);
              if (detail) {
                detail.open = true;
                document.querySelector('.mass-content')
                        ?.scrollTo({ top: detail.offsetTop - 10, behavior: 'smooth' });
              }
            });
          },
        }).addTo(map);
      }

      // initialization ﬂow 
      if (params.has('lat') && params.has('long')) {
        buildMap(parseFloat(params.get('lat')), parseFloat(params.get('long')));
        return;
      }

      // try browser geolocation
      if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(
          (pos) => {
            params.set('lat', pos.coords.latitude);
            params.set('long', pos.coords.longitude);
            window.location.replace(window.location.pathname + '?' + params.toString());
          },
          () => {
            // denied or error so fallback to defaults
            if (settings.defaultLat != null && settings.defaultLon != null) {
              buildMap(settings.defaultLat, settings.defaultLon);
            } else {
              console.warn('Geolocation failed and no default coords set.');
            }
          }
        );
      } else {
        // no geolocation support
        if (settings.defaultLat != null && settings.defaultLon != null) {
          buildMap(settings.defaultLat, settings.defaultLon);
        } else {
          console.warn('Geolocation unavailable and no default coords set.');
        }
      }
    },
  };
})(Drupal, drupalSettings, once);
