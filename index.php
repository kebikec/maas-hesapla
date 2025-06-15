<?php
require_once __DIR__ . '/func.php';
// Verileri hesapla
$results = calculateYearlyData($maaslar, $exchange_rates, $enf_orani, $doviz_kur_dosyasi, $log_file);
$chartData = prepareChartData($maaslar, $exchange_rates, $enf_orani);
// Aylar için türkçe array
$months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
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
            foreach ($maaslar as $key => $salary) {
                if (strpos($key, '_comment') === 0) continue;                
                if (preg_match('/^\d{4}$/', $key)) {
                    echo "<p><strong>$key:</strong> " . number_format($salary) . " TL (Tüm yıl)</p>";
                } elseif (preg_match('/^(\d{4})-(\d{2})$/', $key, $matches)) {
                    $year = $matches[1];
                    $month = $matches[2];
                    $month_name = $months[intval($month) - 1];
                    echo "<p><strong>$year $month_name:</strong> " . number_format($salary) . " TL (Bu tarihten itibaren)</p>";
                }
            }
            ?>
        </div>        
        <?php if (isset($results) && !empty($results)): ?>
            <div class="results">
                <?php
                $result_years = array_keys($results);
                for ($i = 0; $i < count($result_years); $i += 2) {
                    echo '<div class="year-row">';
                    $year1 = $result_years[$i];
                    echo '<div class="year-column">';
                    echo '<h3>' . $year1 . ' Yılı</h3>';
                    echo '<p class="ortalama">Ortalama Aylık Maaş: ' . $results[$year1]['average'] . ' $</p>';
                    foreach ($results[$year1]['monthly'] as $month => $usd) {
                        $class = 'success';
                        if ($usd === 'Veri yok') $class = 'error';
                        elseif ($usd === 'Maaş tanımı yok') $class = 'warning';
                        echo '<p class="' . $class . '">' . $month . ': ' . $usd . ' $</p>';
                    }
                    echo '</div>';                    
                    if (isset($result_years[$i + 1])) {
                        $year2 = $result_years[$i + 1];
                        echo '<div class="year-column">';
                        echo '<h3>' . $year2 . ' Yılı</h3>';
                        echo '<p class="ortalama">Ortalama Aylık Maaş: ' . $results[$year2]['average'] . ' $</p>';
                        foreach ($results[$year2]['monthly'] as $month => $usd) {
                            $class = 'success';
                            if ($usd === 'Veri yok') $class = 'error';
                            elseif ($usd === 'Maaş tanımı yok') $class = 'warning';
                            echo '<p class="' . $class . '">' . $month . ': ' . $usd . ' $</p>';
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
			labels: <?= json_encode($chartData['labels']) ?>,
			datasets: [{
				label: 'Aylık Maaş (USD)',
				data: <?= json_encode($chartData['salary_data']) ?>,
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
				data: <?= json_encode($chartData['average_data']) ?>,
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
			labels: <?= json_encode($chartData['labels']) ?>,
			datasets: [{
				label: 'Enflasyon Düzeltilmiş Aylık Maaş (TL)',
				data: <?= json_encode($chartData['inflation_adjusted_salary_data']) ?>,
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
				data: <?= json_encode($chartData['inflation_adjusted_average_data']) ?>,
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