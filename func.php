<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/kaynak.php';

// "2023" veya "2024-08" anahtarını başlangıç timestamp'ine çevirir; geçersiz/yorum ise null
function girisTarihiniCoz($anahtar) {
    if (str_starts_with($anahtar, '_comment')) {
        return null;
    }
    // sadece yıl ("2023")
    if (preg_match('/^\d{4}$/', $anahtar)) {
        return strtotime($anahtar . '-01-01');
    }
    // yıl-ay ("2024-08" veya "2024-8")
    if (preg_match('/^(\d{4})-(\d{1,2})$/', $anahtar, $m)) {
        return strtotime($m[1] . '-' . sprintf('%02d', $m[2]) . '-01');
    }
    return null;
}

// Belirli tarih için maaş getir
function maasiTarihIleGetir($tarih, $maaslar) {
    $hedef_tarih = strtotime($tarih);
    $uygun_maas = null;
    $en_yakin_tarih = 0;
    foreach ($maaslar as $anahtar => $maas) {
        $giris_tarihi = girisTarihiniCoz($anahtar);
        // Bu giriş hedef tarih için geçerliyse ve en yakın tarihse
        if ($giris_tarihi && $giris_tarihi <= $hedef_tarih && $giris_tarihi > $en_yakin_tarih) {
            $en_yakin_tarih = $giris_tarihi;
            $uygun_maas = $maas;
        }
    }
    return $uygun_maas;
}

// Belirli tarih için asgari ücreti getir
function asgariUcretGetir($tarih, $asgari_ucretler) {
    $hedef_tarih = strtotime($tarih);
    $uygun_ucret = null;
    $en_yakin_tarih = 0;

    foreach ($asgari_ucretler as $anahtar => $ucret) {
        $giris_tarihi = girisTarihiniCoz($anahtar);
        if ($giris_tarihi && $giris_tarihi <= $hedef_tarih && $giris_tarihi > $en_yakin_tarih) {
            $en_yakin_tarih = $giris_tarihi;
            $uygun_ucret = $ucret;
        }
    }
    return $uygun_ucret;
}

// Maaş verilerindeki yılları getir
function yillariMaaslardanGetir($maaslar) {
    $yillar = [];
    foreach ($maaslar as $anahtar => $maas) {
        $giris_tarihi = girisTarihiniCoz($anahtar);
        if ($giris_tarihi) {
            $yillar[] = (int) date('Y', $giris_tarihi);
        }
    }
    return array_unique($yillar);
}

// İş günü kontrolü
function isGunuMu($tarih) {
    $haftanin_gunu = date('N', strtotime($tarih));
    return $haftanin_gunu >= 1 && $haftanin_gunu <= 5;
}

// Ayın ilk iş gününü getir
function ilkIsgununuGetir($yil, $ay) {
    $tarih = sprintf('%d-%02d-01', $yil, $ay);
    while (!isGunuMu($tarih)) {
        $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'));
    }
    return $tarih;
}

