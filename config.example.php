<?php
// Örnek ayar dosyası. Kopyalayıp "config.php" olarak kaydedin ve anahtarınızı girin.
// EVDS API anahtarı (ücretsiz): https://evds3.tcmb.gov.tr -> Profilim -> API Key Kopyala
return [
    'evds_api_key' => '',
    // TÜFE Genel Endeks serisi (aylık % değişim). 2003=100 serisi durdurulursa
    // 2025=100 için 'TP.TUKFIY2025.GENEL' ile değiştirilebilir.
    'evds_series'  => 'TP.GENENDEKS.T1',
];
