<?php

return [
    // Évenkénti cserenapok (Mt. szerinti áthelyezések)
    // 'workdays' = szombat, amikor dolgozni kell (a megjelölt pihenőnap helyett)
    // 'restdays' = áthelyezett pihenőnap (amit egy szombati munkanap "fedez")

    'overrides' => [
        2025 => [
            // Szombati munkanapok
            'workdays' => [
                '2025-05-17' => 'Munkanap a 2025-05-02 (péntek) pihenőnap helyett',
                '2025-10-18' => 'Munkanap a 2025-10-24 (péntek) pihenőnap helyett',
                '2025-12-13' => 'Munkanap a 2025-12-24 (szerda) pihenőnap helyett',
            ],

            // Áthelyezett pihenőnapok
            'restdays' => [
                '2025-05-02' => 'Pihenőnap (május 1. miatti áthelyezés)',
                '2025-10-24' => 'Pihenőnap (október 23. miatti áthelyezés)',
                '2025-12-24' => 'Pihenőnap (karácsony előestéje, áthelyezve)',
            ],
        ],

        // ide vehetsz fel további éveket ugyanebben a struktúrában…
    ],
];
