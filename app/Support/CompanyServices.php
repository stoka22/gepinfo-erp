<?php

namespace App\Support;

class CompanyServices
{
    /**
     * A publikus honlap Szolgáltatások tartalma (aloldalankénti leírás + ikon).
     * Statikus szöveg, nincs hozzá adatbázis-tábla — ha bővül, itt kell módosítani.
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'telepites',
                'title' => 'Gépek telepítése, üzembe helyezése',
                'icon' => 'install',
                'summary' => 'Kínai fémmegmunkáló gépek szakszerű telepítése és üzembe helyezése a beérkezéstől a próbaüzemig.',
                'description' => "Sík- és csőlézerek, élhajlítók, lemezollók, valamint lézer hegesztő-tisztító berendezések telepítését és üzembe helyezését végezzük. A gép beérkezésétől a próbaüzemig minden lépést felügyelünk, hogy a berendezés az első pillanattól pontosan és biztonságosan dolgozzon.\n\n25 éves gyakorlati tapasztalattal tudjuk, mire kell figyelni egy új gép beillesztésekor a meglévő gyártási folyamatba — a gépészeti telepítéstől a vezérlés beüzemeléséig.\n\nAz üzembe helyezés részeként a gépet a helyi környezethez és biztonsági előírásokhoz illesztjük, majd közösen ellenőrizzük a próbaüzem eredményét, mielőtt a gyártás éles termelésbe áll.",
            ],
            [
                'slug' => 'atalakitas',
                'title' => 'Átalakítás, fejlesztés',
                'icon' => 'develop',
                'summary' => 'Meglévő gépsorok átalakítása és technológiai fejlesztése az aktuális gyártási igényekhez igazítva.',
                'description' => "Meglévő gépsorok átalakítását és technológiai fejlesztését vállaljuk, hogy azok jobban illeszkedjenek az Ön aktuális gyártási igényeihez. Akár új megmunkálási funkció hozzáadásáról, akár a kapacitás növeléséről van szó, a tervezéstől a kivitelezésig kísérjük végig a folyamatot.\n\nA fejlesztés mindig a meglévő géppark és a napi termelés ismeretében történik, hogy a beavatkozás minél kevesebb állásidővel járjon.\n\nÁtalakítás előtt mindig felmérjük a gép jelenlegi állapotát és a tervezett új feladatot, hogy a módosítás illeszkedjen a meglévő vezérléshez és biztonsági előírásokhoz.",
            ],
            [
                'slug' => 'automatizalas',
                'title' => 'Ipari automatizálás',
                'icon' => 'chip',
                'summary' => 'Gyártási folyamatok automatizálása a hibalehetőségek csökkentése és a kihozatal növelése érdekében.',
                'description' => "Gyártási folyamatok automatizálásával csökkenthető az emberi hibalehetőség és növelhető a kihozatal. Vezérléstechnikai megoldásainkkal a gépek önállóan, kiszámíthatóan és biztonságosan végzik a rájuk bízott feladatokat.\n\nAz automatizált beavatkozási pontok egyszerűen áttekinthetők maradnak, így a napi üzemeltetés is gördülékenyebb.\n\nAz automatizálás mértékét mindig az adott gyártósorhoz igazítjuk — egy-egy résztfolyamat kiváltásától a teljes gyártócella összehangolásáig.",
            ],
            [
                'slug' => 'plc-hmi',
                'title' => 'PLC- és HMI-programozás',
                'icon' => 'hmi',
                'summary' => 'PLC-vezérlők és HMI kezelőfelületek programozása, testreszabása és betanítása.',
                'description' => "PLC-vezérlők és HMI kezelőfelületek programozását és testreszabását végezzük, hogy a gép kezelése egyszerű és átlátható legyen az üzemi dolgozók számára is.\n\nIrodai és üzemi környezetben egyaránt tartunk betanítást az adott vezérlés és kezelőfelület használatáról, szabásterv-készítésről 2D és 3D szinten.\n\nAz elkészült programot dokumentáljuk is, hogy egy későbbi bővítés vagy szervizigény esetén ne kelljen nulláról újrakezdeni.",
            ],
            [
                'slug' => 'korszerusites',
                'title' => 'Korszerűsítés, modernizálás',
                'icon' => 'upgrade',
                'summary' => 'Régebbi gépek vezérlésének, elektronikájának és mechanikájának korszerűsítése.',
                'description' => "Régebbi gépek vezérlésének, elektronikájának és mechanikájának korszerűsítésével új életet adunk a meglévő géppark tagjainak.\n\nA modernizálás gyakran töredékébe kerül egy új gép beszerzésének, miközben a gyártási kapacitás és a megbízhatóság érdemben javul.\n\nTörekszünk arra, hogy a gép megszokott kezelése minél kevesebbet változzon, így a betanított kezelőknek ne kelljen újratanulniuk a gép használatát.",
            ],
            [
                'slug' => 'hibakereses',
                'title' => 'Hibakeresés, diagnosztika',
                'icon' => 'search',
                'summary' => 'Gyors és pontos hibakeresés leállás esetén, az állásidő minimalizálására.',
                'description' => "Leállás esetén gyors és pontos hibakeresésre van szükség — ebben segítünk 25 éves tapasztalattal a háttérben.\n\nElektromos, vezérléstechnikai és mechanikai hibák diagnosztizálásával minimalizáljuk az állásidőt, és igyekszünk megelőzni a hiba visszatérését is.\n\nA diagnosztika eredményét mindig érthetően, a döntéshez szükséges információkkal együtt adjuk át, hogy lássa, mi történt és mi a javasolt következő lépés.",
            ],
            [
                'slug' => 'javitas',
                'title' => 'Javítás, problémamegoldás',
                'icon' => 'fix',
                'summary' => 'Meghibásodott gépek javítása és üzemi problémák megoldása, gyakran helyszíni kiszállással.',
                'description' => "Meghibásodott gépek javítását és a felmerülő üzemi problémák megoldását vállaljuk, gyakran helyszíni kiszállással.\n\nCélunk, hogy a gép a lehető leggyorsabban, biztonságosan visszaálljon a termelésbe, és a hiba oka is tisztázódjon.\n\nJavítás után visszajelzést adunk arról is, mi okozta a hibát, és milyen egyszerű lépésekkel csökkenthető a hasonló meghibásodás esélye a jövőben.",
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        foreach (self::all() as $service) {
            if ($service['slug'] === $slug) {
                return $service;
            }
        }

        return null;
    }
}