// Yıllık verileri hesapla
function yillikVerileriHesapla($maaslar, &$doviz_kurlari, $doviz_kur_dosyasi, $log_dosyasi) {
    $yillar = yillariMaaslardanGetir($maaslar);
    $baslangic_yili = min($yillar);
    $su_anki_yil = date('Y');
    $bitis_yili = max($su_anki_yil, max($yillar));
    $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $sonuclar = [];
    $getirilecek_tarihler = [];
    // Pre-fetch için tarihleri hazırla
    for ($yil = $baslangic_yili; $yil <= $bitis_yili; $yil++) {
        $ay_limiti = ($yil == $su_anki_yil) ? date('n') : 12;
        for ($ay = 1; $ay <= $ay_limiti; $ay++) {
            $tarih = ilkIsgununuGetir($yil, $ay);
            if (!isset($doviz_kurlari[$tarih]) || $doviz_kurlari[$tarih] === 'Veri yok') {
                $getirilecek_tarihler[] = $tarih;
            }
        }
    }
    // Toplu döviz kuru getir
    if (!empty($getirilecek_tarihler)) {
        topluDovizKuruGetir($getirilecek_tarihler, $doviz_kurlari, $doviz_kur_dosyasi, $log_dosyasi);
    }
    // Hesapla
    for ($yil = $baslangic_yili; $yil <= $bitis_yili; $yil++) {
        $aylik_usd = [];
        $ay_limiti = ($yil == $su_anki_yil) ? date('n') : 12;
        for ($ay = 1; $ay <= $ay_limiti; $ay++) {
            $tarih = ilkIsgununuGetir($yil, $ay);
            $maas = maasiTarihIleGetir($tarih, $maaslar);
            if ($maas !== null) {
                $kur = $doviz_kurlari[$tarih] ?? 'Veri yok';
                if ($kur !== 'Veri yok' && $kur > 0) {
                    $usd = $maas / $kur;
                    $aylik_usd[$aylar[$ay - 1]] = number_format($usd, 2);
                } else {
                    $aylik_usd[$aylar[$ay - 1]] = 'Veri yok';
                }
            } else {
                $aylik_usd[$aylar[$ay - 1]] = 'Maaş tanımı yok';
            }
        }
        if (!empty($aylik_usd)) {
            $gecerli_usd = array_filter($aylik_usd, function($v) { 
                return $v !== 'Veri yok' && $v !== 'Maaş tanımı yok'; 
            });
            $sayisal_degerler = array_map(function($v) { 
                return floatval(str_replace(',', '', $v)); 
            }, $gecerli_usd);
            $sonuclar[$yil] = [
                'average' => !empty($sayisal_degerler) ? number_format(array_sum($sayisal_degerler) / count($sayisal_degerler), 2) : 'Veri yok',
                'monthly' => $aylik_usd
            ];
        }
    }
    return $sonuclar;
}

