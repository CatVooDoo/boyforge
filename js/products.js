"use strict";

/* products.js — единый источник данных о товарах BOYFORGE
   catId: tshirt (футболки), sweatshirt (свитшоты)
   Поля: name, price, img (главное фото), imgs (5–6 фото для галереи),
         sub, tags, desc, specs, tg */
(function () {
  "use strict";

  // характеристики футболок
  const BASE_SPECS = [
    ["Материал", "Футер 2-нитка, 95% хлопок / 5% эластан"],
    ["Плотность", "240 г/м²"],
    ["Печать", "DTF"],
    ["Размеры", "S–3XL"],
    ["Пошив", "Россия"]
  ];

  // характеристики свитшотов
  const SWEAT_SPECS = [
    ["Материал", "Футер 3-нитка, хлопок"],
    ["Плотность", "330 г/м²"],
    ["Печать", "DTF"],
    ["Размеры", "S–3XL"],
    ["Пошив", "Россия"]
  ];

  const tgLink = (name) =>
    "https://telegram.me/theboyforge?text=" +
    encodeURIComponent("Здравствуйте! Хочу заказать: " + name);

  // формирует массив из N фото по базовому имени:
  // gallery("images/p-bratya", 5) ->
  // ["images/p-bratya.jpg", "images/p-bratya-2.jpg", ... "-5.jpg"]
  const gallery = (base, count = 5) => {
    const arr = [base + ".jpg"];
    for (let i = 2; i <= count; i++) arr.push(base + "-" + i + ".jpg");
    return arr;
  };

    const PRODUCTS = [
    {
      id: 1, catId: "tshirt", cat: "Футболка",
      name: "Футболка «Братья Святославичи»", price: "3 200 ₽",
      img: "images/bratya/p-bratya.jpg",
      imgs: gallery("images/bratya/p-bratya", 7),
      sub: "Футболка · 95% хлопок / 5% эластан",
      tags: ["S–3XL", "Новая коллекция"],
      desc: "Футболка «Братья Святославичи» — авторский принт. Футер 2-нитка (95% хлопок / 5% эластан), 240 г/м², DTF-печать: мягкая, не трескается и держит цвет после стирок. Ровный крой, комфортная посадка на каждый день.",
      specs: BASE_SPECS,
      tg: tgLink("Братья Святославичи")
    },
    {
      id: 2, catId: "tshirt", cat: "Футболка",
      name: "Футболка «Врёшь Кривжа»", price: "3 200 ₽",
      img: "images/krivzha/p-krivzha.jpg",
      imgs: gallery("images/krivzha/p-krivzha", 7),
      sub: "Футболка · 95% хлопок / 5% эластан",
      tags: ["S–3XL", "Новая коллекция"],
      desc: "Футболка «Врёшь Кривжа» — авторский принт. Футер 2-нитка (95% хлопок / 5% эластан), 240 г/м², стойкая DTF-печать с насыщенными деталями. Носится легко и сочетается с любым гардеробом.",
      specs: BASE_SPECS,
      tg: tgLink("Врёшь Кривжа")
    },
    {
      id: 3, catId: "tshirt", cat: "Футболка",
      name: "Футболка «Варяга меч кормит»", price: "3 200 ₽",
      img: "images/varyag/p-varyag.jpg",
      imgs: gallery("images/varyag/p-varyag", 7),
      sub: "Футболка · 95% хлопок / 5% эластан",
      tags: ["S–3XL", "Новая коллекция"],
      desc: "Футболка «Варяга меч кормит» — авторский принт. Футер 2-нитка (95% хлопок / 5% эластан), 240 г/м², DTF-печать — мягкий стойкий принт. Уход простой: стирка при 30°, без отбеливателя.",
      specs: BASE_SPECS,
      tg: tgLink("Варяга меч кормит")
    },
    {
      id: 4, catId: "tshirt", cat: "Футболка",
      name: "Футболка «Рано меня похоронили»", price: "3 200 ₽",
      img: "images/ranopoh/p-ranopoh.jpg",
      imgs: gallery("images/ranopoh/p-ranopoh", 4),
      sub: "Футболка · 95% хлопок / 5% эластан",
      tags: ["S–3XL", "Хит"],
      desc: "Футболка «Рано меня похоронили» — авторский принт. Футер 2-нитка (95% хлопок / 5% эластан), 240 г/м², стойкая DTF-печать. Ровный крой, комфортная посадка на каждый день.",
      specs: BASE_SPECS,
      tg: tgLink("Рано меня похоронили")
    },
  ];

  // нормализация: гарантируем массив imgs и что главное фото идёт первым
  PRODUCTS.forEach((p) => {
    if (!Array.isArray(p.imgs) || !p.imgs.length) {
      p.imgs = [p.img];
    } else if (p.imgs[0] !== p.img) {
      p.imgs = [p.img, ...p.imgs.filter((s) => s !== p.img)];
    }
  });

  const getProductById = (id) =>
    PRODUCTS.find((p) => String(p.id) === String(id)) || null;

  const getRelated = (id, limit = 4) => {
    const cur = getProductById(id);
    if (!cur) return [];
    return PRODUCTS.filter((p) => p.catId === cur.catId && p.id !== cur.id).slice(0, limit);
  };

  const getByCat = (catId) =>
    catId ? PRODUCTS.filter((p) => p.catId === catId) : PRODUCTS.slice();

  window.PRODUCTS = PRODUCTS;
  window.getProductById = getProductById;
  window.getRelated = getRelated;
  window.getByCat = getByCat;
})();
