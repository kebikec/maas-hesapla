<?php
require_once __DIR__ . '/func.php';
// Verileri hesapla
$sonuclar = yillikVerileriHesapla($maaslar, $doviz_kurlari, $enf_orani, $doviz_kur_dosyasi, $log_dosyasi);
$grafikVerileri = grafikVerileriniHazirla($maaslar, $doviz_kurlari, $enf_orani);
// Aylar için türkçe array
$aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Yıllık Ortalama Maaş (USD) ve Enflasyon Düzeltilmiş Maaş (TL)</title>
<style>
body {font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5;}
h3 {color : #333; border-bottom : 2px solid #036de1; padding-bottom : 5px;}
.container {max-width: 1000px; margin: auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
.results {margin-top: 20px;}
.year-row {display: flex; justify-content: space-between; margin-bottom: 20px;}
.year-column {flex: 1; margin-right: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;}
.year-column:last-child {margin-right: 0;}
.hata {color:#ec0505;}
.ortalama {font-weight: bold; color: #036de1; font-size: 1.1em;}
.maas-bilgi {margin-bottom: 15px; padding: 10px; background-color: #ddd; border-radius: 5px;}
.maas-bilgi h4 {margin: 0 0 10px 0; color:#464c53;}
</style></head>
<body>
    <div class="container">
        <h2>Yıllık Ortalama Maaş (USD) ve Enflasyon Düzeltilmiş Maaş (TL)</h2>        
        <!-- Maaş Bilgileri Section -->
        <div class="maas-bilgi">
            <h4>Maaş Bilgileri:</h4>
            <?php
            foreach ($maaslar as $anahtar => $maas) {
                if (strpos($anahtar, '_comment') === 0) continue;                
                if (preg_match('/^\d{4}$/', $anahtar)) {
                    echo "<p><strong>$anahtar:</strong> " . number_format($maas) . " TL (Tüm yıl)</p>";
                } elseif (preg_match('/^(\d{4})-(\d{2})$/', $anahtar, $eslesenler)) {
                    $yil = $eslesenler[1];
                    $ay = $eslesenler[2];
                    $ay_adi = $aylar[intval($ay) - 1];
                    echo "<p><strong>$yil $ay_adi:</strong> " . number_format($maas) . " TL (Bu tarihten itibaren)</p>";
                }
            }
            ?>
        </div>        
        <?php if (isset($sonuclar) && !empty($sonuclar)): ?>
            <div class="results">
                <?php
                $sonuc_yillari = array_keys($sonuclar);
                for ($i = 0; $i < count($sonuc_yillari); $i += 2) {
                    echo '<div class="year-row">';
                    $yil1 = $sonuc_yillari[$i];
                    echo '<div class="year-column">';
                    echo '<h3>' . $yil1 . ' Yılı</h3>';
                    echo '<p class="ortalama">Ortalama Aylık Maaş: ' . $sonuclar[$yil1]['average'] . ' $</p>';
                    foreach ($sonuclar[$yil1]['monthly'] as $ay => $usd) {
                        $sinif = 'success';
                        if ($usd === 'Veri yok') $sinif = 'error';
                        elseif ($usd === 'Maaş tanımı yok') $sinif = 'warning';
                        echo '<p class="' . $sinif . '">' . $ay . ': ' . $usd . ' $</p>';
                    }
                    echo '</div>';                    
                    if (isset($sonuc_yillari[$i + 1])) {
                        $yil2 = $sonuc_yillari[$i + 1];
                        echo '<div class="year-column">';
                        echo '<h3>' . $yil2 . ' Yılı</h3>';
                        echo '<p class="ortalama">Ortalama Aylık Maaş: ' . $sonuclar[$yil2]['average'] . ' $</p>';
                        foreach ($sonuclar[$yil2]['monthly'] as $ay => $usd) {
                            $sinif = 'success';
                            if ($usd === 'Veri yok') $sinif = 'error';
                            elseif ($usd === 'Maaş tanımı yok') $sinif = 'warning';
                            echo '<p class="' . $sinif . '">' . $ay . ': ' . $usd . ' $</p>';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        <?php else: ?>
            <p class="hata">Sonuç bulunamadı. Lütfen maas.json dosyasını kontrol edin.</p>
        <?php endif; ?>
        <!-- Chart Section (USD) -->
        <div style="margin-top: 40px; padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
            <h3 style="text-align: center; color: #333; margin-bottom: 20px;">Aylık Maaş Değişimi ve Kümülatif Ortalama (USD)</h3>
            <div style="position: relative; height: 400px;">
                <canvas id="salaryChart"></canvas>
            </div>
        </div>
        <!-- Chart Section (Enflasyon TL) -->
        <div style="margin-top: 40px; padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
            <h3 style="text-align: center; color: #333; margin-bottom: 20px;">Enflasyon Düzeltilmiş Aylık Maaş Değişimi ve Kümülatif Ortalama (TL)</h3>
            <div style="position: relative; height: 400px;">
                <canvas id="inflationAdjustedChart"></canvas>
            </div>
        </div>        
        <div style="margin-top: 30px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
          <small>Son güncelleme: <?php echo date('Y-m-d H:i:s'); ?></small>
        </div>
    </div>
    <script src="chart.min.js"></script> <!-- chartjs.org Chart.js v3.9.1 -->   
<script>
document.addEventListener('DOMContentLoaded', function() {
	// USD Chart
	const ctx = document.getElementById('salaryChart').getContext('2d');
	const salaryChart = new Chart(ctx, {
		type: 'line',
		data: {
			labels: <?= json_encode($grafikVerileri['labels']) ?>,
			datasets: [{
				label: 'Aylık Maaş (USD)',
				data: <?= json_encode($grafikVerileri['salary_data']) ?>,
				borderColor: '#28a745',
				backgroundColor: 'rgba(40, 167, 69, 0.1)',
				borderWidth: 3,
				fill: false,
				tension: 0.3,
				pointBackgroundColor: '#28a745',
				pointBorderColor: '#fff',
				pointBorderWidth: 2,
				pointRadius: 5,
				pointHoverRadius: 8,
				pointHoverBackgroundColor: '#28a745',
				pointHoverBorderColor: '#fff',
				pointHoverBorderWidth: 3
			}, {
				label: 'Kümülatif Ortalama Maaş (USD)',
				data: <?= json_encode($grafikVerileri['average_data']) ?>,
				borderColor: '#007bff',
				backgroundColor: 'rgba(0, 123, 255, 0.1)',
				borderWidth: 2,
				fill: false,
				tension: 0.1,
				pointBackgroundColor: '#007bff',
				pointBorderColor: '#fff',
				pointBorderWidth: 2,
				pointRadius: 4,
				pointHoverRadius: 6,
				pointHoverBackgroundColor: '#007bff',
				pointHoverBorderColor: '#fff',
				pointHoverBorderWidth: 2,
				borderDash: [5, 5]
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: {
					display: true,
					position: 'top',
					labels: {
						font: {
							size: 14,
							weight: 'bold'
						},
						color: '#333',
						padding: 20,
						usePointStyle: true
					}
				},
				tooltip: {
					mode: 'index',
					intersect: false,
					backgroundColor: 'rgba(0, 0, 0, 0.9)',
					titleColor: '#fff',
					bodyColor: '#fff',
					borderColor: '#28a745',
					borderWidth: 2,
					cornerRadius: 8,
					displayColors: true,
					callbacks: {
						title: function(context) {
							return context[0].label;
						},
						label: function(context) {
							if (context.datasetIndex === 0) {
								return 'Aylık Maaş: $' + context.parsed.y.toLocaleString('en-US', {
									minimumFractionDigits: 2,
									maximumFractionDigits: 2
								});
							} else {
								return 'Kümülatif Ortalama: $' + context.parsed.y.toLocaleString('en-US', {
									minimumFractionDigits: 2,
									maximumFractionDigits: 2
								});
							}
						}
					}
				}
			},
			scales: {
				x: {
					display: true,
					title: {
						display: true,
						text: 'Dönem',
						font: {
							size: 16,
							weight: 'bold'
						},
						color: '#333'
					},
					ticks: {
						color: '#666',
						font: {
							size: 12
						},
						maxRotation: 45,
						minRotation: 45
					},
					grid: {
						color: 'rgba(0, 0, 0, 0.1)',
						drawBorder: false
					}
				},
				y: {
					display: true,
					title: {
						display: true,
						text: 'Maaş (USD)',
						font: {
							size: 16,
							weight: 'bold'
						},
						color: '#333'
					},
					ticks: {
						color: '#666',
						font: {
							size: 12
						},
						callback: function(value) {
							return '$' + value.toLocaleString('en-US', {
								minimumFractionDigits: 0,
								maximumFractionDigits: 0
							});
						}
					},
					grid: {
						color: 'rgba(0, 0, 0, 0.1)',
						drawBorder: false
					},
					beginAtZero: false
				}
			},
			interaction: {
				mode: 'nearest',
				axis: 'x',
				intersect: false
			},
			animation: {
				duration: 1500,
				easing: 'easeInOutQuart'
			}
		}
	});
	// TL Enf Chart
	const ctxInflation = document.getElementById('inflationAdjustedChart').getContext('2d');
	const inflationAdjustedChart = new Chart(ctxInflation, {
		type: 'line',
		data: {
			labels: <?= json_encode($grafikVerileri['labels']) ?>,
			datasets: [{
				label: 'Enflasyon Düzeltilmiş Aylık Maaş (TL)',
				data: <?= json_encode($grafikVerileri['inflation_adjusted_salary_data']) ?>,
				borderColor: '#dc3545',
				backgroundColor: 'rgba(220, 53, 69, 0.1)',
				borderWidth: 3,
				fill: false,
				tension: 0.3,
				pointBackgroundColor: '#dc3545',
				pointBorderColor: '#fff',
				pointBorderWidth: 2,
				pointRadius: 5,
				pointHoverRadius: 8,
				pointHoverBackgroundColor: '#dc3545',
				pointHoverBorderColor: '#fff',
				pointHoverBorderWidth: 3
			}, {
				label: 'Kümülatif Ortalama Maaş (TL)',
				data: <?= json_encode($grafikVerileri['inflation_adjusted_average_data']) ?>,
				borderColor: '#6f42c1',
				backgroundColor: 'rgba(111, 66, 193, 0.1)',
				borderWidth: 2,
				fill: false,
				tension: 0.1,
				pointBackgroundColor: '#6f42c1',
				pointBorderColor: '#fff',
				pointBorderWidth: 2,
				pointRadius: 4,
				pointHoverRadius: 6,
				pointHoverBackgroundColor: '#6f42c1',
				pointHoverBorderColor: '#fff',
				pointHoverBorderWidth: 2,
				borderDash: [5, 5]
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: {
					display: true,
					position: 'top',
					labels: {
						font: {
							size: 14,
							weight: 'bold'
						},
						color: '#333',
						padding: 20,
						usePointStyle: true
					}
				},
				tooltip: {
					mode: 'index',
					intersect: false,
					backgroundColor: 'rgba(0, 0, 0, 0.9)',
					titleColor: '#fff',
					bodyColor: '#fff',
					borderColor: '#dc3545',
					borderWidth: 2,
					cornerRadius: 8,
					displayColors: true,
					callbacks: {
						title: function(context) {
							return context[0].label;
						},
						label: function(context) {
							if (context.datasetIndex === 0) {
								return 'Aylık Maaş: ' + context.parsed.y.toLocaleString('tr-TR', {
									minimumFractionDigits: 2,
									maximumFractionDigits: 2
								}) + ' TL';
							} else {
								return 'Kümülatif Ortalama: ' + context.parsed.y.toLocaleString('tr-TR', {
									minimumFractionDigits: 2,
									maximumFractionDigits: 2
								}) + ' TL';
							}
						}
					}
				}
			},
			scales: {
				x: {
					display: true,
					title: {
						display: true,
						text: 'Dönem',
						font: {
							size: 16,
							weight: 'bold'
						},
						color: '#333'
					},
					ticks: {
						color: '#666',
						font: {
							size: 12
						},
						maxRotation: 45,
						minRotation: 45
					},
					grid: {
						color: 'rgba(0, 0, 0, 0.1)',
						drawBorder: false
					}
				},
				y: {
					display: true,
					title: {
						display: true,
						text: 'Maaş (TL)',
						font: {
							size: 16,
							weight: 'bold'
						},
						color: '#333'
					},
					ticks: {
						color: '#666',
						font: {
							size: 12
						},
						callback: function(value) {
							return value.toLocaleString('tr-TR', {
								minimumFractionDigits: 0,
								maximumFractionDigits: 0
							}) + ' TL';
						}
					},
					grid: {
						color: 'rgba(0, 0, 0, 0.1)',
						drawBorder: false
					},
					beginAtZero: false
				}
			},
			interaction: {
				mode: 'nearest',
				axis: 'x',
				intersect: false
			},
			animation: {
				duration: 1500,
				easing: 'easeInOutQuart'
			}
		}
	});
});
</script></body></html>