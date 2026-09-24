<?php

return [

    // Ennyi másodpercig számít "online"-nak egy eszköz az utolsó sikeres
    // push óta (Device::getIsOnlineAttribute()/scopeOnline(), DevicesStatusTable).
    'online_timeout' => env('DEVICES_ONLINE_TIMEOUT', 60),

];
