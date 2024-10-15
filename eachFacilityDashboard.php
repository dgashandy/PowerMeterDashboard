<?php

include 'koneksiPowerMeter_starSchema.php';

$tanggalYMD = isset($_GET['tanggalYMD']) ? $_GET['tanggalYMD'] : date('Y-m-d'); // Default to today's date if not provided

$categories = [
    'Air Conditioner',
    'Air Compressor',
    'Vacuum',
    'Cooling Tower' // This will use the new query
];

$resultsByCategory = [];

// Loop through categories to run queries and fetch results
foreach ($categories as $category) {
    if ($category === 'Cooling Tower') {
        $query = "
            SELECT 
                dd.TanggalYMD,
                pf.DateID,
                pf.TimeID,
                'DB Compressor New / Cooling Tower' AS DeviceName, 
                td.WaktuHMS AS Hour,
                (SUM(CASE 
                        WHEN d.Category = 'Cooling Tower' THEN pf.SubskWh 
                        ELSE 0 
                    END) / 1000) - 
            (SUM(CASE 
                    WHEN d.DeviceName = 'Air comp 1' THEN pf.SubskWh 
                    ELSE 0 
                END) / 1000) AS TotalSubskWh
            FROM 
                powermeterfact pf
            INNER JOIN 
                datedimension dd ON pf.DateID = dd.DateID
            INNER JOIN 
                timedimension td ON pf.TimeID = td.TimeID
            INNER JOIN 
                devicedimension d ON pf.DeviceID = d.DeviceID
            WHERE 
                dd.TanggalYMD = :tanggalYMD
            GROUP BY 
                dd.TanggalYMD, pf.DateID, pf.TimeID, td.WaktuHMS
            ORDER BY 
                pf.TimeID";
    } else {
        $query = "
            SELECT 
                dd.TanggalYMD,
                pf.DateID,
                pf.TimeID,
                td.WaktuHMS AS Hour,
                d.DeviceName,
                (SUM(pf.SubskWh) / 1000) AS TotalSubskWh
            FROM 
                powermeterfact pf
            INNER JOIN 
                datedimension dd ON pf.DateID = dd.DateID
            INNER JOIN 
                timedimension td ON pf.TimeID = td.TimeID
            INNER JOIN 
                devicedimension d ON pf.DeviceID = d.DeviceID
            WHERE 
                dd.TanggalYMD = :tanggalYMD
                AND d.Category = :category
            GROUP BY 
                dd.TanggalYMD, pf.DateID, pf.TimeID, td.WaktuHMS, d.DeviceName
            ORDER BY 
                pf.TimeID, d.DeviceName";
    }

    $stmt = $pdo->prepare($query);
    $params = ($category === 'Cooling Tower') ? ['tanggalYMD' => $tanggalYMD] : ['tanggalYMD' => $tanggalYMD, 'category' => $category];
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $datasets = [];
    $totalPerHour = [];

    foreach ($results as $row) {
        if (!in_array($row['Hour'], $labels)) {
            $labels[] = $row['Hour'];
        }

        if (!isset($datasets[$row['DeviceName']])) {
            $datasets[$row['DeviceName']] = [];
        }
        $datasets[$row['DeviceName']][] = $row['TotalSubskWh'];

        if (!isset($totalPerHour[$row['Hour']])) {
            $totalPerHour[$row['Hour']] = 0;
        }
        $totalPerHour[$row['Hour']] += $row['TotalSubskWh'];
    }

    $resultsByCategory[$category] = [
        'labels' => $labels,
        'datasets' => $datasets,
        'totalPerHour' => $totalPerHour
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Energy Consumption Graphs</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
        }
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            margin-bottom: -50px;
        }
        .form-container {
            display: flex;
            position: absolute;
            left: 0;
            transform: translateY(-50%);
        }
        .title {
            height: auto;
            margin-bottom: 20px;
        }
        .chart-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
        }
        .chart-item {
            border: 1px solid #000000;
            padding: 10px;
            box-sizing: border-box;
            flex: 1 1 40%;
            margin: 10px;
        }
        h3 {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
        canvas {
            width: 100% !important;
            height: 400px !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <form method="GET" action="">
                <label for="tanggalYMD">Select Date:</label>
                <input type="date" id="tanggalYMD" name="tanggalYMD" value="<?php echo htmlspecialchars($tanggalYMD); ?>">
                <button type="submit">Submit</button>
            </form>
        </div>
        <div class="title">
            <h1>FACILITY POWER METER HOURLY</h1>
        </div>
    </div>
    <div class="chart-container">
        <?php foreach ($categories as $category): ?>
            <div class="chart-item">
                <h3><?php echo $category; ?></h3>
                <canvas id="chart-<?php echo strtolower(str_replace(' ', '-', $category)); ?>"></canvas>
                <script>
                    const ctx_<?php echo strtolower(str_replace(' ', '_', $category)); ?> = document.getElementById('chart-<?php echo strtolower(str_replace(' ', '-', $category)); ?>').getContext('2d');
                    const labels_<?php echo strtolower(str_replace(' ', '_', $category)); ?> = <?php echo json_encode($resultsByCategory[$category]['labels']); ?>;

                    const lineDatasets_<?php echo strtolower(str_replace(' ', '_', $category)); ?> = [];
                    <?php if ($category !== 'Cooling Tower'): ?>
                        <?php foreach ($resultsByCategory[$category]['datasets'] as $deviceName => $data): ?>
                            lineDatasets_<?php echo strtolower(str_replace(' ', '_', $category)); ?>.push({
                                label: '<?php echo $deviceName; ?>',
                                data: <?php echo json_encode($data); ?>,
                                fill: false,
                                borderColor: '<?php echo sprintf("#%06X", mt_rand(0, 0xFFFFFF)); ?>',  // Generate random color
                                tension: 0.1,
                                type: 'line'
                            });
                        <?php endforeach; ?>
                    <?php else: ?>
                        const lineDataset_<?php echo strtolower(str_replace(' ', '_', $category)); ?> = {
                            label: 'DB Compressor New / Cooling Tower',
                            data: <?php echo json_encode($resultsByCategory[$category]['datasets']['DB Compressor New / Cooling Tower']); ?>,
                            fill: false,
                            borderColor: '<?php echo sprintf("#%06X", mt_rand(0, 0xFFFFFF)); ?>',  // Generate random color
                            tension: 0.1,
                            type: 'line'
                        };
                        lineDatasets_<?php echo strtolower(str_replace(' ', '_', $category)); ?>.push(lineDataset_<?php echo strtolower(str_replace(' ', '_', $category)); ?>);
                    <?php endif; ?>

                    const barDataset_<?php echo strtolower(str_replace(' ', '_', $category)); ?> = {
                        label: 'Total <?php echo $category; ?> kWh',
                        data: <?php echo json_encode(array_values($resultsByCategory[$category]['totalPerHour'])); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        type: 'bar',
                        yAxisID: 'y2',
                    };

                    const data_<?php echo strtolower(str_replace(' ', '_', $category)); ?> = {
                        labels: labels_<?php echo strtolower(str_replace(' ', '_', $category)); ?>,
                        datasets: [barDataset_<?php echo strtolower(str_replace(' ', '_', $category)); ?>, ...lineDatasets_<?php echo strtolower(str_replace(' ', '_', $category)); ?>]
                    };

                    const config_<?php echo strtolower(str_replace(' ', '_', $category)); ?> = {
                        type: 'bar',
                        data: data_<?php echo strtolower(str_replace(' ', '_', $category)); ?>,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'kWh'
                                    }
                                },
                                y2: {
                                    beginAtZero: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: ''
                                    },
                                    grid: {
                                        drawOnChartArea: false,
                                    },
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Hour'
                                    }
                                }
                            },
                            plugins: {
                                title: {
                                    display: true,
                                    text: '<?php echo $category; ?> Energy Consumption'
                                }
                            }
                        }
                    };

                    const chart_<?php echo strtolower(str_replace(' ', '_', $category)); ?> = new Chart(ctx_<?php echo strtolower(str_replace(' ', '_', $category)); ?>, config_<?php echo strtolower(str_replace(' ', '_', $category)); ?>);
                </script>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
