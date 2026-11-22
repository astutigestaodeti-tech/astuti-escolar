/* Mapa de localização (Leaflet): clique ou arraste o marcador; o formulário guarda o ponto. */
(function () {
    const box = document.getElementById('mapa-localizacao');
    if (!box || typeof L === 'undefined') return;

    const lat = parseFloat(box.dataset.lat);
    const lon = parseFloat(box.dataset.lon);
    const temPonto = !Number.isNaN(lat) && !Number.isNaN(lon);
    const escolaLat = parseFloat(box.dataset.escolaLat);
    const escolaLon = parseFloat(box.dataset.escolaLon);
    const temEscola = !Number.isNaN(escolaLat) && !Number.isNaN(escolaLon);

    const centro = temPonto ? [lat, lon] : (temEscola ? [escolaLat, escolaLon] : [-23.55, -46.63]);
    const mapa = L.map(box).setView(centro, temPonto || temEscola ? 16 : 11);
    L.tileLayer(box.dataset.tiles, { maxZoom: 19, attribution: box.dataset.atribuicao }).addTo(mapa);

    if (temEscola) {
        L.circleMarker([escolaLat, escolaLon], { radius: 9, color: '#1f4fa8', fillColor: '#1f4fa8', fillOpacity: 0.85 })
            .addTo(mapa).bindTooltip(box.dataset.escolaNome || 'Escola');
    }

    const campoLat = document.getElementById('latitude');
    const campoLon = document.getElementById('longitude');
    const origem = document.getElementById('geo_source');
    const situacao = document.getElementById('geo-situacao');
    let marcador = null;

    function marcar(ponto, manual) {
        if (!marcador) {
            marcador = L.marker(ponto, { draggable: true }).addTo(mapa);
            marcador.on('dragend', function () { marcar(marcador.getLatLng(), true); });
        } else {
            marcador.setLatLng(ponto);
        }
        const p = L.latLng(ponto);
        campoLat.value = p.lat.toFixed(6);
        campoLon.value = p.lng.toFixed(6);
        if (manual) {
            origem.value = 'mapa';
            situacao.textContent = 'Ponto marcado no mapa. Confira e salve.';
        }
    }

    if (temPonto) marcar([lat, lon], false);
    mapa.on('click', function (evento) { marcar(evento.latlng, true); });
})();
