# Archívum

Ez a mappa nem futó, nem autoloadolt kód – korábbi `.bak`/`copy` fájlok és
egy elhagyott service-verzió lett ide áthelyezve törlés helyett, hogy a
git history megmaradjon.

## Miért kellett kiszedni az `app/` alól

Több fájl ugyanazt az osztálynevet és namespace-t deklarálta, mint egy élő,
használt fájl. Amíg az `app/` alatt voltak, ez classmap-ütközést
okozhatott (`composer dump-autoload -o` / `--classmap-authoritative` esetén
nem determinisztikus, hogy melyik definíció "nyer"):

- `app/Http/Controllers/Api/DevicePulseController copy.bak` –
  `App\Http\Controllers\Api\DevicePulseController` duplikátuma.
- `app/Http/JumpCodeController2.php.bak` –
  `App\Http\Controllers\JumpCodeController` duplikátuma.
- `app/Services/JumpCodeGeneratorV4.php` – ténylegesen a
  `class JumpCodeGeneratorV2` nevet deklarálta (fájlnév és osztálynév nem
  egyezett), így ütközött a valódi `JumpCodeGeneratorV2.php`-vel. Emellett
  sehol nem volt rá hivatkozás a kódban – holt kód volt.

## Ami nem ütközési kockázat, csak holt kód

- `app/Filament/User.bak/*` – más namespace-ben van
  (`App\Filament\User\...`), de az `UserPanelProvider` a
  `Filament/Resources` (nem `Filament/User/Resources`) mappából
  discovery-zik resource-okat, tehát ezek sosem töltődtek be.
- `bootstrap/providers.php.bak` – az app `bootstrap/app.php`-ban
  `->withProviders([...])` explicit tömbbel regisztrálja a providereket,
  a `bootstrap/providers.php` fájlt a keretrendszer itt nem olvassa.
- `routes/web copy.bak` – nincs rá `require`/`include` sehol, a
  `routes/web.php` van bekötve a `bootstrap/app.php`-ban.

Ha valamelyikre mégis szükség lenne, itt megvan – csak vissza kell mozgatni
az eredeti helyére és ellenőrizni, hogy nem ütközik-e semmivel.
