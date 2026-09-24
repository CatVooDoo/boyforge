"use strict";

(function () {
  "use strict";

  const PRODUCTS = [
    {
        "id": 7,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Колизей»",
        "price": "3 200 ₽",
        "img": "uploads\/products\/prod_20260915_140357_62ab6d22.jpg",
        "imgs": [
            "uploads\/products\/prod_20260915_140357_62ab6d22.jpg",
            "uploads\/products\/prod_20260915_140353_03c6a333.jpg",
            "uploads\/products\/prod_20260915_140353_f0f64bb9.jpg",
            "uploads\/products\/prod_20260915_140353_0797892a.jpg",
            "uploads\/products\/prod_20260915_140353_a62e1d5b.jpg",
            "uploads\/products\/prod_20260915_140353_4c78285d.jpg",
            "uploads\/products\/prod_20260915_140353_a129f056.jpg",
            "uploads\/products\/prod_20260915_140353_eb0792bb.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "Новая коллекция",
            "S–3XL"
        ],
        "desc": "Авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², стойкая DTF-печать. Ровный крой, комфортная посадка на каждый день.\r\n\r\nВдохновлённая легендами Колизея, эта футболка заряжена духом борьбы и непобедимой силы гладиаторов древнего Рима.",
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
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5!%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%A4%D1%83%D1%82%D0%B1%D0%BE%D0%BB%D0%BA%D0%B0%20%C2%AB%D0%9A%D0%BE%D0%BB%D0%B8%D0%B7%D0%B5%D0%B9%C2%BB"
    },
    {
        "id": 6,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Гладиатор»",
        "price": "3 200 ₽",
        "img": "uploads\/products\/prod_20260915_134957_9856812f.jpg",
        "imgs": [
            "uploads\/products\/prod_20260915_134957_9856812f.jpg",
            "uploads\/products\/prod_20260915_135102_4b94577a.jpg",
            "uploads\/products\/prod_20260915_135103_e7baaa9c.jpg",
            "uploads\/products\/prod_20260915_135106_15cb67da.jpg",
            "uploads\/products\/prod_20260915_135108_bca0ab98.jpg",
            "uploads\/products\/prod_20260915_135110_717ec8a8.jpg",
            "uploads\/products\/prod_20260915_135112_a5372bef.jpg",
            "uploads\/products\/prod_20260915_135114_2c003939.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "Новая коллекция",
            "S–3XL"
        ],
        "desc": "Авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², стойкая DTF-печать. Ровный крой, комфортная посадка на каждый день.\r\n\r\nВдохновлённая легендами Колизея, эта футболка заряжена духом борьбы и непобедимой силы гладиаторов древнего Рима.",
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
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5!%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%A4%D1%83%D1%82%D0%B1%D0%BE%D0%BB%D0%BA%D0%B0%20%C2%AB%D0%93%D0%BB%D0%B0%D0%B4%D0%B8%D0%B0%D1%82%D0%BE%D1%80%C2%BB"
    },
    {
        "id": 8,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Минотавр»",
        "price": "3 200 ₽",
        "img": "uploads\/products\/prod_20260915_140312_08678a5b.jpg",
        "imgs": [
            "uploads\/products\/prod_20260915_140312_08678a5b.jpg",
            "uploads\/products\/prod_20260915_140312_5c60971f.jpg",
            "uploads\/products\/prod_20260915_140312_132699b3.jpg",
            "uploads\/products\/prod_20260915_140312_5e7a8fbf.jpg",
            "uploads\/products\/prod_20260915_140312_7fd74c46.jpg",
            "uploads\/products\/prod_20260915_140312_2ad34f5b.jpg",
            "uploads\/products\/prod_20260915_140312_19c382d8.jpg",
            "uploads\/products\/prod_20260915_140312_638692f9.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "Новая коллекция",
            "S–3XL"
        ],
        "desc": "Авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², стойкая DTF-печать. Ровный крой, комфортная посадка на каждый день.\r\n\r\nЭта футболка стала воплощением древнего мифа и мужества. Выбор для тех, кто не боится идти до конца.",
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
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5!%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%A4%D1%83%D1%82%D0%B1%D0%BE%D0%BB%D0%BA%D0%B0%20%C2%AB%D0%9C%D0%B8%D0%BD%D0%BE%D1%82%D0%B0%D0%B2%D1%80%C2%BB"
    },
    {
        "id": 10,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Троя»",
        "price": "3 200 ₽",
        "img": "uploads\/products\/prod_20260915_141002_052c3346.jpg",
        "imgs": [
            "uploads\/products\/prod_20260915_141002_052c3346.jpg",
            "uploads\/products\/prod_20260915_141002_5f49cd2b.jpg",
            "uploads\/products\/prod_20260915_141002_ea3a354c.jpg",
            "uploads\/products\/prod_20260915_141002_4f777c32.jpg",
            "uploads\/products\/prod_20260915_141002_3bb78650.jpg",
            "uploads\/products\/prod_20260915_141002_f11f9556.jpg",
            "uploads\/products\/prod_20260915_141002_01a4d0be.jpg",
            "uploads\/products\/prod_20260915_141002_d2865d95.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "Новая коллекция",
            "S–3XL"
        ],
        "desc": "Авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², стойкая DTF-печать. Ровный крой, комфортная посадка на каждый день.\r\n\r\nЛегенда о великой осаде и громких победах. Почувствуй атмосферу древних сражений и неповторимый вкус истории.",
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
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5!%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%A4%D1%83%D1%82%D0%B1%D0%BE%D0%BB%D0%BA%D0%B0%20%C2%AB%D0%A2%D1%80%D0%BE%D1%8F%C2%BB"
    },
    {
        "id": 9,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Несломленный»",
        "price": "3 200 ₽",
        "img": "uploads\/products\/prod_20260915_140508_b5a397ad.jpg",
        "imgs": [
            "uploads\/products\/prod_20260915_140508_b5a397ad.jpg",
            "uploads\/products\/prod_20260915_140459_5d04d719.jpg",
            "uploads\/products\/prod_20260915_140459_9c9ae1d0.jpg",
            "uploads\/products\/prod_20260915_140459_e1a32b4b.jpg",
            "uploads\/products\/prod_20260915_140459_01545511.jpg",
            "uploads\/products\/prod_20260915_140459_70109ecb.jpg",
            "uploads\/products\/prod_20260915_140459_2aa9b175.jpg",
            "uploads\/products\/prod_20260915_140459_61d5f353.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "Новая коллекция",
            "S–3XL"
        ],
        "desc": "Авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², стойкая DTF-печать. Ровный крой, комфортная посадка на каждый день.\r\n\r\nДля тех, кто готов держаться до победного конца, несмотря ни на что. Символ непреодолимой воли и стойкости.",
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
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5!%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%A4%D1%83%D1%82%D0%B1%D0%BE%D0%BB%D0%BA%D0%B0%20%C2%AB%D0%9D%D0%B5%D1%81%D0%BB%D0%BE%D0%BC%D0%BB%D0%B5%D0%BD%D0%BD%D1%8B%D0%B9%C2%BB"
    },
    {
        "id": 11,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Центурион»",
        "price": "3 200 ₽",
        "img": "uploads\/products\/prod_20260915_141111_83079d8b.jpg",
        "imgs": [
            "uploads\/products\/prod_20260915_141111_83079d8b.jpg",
            "uploads\/products\/prod_20260915_141103_0ab592bb.jpg",
            "uploads\/products\/prod_20260915_141105_90991142.jpg",
            "uploads\/products\/prod_20260915_141108_968467af.jpg",
            "uploads\/products\/prod_20260915_141108_9f25da38.jpg",
            "uploads\/products\/prod_20260915_141108_4a716079.jpg",
            "uploads\/products\/prod_20260915_141108_1b0828a0.jpg",
            "uploads\/products\/prod_20260915_141108_ddd5bea0.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "Новая коллекция",
            "S–3XL"
        ],
        "desc": "Авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², стойкая DTF-печать. Ровный крой, комфортная посадка на каждый день.\r\n\r\nВдохновлённый образом римских военачальников, этот дизайн для тех, кто идет впереди и не уступает свои позиции.",
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
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5!%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%A4%D1%83%D1%82%D0%B1%D0%BE%D0%BB%D0%BA%D0%B0%20%C2%AB%D0%A6%D0%B5%D0%BD%D1%82%D1%83%D1%80%D0%B8%D0%BE%D0%BD%C2%BB"
    },
    {
        "id": 4,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Рано меня похоронили»",
        "price": "2 800 ₽",
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
            "Скидка"
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
    },
    {
        "id": 1,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «По белу снегу заскучал»",
        "price": "2 700 ₽",
        "img": "uploads\/products\/prod_20260915_102430_4f02c627.jpg",
        "imgs": [
            "uploads\/products\/prod_20260915_102430_4f02c627.jpg",
            "uploads\/products\/prod_20260915_102353_5107b489.jpg",
            "uploads\/products\/prod_20260915_102353_cbb2f7e6.jpg",
            "uploads\/products\/prod_20260915_102354_f49c7ad4.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "S–3XL",
            "Скидка"
        ],
        "desc": "Футболка «По белу снегу заскучал» — авторский принт. Футер 2-нитка (95% хлопок \/ 5% эластан), 240 г\/м², DTF-печать: мягкая, не трескается и держит цвет после стирок. Ровный крой, комфортная посадка на каждый день.",
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
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5%21%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%9F%D0%BE%20%D0%B1%D0%B5%D0%BB%D1%83%20%D1%81%D0%BD%D0%B5%D0%B3%D1%83%20%D0%B7%D0%B0%D1%81%D0%BA%D1%83%D1%87%D0%B0%D0%BB"
    },
    {
        "id": 3,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка «Варяга меч кормит»",
        "price": "2 800 ₽",
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
            "Скидка"
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
        "id": 5,
        "catId": "tshirt",
        "cat": "Футболка",
        "name": "Футболка чёрная мужская",
        "price": "1 ₽",
        "img": "images\/ranopoh\/p-ranopoh.jpg",
        "imgs": [
            "images\/ranopoh\/p-ranopoh.jpg"
        ],
        "sub": "Футболка · 95% хлопок \/ 5% эластан",
        "tags": [
            "Хит",
            "S–3XL"
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
        "tg": "https:\/\/telegram.me\/theboyforge?text=%D0%97%D0%B4%D1%80%D0%B0%D0%B2%D1%81%D1%82%D0%B2%D1%83%D0%B9%D1%82%D0%B5%21%20%D0%A5%D0%BE%D1%87%D1%83%20%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D1%8C%3A%20%D0%A4%D1%83%D1%82%D0%B1%D0%BE%D0%BB%D0%BA%D0%B0%20%C2%AB%D0%A0%D0%B0%D0%BD%D0%BE%20%D0%BC%D0%B5%D0%BD%D1%8F%20%D0%BF%D0%BE%D1%85%D0%BE%D1%80%D0%BE%D0%BD%D0%B8%D0%BB%D0%B8%C2%BB%20%28%D0%9A%D0%BE%D0%BF%D0%B8%D1%8F%29"
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