// Grafik verilerini hazırla
function grafikVerileriniHazirla($maaslar, $doviz_kurlari, $enf_oranlari, $asgari_ucretler) {
    $grafik_etiketleri = [];
    $grafik_verileri = [];
    $ortalama_verileri = [];
    $enf_duzeltilmis_maas_verileri = [];
    $enf_duzeltilmis_ortalama_verileri = [];
    $asgari_ucret_oran_verileri = [];
    $asgari_ucret_ortalama_verileri = [];
    $nominal_maas_verileri = [];
    $asgari_ucret_miktar_verileri = [];
    
    $aylar_kisa = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    $yillar = yillariMaaslardanGetir($maaslar);
    $baslangic_yili = min($yillar);
    $su_anki_yil = date('Y');
    $bitis_yili = max($su_anki_yil, max($yillar));
    
    $birikimli_toplam = 0;
    $ay_sayisi = 0;
    
    $enf_duzeltilmis_maas = 100; // İlk ay maaş 100 TL (2022-07)
    $enf_birikimli_toplam = 0;
    $enf_ay_sayisi = 0;

    $asgari_ucret_birikimli_toplam = 0;
    $asgari_ay_sayisi = 0;

    // İlk maaş tarihini (timestamp) ve nominal maaşı belirle
    $ilk_maas_tarihi = null;
    $ilk_maas = null;
    foreach ($maaslar as $anahtar => $maas) {
        $giris_tarihi = girisTarihiniCoz($anahtar);
        if ($giris_tarihi && ($ilk_maas_tarihi === null || $giris_tarihi < $ilk_maas_tarihi)) {
            $ilk_maas_tarihi = $giris_tarihi;
            $ilk_maas = $maas;
        }
    }
    $temel_enflasyon = 1; // Birikimli enflasyon oranı (başlangıçta 1)

    for ($yil = $baslangic_yili; $yil <= $bitis_yili; $yil++) {
        $ay_limiti = ($yil == $su_anki_yil) ? date('n') : 12;
        for ($ay = 1; $ay <= $ay_limiti; $ay++) {
            $tarih = ilkIsgununuGetir($yil, $ay);
            $nominal_maas = maasiTarihIleGetir($tarih, $maaslar);
            if ($nominal_maas !== null && strtotime($tarih) >= $ilk_maas_tarihi) {
                $kur = $doviz_kurlari[$tarih] ?? null;
                if ($kur !== null && $kur !== 'Veri yok' && $kur > 0) {
                    // USD Hesabı
                    $usd = $nominal_maas / $kur;
                    $birikimli_toplam += $usd;
                    $ay_sayisi++;
                    $grafik_etiketleri[] = $aylar_kisa[$ay - 1] . ' ' . $yil;
                    $grafik_verileri[] = round($usd, 2);
                    $ortalama_verileri[] = round($birikimli_toplam / $ay_sayisi, 2);

                    // Enflasyon düzeltilmiş maaş hesaplama
                    $enf_anahtari = sprintf('%d-%02d', $yil, $ay);
                    if (strtotime($tarih) === $ilk_maas_tarihi) {
                        $enf_duzeltilmis_maas = 100; // Başlangıç ayında 100 TL
                        $temel_enflasyon = 1; // Enflasyonu sıfırla
                    } else {
                        // O ayın enflasyonu varsa birikimli enflasyona ekle (yoksa o ay %0 sayılır)
                        if (isset($enf_oranlari[$enf_anahtari])) {
                            $aylik_enf = $enf_oranlari[$enf_anahtari] / 100; // Yüzdeyi ondalığa çevir
                            $temel_enflasyon *= (1 + $aylik_enf); // Birikimli enflasyon
                        }
                        // Reel maaş her ay yeniden hesaplanır (zam + güncel birikimli enflasyon)
                        $maas_orani = ($ilk_maas > 0) ? ($nominal_maas / $ilk_maas) : 0;
                        $enf_duzeltilmis_maas = (100 / $temel_enflasyon) * $maas_orani;
                    }
                    $enf_duzeltilmis_maas_verileri[] = round($enf_duzeltilmis_maas, 2);
                    $enf_birikimli_toplam += $enf_duzeltilmis_maas;
                    $enf_ay_sayisi++;
                    $enf_duzeltilmis_ortalama_verileri[] = round($enf_birikimli_toplam / $enf_ay_sayisi, 2);

                    // Asgari Ücret Oran Hesabı
                    $asgari_ucret = asgariUcretGetir($tarih, $asgari_ucretler);
                    $nominal_maas_verileri[] = $nominal_maas;
                    if ($asgari_ucret && $asgari_ucret > 0) {
                        $oran = $nominal_maas / $asgari_ucret;
                        $asgari_ucret_oran_verileri[] = round($oran, 2);
                        $asgari_ucret_birikimli_toplam += $oran;
                        $asgari_ay_sayisi++;
                        $asgari_ucret_ortalama_verileri[] = round($asgari_ucret_birikimli_toplam / $asgari_ay_sayisi, 2);
                        $asgari_ucret_miktar_verileri[] = $asgari_ucret;
                    } else {
                        $asgari_ucret_oran_verileri[] = null;
                        $asgari_ucret_ortalama_verileri[] = null;
                        $asgari_ucret_miktar_verileri[] = null;
                    }
                }
            }
        }
    }    
    return [
        'labels' => $grafik_etiketleri,
        'salary_data' => $grafik_verileri,
        'average_data' => $ortalama_verileri,
        'inflation_adjusted_salary_data' => $enf_duzeltilmis_maas_verileri,
        'inflation_adjusted_average_data' => $enf_duzeltilmis_ortalama_verileri,
        'minimum_wage_ratio_data' => $asgari_ucret_oran_verileri,
        'minimum_wage_average_data' => $asgari_ucret_ortalama_verileri,
        'nominal_salary_data' => $nominal_maas_verileri,
        'minimum_wage_data' => $asgari_ucret_miktar_verileri
    ];
}
