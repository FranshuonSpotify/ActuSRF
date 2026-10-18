/* Texto de SEO que el visitante no ve pero sí leen los buscadores y los
   lectores de pantalla: el <h1> accesible de la portada, en cada idioma.
   Va en el último fichero de lang/ (zz_) porque FUNDE sobre lo que ya hubiera
   cargado cada idioma en vez de reemplazarlo. */
(function () {
  var h1 = {
    es: 'Superliga Frontier: Liga de Inazuma Eleven Victory Road',
    en: 'Superliga Frontier: Inazuma Eleven Victory Road League',
    gl: 'Superliga Frontier: Liga de Inazuma Eleven Victory Road',
    pt: 'Superliga Frontier: Liga de Inazuma Eleven Victory Road',
    fr: 'Superliga Frontier : la ligue d’Inazuma Eleven Victory Road',
    it: 'Superliga Frontier: la lega di Inazuma Eleven Victory Road',
    de: 'Superliga Frontier: Die Liga für Inazuma Eleven Victory Road',
    ar: 'Superliga Frontier: دوري Inazuma Eleven Victory Road',
    ro: 'Superliga Frontier: Liga Inazuma Eleven Victory Road',
    nl: 'Superliga Frontier: de competitie van Inazuma Eleven Victory Road',
    uk: 'Superliga Frontier: ліга Inazuma Eleven Victory Road',
    ru: 'Superliga Frontier: лига Inazuma Eleven Victory Road',
    zh: 'Superliga Frontier：《闪电十一人 Victory Road》联赛',
    pl: 'Superliga Frontier: liga Inazuma Eleven Victory Road',
    tr: 'Superliga Frontier: Inazuma Eleven Victory Road Ligi',
    ja: 'Superliga Frontier：イナズマイレブン 英雄たちのヴィクトリーロード リーグ',
    ko: 'Superliga Frontier: 이나즈마 일레븐 빅토리 로드 리그',
    bg: 'Superliga Frontier: лигата на Inazuma Eleven Victory Road',
    sr: 'Superliga Frontier: лига игре Inazuma Eleven Victory Road',
    el: 'Superliga Frontier: Η λίγκα του Inazuma Eleven Victory Road',
    cs: 'Superliga Frontier: liga Inazuma Eleven Victory Road',
    hu: 'Superliga Frontier: az Inazuma Eleven Victory Road ligája',
    sv: 'Superliga Frontier: ligan i Inazuma Eleven Victory Road',
    id: 'Superliga Frontier: Liga Inazuma Eleven Victory Road',
    vi: 'Superliga Frontier: giải đấu Inazuma Eleven Victory Road',
    th: 'Superliga Frontier: ลีก Inazuma Eleven Victory Road',
    hi: 'Superliga Frontier: Inazuma Eleven Victory Road की लीग',
    bn: 'Superliga Frontier: Inazuma Eleven Victory Road-এর লিগ',
    ms: 'Superliga Frontier: Liga Inazuma Eleven Victory Road',
    tl: 'Superliga Frontier: Liga ng Inazuma Eleven Victory Road'
  };
  window.SF_DICT_EXT = window.SF_DICT_EXT || {};
  Object.keys(h1).forEach(function (c) {
    window.SF_DICT_EXT[c] = window.SF_DICT_EXT[c] || {};
    window.SF_DICT_EXT[c]['seo.h1'] = h1[c];
  });
})();
