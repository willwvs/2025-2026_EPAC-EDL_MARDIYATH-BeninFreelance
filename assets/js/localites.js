/**
 * Villes principales du Bénin (formulaires + filtres).
 * Garder aligné avec config/marketplace.php → localites_suggestees
 */
window.BFCities = (function () {
  var CITIES = [
    'Cotonou',
    'Porto-Novo',
    'Parakou',
    'Abomey',
    'Abomey-Calavi',
    'Allada',
    'Aplahoué',
    'Bohicon',
    'Comè',
    'Covè',
    'Dassa-Zoumè',
    'Djougou',
    'Grand-Popo',
    'Kandi',
    'Lokossa',
    'Malanville',
    'Natitingou',
    'Nikki',
    'Ouidah',
    'Pobè',
    'Savé',
    'Savalou',
    'Autre',
  ];

  function list() {
    return CITIES.slice();
  }

  function fillSelect(id, placeholder, opts) {
    opts = opts || {};
    var sel = document.getElementById(id);
    if (!sel) return;
    var cur = sel.value;
    var includeAutre = opts.includeAutre !== false;
    var cities = includeAutre ? CITIES : CITIES.filter(function (c) { return c !== 'Autre'; });
    sel.innerHTML = '';
    if (placeholder !== false) {
      var o0 = document.createElement('option');
      o0.value = '';
      o0.textContent = placeholder || 'Sélectionner une ville';
      sel.appendChild(o0);
    }
    cities.forEach(function (city) {
      var o = document.createElement('option');
      o.value = city;
      o.textContent = city;
      sel.appendChild(o);
    });
    if (cur && Array.from(sel.options).some(function (opt) { return opt.value === cur; })) {
      sel.value = cur;
    } else if (opts.preserveValue && cur) {
      var extra = document.createElement('option');
      extra.value = cur;
      extra.textContent = cur;
      sel.appendChild(extra);
      sel.value = cur;
    }
  }

  return { list: list, fillSelect: fillSelect };
})();
