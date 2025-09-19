(function () {
  function initMap(el) {
    if (el.dataset.inited) return;
    el.dataset.inited = "1";

    var lat = parseFloat(el.getAttribute("data-lat"));
    var lon = parseFloat(el.getAttribute("data-lon"));
    var name = el.getAttribute("data-name") || "";

    if (isNaN(lat) || isNaN(lon)) return;

    // Stäng av inbyggd attribution-kontroll i kartans hörn
    var map = L.map(el, { scrollWheelZoom: false, attributionControl: false });
    map.setView([lat, lon], 12);

    // Tile-lager utan att visa attribution i kartan; vi visar den själva under kartan
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19
    }).addTo(map);

    L.marker([lat, lon]).addTo(map).bindPopup(name || "Plats");

    // Säkerställ korrekt storlek efter att elementet har blivit synligt
    setTimeout(function(){ map.invalidateSize(); }, 150);
  }

  function scan() {
    document.querySelectorAll(".svv-map").forEach(initMap);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", scan);
  } else {
    scan();
  }

  // Stöd för dynamisk inladdning (block-editor/SPA-teman)
  var observer = new MutationObserver(function () { scan(); });
  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
