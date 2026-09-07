"use strict";

(function () {
  "use strict";

  const PRODUCTS = [
    {
        "id": 1,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Братья Святославичи»",
        "price": "3 200 ₽",
        "img": "images\/bratya\/p-bratya.jpg",
        "imgs": [
            "images\/bratya\/p-bratya.jpg",
            "images\/bratya\/p-bratya-2.jpg",
            "images\/bratya\/p-bratya-3.jpg",
            "images\/bratya\/p-bratya-4.jpg",
            "images\/bratya\/p-bratya-5.jpg",
            "images\/bratya\/p-bratya-6.jpg",
            "images\/bratya\/p-bratya-7.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "Новая коллекция",
            "Хит",
            "S–3XL",
            "Оверсайз",
            "Лимитированный тираж",
            "Скидка 20%"
        ],
        "desc": "Футболка «Братья Святославичи» — авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², DTF-печать: мягкая, не трескается и держит цвет после стирок. Ровный крой, комфортная посадка на каждый день.",
        "specs": [
            [
                "Материал",
                "Футер 2-нитка, 95% хлопок \/ 5% эластан"
            ],
            [
                "Плотность",
                "240 г\/м²"
            ],
            [
                "Печать",
                "DTF"
            ],
            [
                "Размеры",
                "S–3XL"
            ],
            [
                "Пошив",
                "Россия"
            ]
        ],
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5%21%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%91%D1%80%D0%B0%D1%82%D1%8C%D1%8F%20%D0%A1%D0%B2%D1%8F%D1%82%D0%BE%D1%81%D0%BB%D0%B0%D0%B2%D0%B8%D1%87%D0%B8"
    },
    {
        "id": 2,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Врёшь Кривжа»",
        "price": "3 200 ₽",
        "img": "images\/krivzha\/p-krivzha.jpg",
        "imgs": [
            "images\/krivzha\/p-krivzha.jpg",
            "images\/krivzha\/p-krivzha-2.jpg",
            "images\/krivzha\/p-krivzha-3.jpg",
            "images\/krivzha\/p-krivzha-4.jpg",
            "images\/krivzha\/p-krivzha-5.jpg",
            "images\/krivzha\/p-krivzha-6.jpg",
            "images\/krivzha\/p-krivzha-7.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "S–3XL",
            "Новая коллекция"
        ],
        "desc": "Футболка «Врёшь Кривжа» — авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², стойкая DTF-печать с насыщенными деталями. Носится легко и сочетается с любым гардеробом.",
        "specs": [
            [
                "Материал",
                "Футер 2-нитка, 95% хлопок \/ 5% эластан"
            ],
            [
                "Плотность",
                "240 г\/м²"
            ],
            [
                "Печать",
                "DTF"
            ],
            [
                "Размеры",
                "S–3XL"
            ],
            [
                "Пошив",
                "Россия"
            ]
        ],
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5%21%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%92%D1%80%D1%91%D1%88%D1%8C%20%D0%9A%D1%80%D0%B8%D0%B2%D0%B6%D0%B0"
    },
    {
        "id": 3,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Варяга меч кормит»",
        "price": "3 200 ₽",
        "img": "images\/varyag\/p-varyag.jpg",
        "imgs": [
            "images\/varyag\/p-varyag.jpg",
            "images\/varyag\/p-varyag-2.jpg",
            "images\/varyag\/p-varyag-3.jpg",
            "images\/varyag\/p-varyag-4.jpg",
            "images\/varyag\/p-varyag-5.jpg",
            "images\/varyag\/p-varyag-6.jpg",
            "images\/varyag\/p-varyag-7.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "S–3XL",
            "Новая коллекция"
        ],
        "desc": "Футболка «Варяга меч кормит» — авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², DTF-печать — мягкий стойкий принт. Уход простой: стирка при 30°, без отбеливателя.",
        "specs": [
            [
                "Материал",
                "Футер 2-нитка, 95% хлопок \/ 5% эластан"
            ],
            [
                "Плотность",
                "240 г\/м²"
            ],
            [
                "Печать",
                "DTF"
            ],
            [
                "Размеры",
                "S–3XL"
            ],
            [
                "Пошив",
                "Россия"
            ]
        ],
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5%21%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%92%D0%B0%D1%80%D1%8F%D0%B3%D0%B0%20%D0%BC%D0%B5%D1%87%20%D0%BA%D0%BE%D1%80%D0%BC%D0%B8%D1%82"
    },
    {
        "id": 4,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Рано меня похоронили»",
        "price": "3 200 ₽",
        "img": "images\/ranopoh\/p-ranopoh.jpg",
        "imgs": [
            "images\/ranopoh\/p-ranopoh.jpg",
            "images\/ranopoh\/p-ranopoh-2.jpg",
            "images\/ranopoh\/p-ranopoh-3.jpg",
            "images\/ranopoh\/p-ranopoh-4.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "S–3XL",
            "Хит"
        ],
        "desc": "Футболка «Рано меня похоронили» — авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², стойкая DTF-печать. Ровный крой, комфортная посадка на каждый день.",
        "specs": [
            [
                "Материал",
                "Футер 2-нитка, 95% хлопок \/ 5% эластан"
            ],
            [
                "Плотность",
                "240 г\/м²"
            ],
            [
                "Печать",
                "DTF"
            ],
            [
                "Размеры",
                "S–3XL"
            ],
            [
                "Пошив",
                "Россия"
            ]
        ],
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5%21%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%A0%D0%B0%D0%BD%D0%BE%20%D0%BC%D0%B5%D0%BD%D1%8F%20%D0%BF%D0%BE%D1%85%D0%BE%D1%80%D0%BE%D0%BD%D0%B8%D0%BB%D0%B8"
    }
];

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
