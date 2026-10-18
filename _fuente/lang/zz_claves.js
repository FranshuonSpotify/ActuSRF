/* Claves que el código usa por rutas que un barrido de T('clave') no ve
   (operadores ternarios, tablas FASE_KEY, etiquetas de staff…) y que los 10
   idiomas heredados ya tenían. Aquí van para los 20 idiomas nuevos. FUNDE sobre
   lo cargado, por eso su nombre empieza por zz_. */
(function () {
  var K = ['hist.pj', 'zone.playin.part', 'staff.fundador', 'staff.admin', 'staff.mod', 'scroll', 'br.grupo1', 'br.grupo2'];
  var T = {
    gl: ['Partidos', 'Partido polo Play-in', 'Fundador', 'Administrador', 'Moderador', 'Desprazar', '1.º Grupo {g}', '2.º Grupo {g}'],
    de: ['Spiele', 'Play-in-Spiel', 'Gründer', 'Administrator', 'Moderator', 'Scrollen', 'Gruppe {g}: 1.', 'Gruppe {g}: 2.'],
    ar: ['المباريات', 'مباراة Play-in', 'المؤسس', 'المشرف العام', 'مشرف', 'مرّر', 'أول المجموعة {g}', 'ثاني المجموعة {g}'],
    ro: ['Meciuri', 'Meci de Play-in', 'Fondator', 'Administrator', 'Moderator', 'Derulează', 'Locul 1 Grupa {g}', 'Locul 2 Grupa {g}'],
    nl: ['Wedstrijden', 'Play-in-duel', 'Oprichter', 'Beheerder', 'Moderator', 'Scroll', '1e Groep {g}', '2e Groep {g}'],
    uk: ['Матчі', 'Матч плей-ін', 'Засновник', 'Адміністратор', 'Модератор', 'Прокрутіть', '1-е місце, група {g}', '2-е місце, група {g}'],
    ru: ['Матчи', 'Матч плей-ин', 'Основатель', 'Администратор', 'Модератор', 'Прокрутите', '1-е место, группа {g}', '2-е место, группа {g}'],
    zh: ['比赛', '附加赛', '创始人', '管理员', '版主', '向下滚动', '{g} 组第 1', '{g} 组第 2'],
    tr: ['Maçlar', 'Play-in maçı', 'Kurucu', 'Yönetici', 'Moderatör', 'Kaydır', '{g} Grubu 1.’si', '{g} Grubu 2.’si'],
    el: ['Αγώνες', 'Αγώνας Play-in', 'Ιδρυτής', 'Διαχειριστής', 'Συντονιστής', 'Κύλιση', '1ος Ομίλου {g}', '2ος Ομίλου {g}'],
    cs: ['Zápasy', 'Zápas Play-in', 'Zakladatel', 'Administrátor', 'Moderátor', 'Posuň', '1. ve skupině {g}', '2. ve skupině {g}'],
    hu: ['Mérkőzések', 'Play-in mérkőzés', 'Alapító', 'Adminisztrátor', 'Moderátor', 'Görgess', '{g} csoport 1.', '{g} csoport 2.'],
    sv: ['Matcher', 'Play-in-match', 'Grundare', 'Administratör', 'Moderator', 'Scrolla', 'Etta i grupp {g}', 'Tvåa i grupp {g}'],
    id: ['Pertandingan', 'Pertandingan Play-in', 'Pendiri', 'Administrator', 'Moderator', 'Gulir', 'Juara Grup {g}', 'Runner-up Grup {g}'],
    vi: ['Trận đấu', 'Trận Play-in', 'Người sáng lập', 'Quản trị viên', 'Điều hành viên', 'Cuộn', 'Nhất bảng {g}', 'Nhì bảng {g}'],
    th: ['แมตช์', 'แมตช์เพลย์อิน', 'ผู้ก่อตั้ง', 'ผู้ดูแลระบบ', 'ผู้ควบคุม', 'เลื่อนลง', 'อันดับ 1 กลุ่ม {g}', 'อันดับ 2 กลุ่ม {g}'],
    hi: ['मैच', 'प्ले-इन मैच', 'संस्थापक', 'एडमिनिस्ट्रेटर', 'मॉडरेटर', 'स्क्रॉल करें', 'ग्रुप {g} में पहला', 'ग्रुप {g} में दूसरा'],
    bn: ['ম্যাচ', 'প্লে-ইন ম্যাচ', 'প্রতিষ্ঠাতা', 'অ্যাডমিনিস্ট্রেটর', 'মডারেটর', 'স্ক্রল করুন', 'গ্রুপ {g}-এ প্রথম', 'গ্রুপ {g}-এ দ্বিতীয়'],
    ms: ['Perlawanan', 'Perlawanan Play-in', 'Pengasas', 'Pentadbir', 'Moderator', 'Tatal', 'Tempat pertama Kumpulan {g}', 'Tempat kedua Kumpulan {g}'],
    tl: ['Mga laban', 'Laban sa Play-in', 'Tagapagtatag', 'Administrator', 'Moderator', 'Mag-scroll', 'Una sa Grupo {g}', 'Pangalawa sa Grupo {g}']
  };
  window.SF_DICT_EXT = window.SF_DICT_EXT || {};
  Object.keys(T).forEach(function (c) {
    window.SF_DICT_EXT[c] = window.SF_DICT_EXT[c] || {};
    K.forEach(function (k, i) { window.SF_DICT_EXT[c][k] = T[c][i]; });
  });
})();
