<?php

namespace App\Support;

class WebsiteInquiryQuestionnaire
{
    /**
     * A "Weboldal igényfelmérő kérdőív" teljes szerkezete (szekció → mezők).
     * Ezt rendereli adatvezérelten a publikus űrlap (resources/views/weboldal-igenyfelmero.blade.php)
     * ÉS ugyanez adja a feliratokat a Filament admin megtekintő nézetéhez is
     * (WebsiteInquiryResource) — egy helyen tartva a kérdés-feliratokat, hogy a kettő
     * sose térjen el egymástól.
     *
     * Mező típusok: text, email, date, textarea, radio, checkbox.
     * A radio/checkbox mezőknél az 'options' kulcs=>felirat formában adja a lehetséges
     * válaszokat; a mentett érték ennek a kulcsnak felel meg (checkboxnál kulcsok tömbje).
     */
    public static function sections(): array
    {
        return [
            [
                'title' => '1. Cégadatok és kapcsolattartó',
                'fields' => [
                    ['key' => 'cegnev', 'label' => 'Cégnév', 'type' => 'text'],
                    ['key' => 'tevekenyseg', 'label' => 'Tevékenység röviden', 'type' => 'text'],
                    ['key' => 'telephely_cime', 'label' => 'Telephely címe', 'type' => 'text'],
                    ['key' => 'kapcsolattarto_neve', 'label' => 'Kapcsolattartó neve', 'type' => 'text'],
                    ['key' => 'telefon', 'label' => 'Telefon', 'type' => 'text'],
                    ['key' => 'email', 'label' => 'E-mail', 'type' => 'email'],
                    ['key' => 'nyitvatartas', 'label' => 'Nyitvatartás', 'type' => 'text'],
                    [
                        'key' => 'van_weboldal', 'label' => 'Van jelenleg weboldala?', 'type' => 'radio',
                        'options' => [
                            'nincs_elso' => 'Nincs, ez lesz az első',
                            'van_elavult' => 'Van, de elavult, újat szeretnék helyette',
                            'van_frissiteni' => 'Van, csak frissíteni vagy bővíteni kell',
                        ],
                    ],
                    ['key' => 'jelenlegi_weboldal_cim', 'label' => 'Jelenlegi weboldal címe (ha van)', 'type' => 'text'],
                ],
            ],
            [
                'title' => '2. A weboldal célja és célközönsége',
                'fields' => [
                    [
                        'key' => 'weboldal_celja', 'label' => 'Mi a weboldal legfontosabb feladata?', 'type' => 'checkbox',
                        'options' => [
                            'google_terkep' => 'Megtaláljanak a Google-ben és a térképen',
                            'tobb_hivas' => 'Több telefonhívás és személyes megkeresés',
                            'ajanlatkeres_online' => 'Ajánlatkérések fogadása online',
                            'termek_bemutatas' => 'Termékek és szolgáltatások bemutatása',
                            'akciok_kozzetetele' => 'Akciók, újdonságok közzététele',
                            'megbizhato_megjelenes' => 'Megbízható, korszerű megjelenés a versenytársakhoz képest',
                        ],
                    ],
                    [
                        'key' => 'fo_vasarlok', 'label' => 'Kik a fő vásárlói?', 'type' => 'checkbox',
                        'options' => [
                            'maganszemelyek' => 'Magánszemélyek',
                            'vallalkozok_kivitelezok' => 'Vállalkozók, kivitelezők',
                            'cegek_intezmenyek' => 'Cégek, intézmények',
                            'viszontelado' => 'Viszonteladók',
                        ],
                    ],
                    [
                        'key' => 'terulet', 'label' => 'Mekkora területről várja a vevőket?', 'type' => 'radio',
                        'options' => [
                            'helyben' => 'Helyben és a közvetlen környékről',
                            'megyebol' => 'A megyéből',
                            'orszagosan' => 'Több megyéből vagy országosan',
                        ],
                    ],
                    ['key' => 'versenytarsak', 'label' => 'Három versenytárs vagy tetszetős weboldal, amelyet mintának tekint', 'type' => 'textarea'],
                ],
            ],
            [
                'title' => '3. Aloldalak és tartalom',
                'fields' => [
                    [
                        'key' => 'aloldalak', 'label' => 'Milyen aloldalakra van szükség?', 'type' => 'checkbox',
                        'options' => [
                            'fooldal' => 'Főoldal',
                            'rolunk' => 'Rólunk, cégtörténet',
                            'termekek_szolgaltatasok' => 'Termékek vagy szolgáltatások',
                            'arlista' => 'Árlista',
                            'akciok_ajanlatok' => 'Akciók, aktuális ajánlatok',
                            'szallitas_feltetelek' => 'Szállítás, kiszolgálás feltételei',
                            'referenciak_galeria' => 'Referenciák, galéria',
                            'hirek_blog' => 'Hírek, blog',
                            'gyik' => 'Gyakori kérdések',
                            'dokumentumok' => 'Letölthető dokumentumok',
                            'kapcsolat_terkep' => 'Kapcsolat, térkép, nyitvatartás',
                            'egyeb' => 'Egyéb',
                        ],
                    ],
                    ['key' => 'aloldalak_egyeb', 'label' => 'Egyéb aloldal', 'type' => 'text'],
                    [
                        'key' => 'nyelvek', 'label' => 'Milyen nyelveken jelenjen meg az oldal?', 'type' => 'radio',
                        'options' => [
                            'csak_magyar' => 'Csak magyarul',
                            'magyar_angol' => 'Magyarul és angolul',
                            'magyar_nemet' => 'Magyarul és németül',
                            'egyeb_nyelv' => 'Egyéb nyelv',
                        ],
                    ],
                    ['key' => 'nyelvek_egyeb', 'label' => 'Egyéb nyelv', 'type' => 'text'],
                ],
            ],
            [
                'title' => '4. Termékek, szolgáltatások, árak',
                'fields' => [
                    [
                        'key' => 'kinalat_bemutatas', 'label' => 'Hogyan szeretné bemutatni a kínálatot?', 'type' => 'radio',
                        'options' => [
                            'fo_kategoriak_leirassal' => 'Csak a fő kategóriák rövid leírással',
                            'kategoriak_markak' => 'Kategóriák és a forgalmazott márkák',
                            'teteles_lista_kepekkel' => 'Tételes lista képekkel és leírással',
                            'kereshetok_szurheto' => 'Kereshető, szűrhető katalógus',
                        ],
                    ],
                    [
                        'key' => 'termekek_szama', 'label' => 'Körülbelül hány terméket vagy szolgáltatást kell megjeleníteni?', 'type' => 'radio',
                        'options' => [
                            'alatt_20' => '20 alatt',
                            '20_100' => '20–100',
                            '100_500' => '100–500',
                            'felett_500' => '500 felett',
                        ],
                    ],
                    [
                        'key' => 'arak_oldalon', 'label' => 'Szerepeljenek árak az oldalon?', 'type' => 'radio',
                        'options' => [
                            'nincs_ar' => 'Nem, árat csak megkeresésre adunk',
                            'pdf_arlista' => 'Letölthető árlista (PDF)',
                            'kiemelt_termek_ara' => 'Néhány kiemelt termék ára',
                            'minden_tetel_ara' => 'Minden tétel ára',
                        ],
                    ],
                    [
                        'key' => 'ar_valtozas_gyakorisaga', 'label' => 'Milyen gyakran változnak az árak és a kínálat?', 'type' => 'radio',
                        'options' => [
                            'hetente' => 'Hetente vagy gyakrabban',
                            'havonta' => 'Havonta',
                            'szezon' => 'Szezononként',
                            'ritkan' => 'Ritkán',
                        ],
                    ],
                    [
                        'key' => 'ar_frissito', 'label' => 'Ki fogja frissíteni az árakat és az akciókat?', 'type' => 'radio',
                        'options' => [
                            'sajat_munkatars' => 'Saját munkatárs, betanítás után',
                            'keszito_megbizas' => 'A weboldal készítője, megbízás alapján',
                        ],
                    ],
                    [
                        'key' => 'webaruhaz_terv', 'label' => 'Tervez később online értékesítést (webáruház)?', 'type' => 'radio',
                        'options' => [
                            'igen_egy_even_belul' => 'Igen, egy éven belül',
                            'talan_kesobb' => 'Talán később',
                            'nem' => 'Nem',
                        ],
                    ],
                ],
            ],
            [
                'title' => '5. Funkciók',
                'fields' => [
                    [
                        'key' => 'funkciok', 'label' => 'Milyen funkciókra van szükség?', 'type' => 'checkbox',
                        'options' => [
                            'kapcsolatfelveteli_urlap' => 'Kapcsolatfelvételi űrlap',
                            'ajanlatkero_urlap' => 'Ajánlatkérő űrlap',
                            'fajlfeltoltes' => 'Fájlfeltöltés az űrlapon (pl. lista, tervrajz, fotó)',
                            'kattinthato_telefon' => 'Kattintható telefonszám és útvonaltervezés',
                            'google_terkep_beagyazas' => 'Google Térkép beágyazása',
                            'kepgaleria' => 'Képgaléria',
                            'kereso' => 'Kereső az oldalon',
                            'hirlevel_feliratkozas' => 'Hírlevél-feliratkozás',
                            'social_media' => 'Facebook- vagy Instagram-bejegyzések megjelenítése',
                            'velemenyek' => 'Vásárlói vélemények megjelenítése',
                            'idopontfoglalas' => 'Időpontfoglalás',
                            'felugro_ertesites' => 'Felugró értesítés (pl. ünnepi nyitvatartás, akció)',
                            'egyeb' => 'Egyéb',
                        ],
                    ],
                    ['key' => 'funkciok_egyeb', 'label' => 'Egyéb funkció', 'type' => 'text'],
                    ['key' => 'urlap_email', 'label' => 'Hová érkezzenek az űrlapok üzenetei (e-mail-cím)?', 'type' => 'email'],
                    [
                        'key' => 'google_cegprofil', 'label' => 'Van Google Cégprofilja (megjelenés a Google Térképen)?', 'type' => 'radio',
                        'options' => [
                            'van_kezeljuk' => 'Van, és kezeljük',
                            'van_nem_ferunk_hozza' => 'Van, de nem férünk hozzá',
                            'nincs_kerem_letrehozasat' => 'Nincs, kérem a létrehozását',
                            'nem_tudom' => 'Nem tudom',
                        ],
                    ],
                ],
            ],
            [
                'title' => '6. Arculat, szövegek, képek',
                'fields' => [
                    [
                        'key' => 'rendelkezesre_all', 'label' => 'Mi áll rendelkezésre?', 'type' => 'checkbox',
                        'options' => [
                            'logo_jo_minosegben' => 'Logó jó minőségben (vektoros vagy nagy felbontású)',
                            'szinek_betutipus' => 'Céges színek, betűtípus',
                            'sajat_fotok' => 'Saját fotók a telephelyről, termékekről, csapatról',
                            'kesz_szovegek' => 'Kész szövegek az aloldalakhoz',
                            'gyartoi_kepek' => 'Gyártói, beszállítói képek és logók felhasználási engedéllyel',
                        ],
                    ],
                    [
                        'key' => 'mit_ker_keszitotol', 'label' => 'Mit kér a készítőtől?', 'type' => 'checkbox',
                        'options' => [
                            'logo_tervezes' => 'Logó tervezése vagy felújítása',
                            'szovegiras' => 'Szövegírás a megadott információk alapján',
                            'szoveg_atfesules' => 'Meglévő szövegek átfésülése',
                            'fotozas_helyszinen' => 'Fotózás a helyszínen',
                            'kepek_beszerzese' => 'Képek beszerzése képtárból',
                        ],
                    ],
                    [
                        'key' => 'megjelenes', 'label' => 'Milyen megjelenést képzel el?', 'type' => 'radio',
                        'options' => [
                            'letisztult_egyszeru' => 'Letisztult, egyszerű',
                            'hagyomanyos_megbizhato' => 'Hagyományos, megbízhatóságot sugárzó',
                            'modern_latvanyos' => 'Modern, látványos',
                            'keszitore_bizom' => 'A készítőre bízom',
                        ],
                    ],
                ],
            ],
            [
                'title' => '7. Technikai háttér',
                'fields' => [
                    [
                        'key' => 'domain_allapot', 'label' => 'Domain név (webcím)', 'type' => 'radio',
                        'options' => [
                            'van_ceg_neven' => 'Van, a cég nevén',
                            'van_mas_neven' => 'Van, de más nevén (pl. korábbi készítő)',
                            'nincs_regisztralni_kell' => 'Nincs, regisztrálni kell',
                        ],
                    ],
                    ['key' => 'domain_nev', 'label' => 'Meglévő vagy kívánt domain', 'type' => 'text'],
                    [
                        'key' => 'tarhely', 'label' => 'Tárhely', 'type' => 'radio',
                        'options' => [
                            'van_megtartani' => 'Van, szeretném megtartani',
                            'van_valtanek' => 'Van, de váltanék',
                            'nincs_javaslatot_kerek' => 'Nincs, kérem a készítő javaslatát',
                        ],
                    ],
                    [
                        'key' => 'cegek_email', 'label' => 'Céges e-mail-címek (pl. info@cegnev.hu)', 'type' => 'radio',
                        'options' => [
                            'vannak_maradjanak' => 'Vannak, maradjanak',
                            'ujakat_kerek' => 'Újakat kérek',
                            'nem_szukseges' => 'Nem szükséges',
                        ],
                    ],
                    ['key' => 'cegek_email_darabszam', 'label' => 'Újakat kérek, darabszám', 'type' => 'text'],
                    [
                        'key' => 'hozzaferesi_adatok', 'label' => 'Rendelkezik a hozzáférési adatokkal (domain, tárhely, régi weboldal)?', 'type' => 'radio',
                        'options' => [
                            'igen' => 'Igen',
                            'reszben' => 'Részben',
                            'nem_korabbi_keszitonel' => 'Nem, a korábbi készítőnél vannak',
                        ],
                    ],
                ],
            ],
            [
                'title' => '8. Jogi tartalmak',
                'fields' => [
                    [
                        'key' => 'jogi_rendelkezesre_all', 'label' => 'Mi áll rendelkezésre?', 'type' => 'checkbox',
                        'options' => [
                            'adatkezelesi_tajekoztato' => 'Adatkezelési tájékoztató',
                            'impresszum' => 'Impresszum adatai (cégjegyzékszám, adószám, székhely)',
                            'aszf' => 'Általános szerződési feltételek',
                            'egyik_sem' => 'Egyik sem',
                        ],
                    ],
                    [
                        'key' => 'jogi_mit_ker', 'label' => 'Mit kér a készítőtől?', 'type' => 'checkbox',
                        'options' => [
                            'sutikezeles_beallitasa' => 'Sütikezelési (cookie) sáv beállítása',
                            'adatkezelesi_minta' => 'Adatkezelési tájékoztató mintaszöveg alapján',
                            'jogi_szoveg_sajat_jogasz' => 'A jogi szövegeket saját jogászunk készíti',
                        ],
                    ],
                ],
            ],
            [
                'title' => '9. Üzemeltetés és karbantartás',
                'fields' => [
                    [
                        'key' => 'tartalom_szerkeszto', 'label' => 'Ki szerkeszti majd a tartalmat?', 'type' => 'radio',
                        'options' => [
                            'sajat_maguk_betanitas' => 'Saját magunk, ehhez kezelőfelületet és betanítást kérünk',
                            'keszito_havidijas' => 'A készítő, havi díjas megbízással',
                            'keszito_eseti' => 'A készítő, eseti megrendelés alapján',
                        ],
                    ],
                    [
                        'key' => 'folyamatos_szolgaltatas', 'label' => 'Milyen folyamatos szolgáltatást kér?', 'type' => 'checkbox',
                        'options' => [
                            'tarhely_domain_kezelese' => 'Tárhely és domain kezelése',
                            'biztonsagi_mentes' => 'Biztonsági mentés és frissítések',
                            'havi_tartalmi_modositas' => 'Havi néhány tartalmi módosítás',
                            'latogatottsagi_statisztika' => 'Látogatottsági statisztika, időszakos jelentés',
                            'google_cegprofil_gondozasa' => 'Google Cégprofil gondozása',
                            'nem_kerek' => 'Nem kérek folyamatos szolgáltatást',
                        ],
                    ],
                ],
            ],
            [
                'title' => '10. Ütemezés és költségkeret',
                'fields' => [
                    [
                        'key' => 'hatarido', 'label' => 'Mikorra szeretné az elkészült weboldalt?', 'type' => 'radio',
                        'options' => [
                            'egy_honapon_belul' => 'Egy hónapon belül',
                            'egy_ket_honapon_belul' => '1–2 hónapon belül',
                            'nincs_szoros_hatarido' => 'Nincs szoros határidő',
                            'konkret_datum' => 'Konkrét dátum',
                        ],
                    ],
                    ['key' => 'hatarido_datum', 'label' => 'Konkrét dátum', 'type' => 'date'],
                    [
                        'key' => 'koltsegkeret', 'label' => 'Mekkora egyszeri költségkerettel számol?', 'type' => 'radio',
                        'options' => [
                            'alatt_100e' => '100 000 Ft alatt',
                            '100_200e' => '100 000–200 000 Ft',
                            '200_350e' => '200 000–350 000 Ft',
                            'felett_350e' => '350 000 Ft felett',
                            'ajanlatot_kerek' => 'Ajánlatot kérek, utána döntök',
                        ],
                    ],
                    ['key' => 'dontesi_jogkor', 'label' => 'Ki dönt a megrendelésről és hagyja jóvá az elkészült munkát?', 'type' => 'text'],
                    ['key' => 'egyeb_megjegyzes', 'label' => 'Egyéb megjegyzés, kérés', 'type' => 'textarea'],
                    ['key' => 'kitolto_neve', 'label' => 'Kitöltő neve', 'type' => 'text'],
                ],
            ],
        ];
    }

    /** Minden mező lapos listája (szekció nélkül) — validáláshoz és admin-kiíráshoz. */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::sections() as $section) {
            foreach ($section['fields'] as $field) {
                $fields[$field['key']] = $field;
            }
        }

        return $fields;
    }
}
