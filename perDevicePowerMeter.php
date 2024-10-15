<?php
$host = "10.38.11.3";
$dbname = "star_schemas_powermeter";
$username = "root";
$password = "ida6422690";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initialize variables
    $deviceDetails = null;
    $dates = [];
    $totals = [];
    $viewType = "daily";
    $data = [];
    $columns = [];

    if (
        $_SERVER["REQUEST_METHOD"] === "GET" &&
        isset(
            $_GET["deviceID"],
            $_GET["viewType"],
            $_GET["startDate"],
            $_GET["endDate"]
        )
    ) {
        $deviceID = (int) $_GET["deviceID"];
        $viewType = $_GET["viewType"];
        $startDate = $_GET["startDate"];
        $endDate = $_GET["endDate"];

        $stmt = $pdo->prepare("CALL spShowDeviceDetails(:deviceID)");
        $stmt->bindParam(":deviceID", $deviceID, PDO::PARAM_INT);
        $stmt->execute();
        $deviceDetails = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($viewType === "hourly") {
            $stmt = $pdo->prepare(
                "CALL spGetHourlySubskWHInterval(:deviceID, :startDate, :endDate)"
            );

            $stmt->bindParam(":deviceID", $deviceID, PDO::PARAM_INT);
            $stmt->bindParam(":startDate", $startDate, PDO::PARAM_STR);
            $stmt->bindParam(":endDate", $endDate, PDO::PARAM_STR);

        } elseif ($viewType === "daily") {
            $stmt = $pdo->prepare(
                "CALL spGetDailySubskWHInterval(:deviceID, :startDate, :endDate)"
            );

            $stmt->bindParam(":deviceID", $deviceID, PDO::PARAM_INT);
            $stmt->bindParam(":startDate", $startDate, PDO::PARAM_STR);
            $stmt->bindParam(":endDate", $endDate, PDO::PARAM_STR);
        } elseif ($viewType === "monthly") {
            $startMonth = (int) date("m", strtotime($startDate));
            $endMonth = (int) date("m", strtotime($endDate));

            $stmt = $pdo->prepare("CALL spGetMonthlySubskWHInterval(:deviceID, :startMonth, :endMonth)");
            $stmt->bindParam(':deviceID', $deviceID, PDO::PARAM_INT);
            $stmt->bindParam(':startMonth', $startMonth, PDO::PARAM_INT);
            $stmt->bindParam(':endMonth', $endMonth, PDO::PARAM_INT);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($results) {
            foreach ($results as $row) {
                if ($viewType === "hourly") {
                    $dates[] = $row["Date"] . ' ' . $row["Time"];
                } elseif ($viewType === "daily") {
                    $dates[] = $row["Date"];
                } else {
                    $dates[] = $row["Month"];
                }                
                $totals[] = $row["TotalSubskWH"];
            }
        }

        $query = "CALL spShowRawData(:deviceID, :startDate, :endDate)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(":deviceID", $deviceID, PDO::PARAM_INT);
        $stmt->bindParam(":startDate", $startDate, PDO::PARAM_STR);
        $stmt->bindParam(":endDate", $endDate, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columns = array_keys($data[0] ?? []);
    }
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PerDevice Dashboard Powermeter</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.2/xlsx.full.min.js"></script>
    <style>
            #dataTable thead th {
        color: #d8dde8;
        background-color: #d8dde8;
        padding: 10px;
    }

    #dataTable tbody td {
        color: #d8dde8;
        background-color: #d8dde8;
        padding: 8px;
    }

    #dataTable tbody tr:hover {
        background-color: #d8dde8;
    }

        body {
            font-family: "Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
            padding: 10px 30px 10px 30px;
            background-color: #d8dde8;
            overflow: hidden;
        }

        .container {
            display: flex;
            max-width: 90%;
            margin-left: auto;
            margin-right: auto;
            width: 90%;
            height: auto;
        }

        .chart-container {
            flex: 1;
            margin-right: 20px;
        }

        .details-container {
            flex: 1;
            background-color: #f4f4f4;
            padding: 20px;
            border-radius: 8px;
        }

        .form-container {
            margin-bottom: 20px;
        }

        .form_wrapper {
            display: flex;
            gap: 20px;
        } 

        canvas {
            max-width: 100%;
        }

        .title_wrapper{
            display: flex;
            justify-content: center;
        }

        .title_wrapper h2{
            font-size: 25px;
            color: #5a85e8;
            weight: 100px;
            font-family: Arial, Helvetica, sans-serif;
        }

        .input_group {
            margin-bottom: 1em;
        }

        label {
            margin-bottom: 0.5em;
            display: block;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 0.75em;
            line-height: 1.4;
            background-color: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 3px;
            transition: 0.35s ease-in-out;
        }

        input:focus,
        select:focus {
            outline: 0;
            border-color: #bd8200;
        }
        .input_date_group{
            gap: 50px;
            display: flex;
        }

        input[label="endDate"]{
            margin-left: 20px;
        }

        input[type="submit"] {
            display: flex;
            align-items: center;
            background-color: #4c74ed;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 200px;
            height: 50px;
            margin-left: 30px;
            margin-top: 20px;
        }

        input[type="submit"]:hover {
            background-color: #3b5fbc;
        }

        .all_container{
            width: 95%;
            margin-left: auto;
            margin-right: auto;
            height: auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            border: 1px solid #5982e3;
            box-shadow: 5px 5px 8px rgba(0, 0, 0, 0.3);
        }
        .export_excel{
            margin-top: 18px;
        }

        .export_excel button{
            border-radius: 10px;
            color: white;
            display: flex;
            align-items : center;
            background-color: #0bb329;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
            height: 50px;
            width: 100px;

        }
        
    </style>
</head>
<body>
    <div class='title_wrapper'>
        <h2>EACH DEVICE POWER METER DASHBOARD</h2>
    </div>

    <div class='all_container'>
        <div class="form-container">
            <form class='form_wrapper' method="GET" action="">
                <div class="input_group">
                    <label for="deviceID">Device ID:</label>
                    <select id="deviceID" name="deviceID" required>
                    <option value="1" <?php echo isset($deviceID) &&
                    $deviceID == 1
                        ? "selected"
                        : ""; ?>>MDB AC VRV lot 3</option>
                    <option value="2" <?php echo isset($deviceID) &&
                    $deviceID == 2
                        ? "selected"
                        : ""; ?>>MDB AC VRV lot 2</option>
                    <option value="3" <?php echo isset($deviceID) &&
                    $deviceID == 3
                        ? "selected"
                        : ""; ?>>MSB lot 2</option>
                    <option value="4" <?php echo isset($deviceID) &&
                    $deviceID == 4
                        ? "selected"
                        : ""; ?>>MSB lot 3</option>
                    <option value="5" <?php echo isset($deviceID) &&
                    $deviceID == 5
                        ? "selected"
                        : ""; ?>>DB Vacuum</option>
                    <option value="6" <?php echo isset($deviceID) &&
                    $deviceID == 6
                        ? "selected"
                        : ""; ?>>DB AC 1</option>
                    <option value="7" <?php echo isset($deviceID) &&
                    $deviceID == 7
                        ? "selected"
                        : ""; ?>>DB AC 2</option>
                    <option value="8" <?php echo isset($deviceID) &&
                    $deviceID == 8
                        ? "selected"
                        : ""; ?>>Air comp 5</option>
                    <option value="9" <?php echo isset($deviceID) &&
                    $deviceID == 9
                        ? "selected"
                        : ""; ?>>Air comp 4</option>
                    <option value="10" <?php echo isset($deviceID) &&
                    $deviceID == 10
                        ? "selected"
                        : ""; ?>>Air comp 3</option>
                    <option value="11" <?php echo isset($deviceID) &&
                    $deviceID == 11
                        ? "selected"
                        : ""; ?>>Air comp 1</option>
                    <option value="12" <?php echo isset($deviceID) &&
                    $deviceID == 12
                        ? "selected"
                        : ""; ?>>Air comp 6</option>
                    <option value="13" <?php echo isset($deviceID) &&
                    $deviceID == 13
                        ? "selected"
                        : ""; ?>>DB Compressor New / Cooling Tower</option>
                    <option value="14" <?php echo isset($deviceID) &&
                    $deviceID == 14
                        ? "selected"
                        : ""; ?>>DB AC 4 Lot 2</option>
                    <option value="15" <?php echo isset($deviceID) &&
                    $deviceID == 15
                        ? "selected"
                        : ""; ?>>DB AC 2ndfloor lot2</option>
                    <option value="16" <?php echo isset($deviceID) &&
                    $deviceID == 16
                        ? "selected"
                        : ""; ?>>DB AC Electronic Store</option>
                    <option value="17" <?php echo isset($deviceID) &&
                    $deviceID == 17
                        ? "selected"
                        : ""; ?>>DB300</option>
                    <option value="18" <?php echo isset($deviceID) &&
                    $deviceID == 18
                        ? "selected"
                        : ""; ?>>DB AC400</option>
                    <option value="19" <?php echo isset($deviceID) &&
                    $deviceID == 19
                        ? "selected"
                        : ""; ?>>DB 100 AC Office</option>
                    <option value="20" <?php echo isset($deviceID) &&
                    $deviceID == 20
                        ? "selected"
                        : ""; ?>>MSB Panel Garuda</option>
                    </select><br>
                </div>

                <div class="input_group">
                    <label for="viewType">View Type:</label>
                    <select id="viewType" name="viewType" required>
                        <option value="daily" <?php echo $viewType === "daily"
                            ? "selected"
                            : ""; ?>>Daily</option>
                        <option value="hourly" <?php echo $viewType === "hourly"
                            ? "selected"
                            : ""; ?>>Hourly</option>
                        <option value="monthly" <?php echo $viewType ===
                        "monthly"
                            ? "selected"
                            : ""; ?>>Monthly</option>
                    </select><br>
                </div>

                <div class="input_date_group">
                    <div class="input_group">
                        <label for="startDate">Start Date:</label>
                        <input type="date" id="startDate" name="startDate" value="<?php echo isset(
                            $startDate
                        )
                            ? htmlspecialchars($startDate)
                            : ""; ?>" required><br>
                    </div>

                    <div class="input_group">
                        <label for="endDate">End Date:</label>
                        <input type="date" id="endDate" name="endDate" value="<?php echo isset(
                            $endDate
                        )
                            ? htmlspecialchars($endDate)
                            : ""; ?>" required><br>
                    </div>
                </div>
                <input type="submit" value="Show Chart and Details">
                <div class='export_excel'>
                    <button id="exportBtn" style="display:inline-block;" onclick="exportToExcel()">Export to Excel</button>
                </div>
            </form>
        </div>

        <div class="container">
            <div class="chart-container">
                <?php if (!empty($dates) && !empty($totals)): ?>
                    <canvas id="myChart" width="500" height="190"></canvas>
                        <script>
                            const labels = <?php echo json_encode($dates); ?>.map(date => {
                                if ('<?php echo $viewType; ?>' === 'monthly') {
                                    return date;
                                } else if ('<?php echo $viewType; ?>' === 'hourly') {
                                    const [fullDate, time] = date.split(' '); // Split into full date and time
                                    const [year, month, day] = fullDate.split('-'); // Split full date into year, month, day
                                    const hour = time.split(':')[0]; // Get the hour part
                                    return `${day} ${hour}`; // Return formatted label
                                } else {
                                    return date.split('-')[2]; // Extract the day for daily views
                                }
                            });
                            const data = <?php echo json_encode($totals); ?>;
                            const chartLabel = '<?php echo ucfirst($viewType); ?> Total SubskWH';

                            const ctx = document.getElementById('myChart').getContext('2d');
                            const myChart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: chartLabel,
                                        data: data,
                                        backgroundColor: '#5a85e8',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    scales: {
                                        x: {
                                            grid: {
                                                display: false
                                            },
                                            ticks: {
                                                maxRotation: 45,
                                                minRotation: 0
                                            }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            grid: {
                                                display: false
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                        },
                                        title: {
                                            display: true,
                                            text: chartLabel + ' Histogram'
                                        }
                                    }
                                }
                            });
                        </script>
                <?php else: ?>
                    <p>No data available for the selected range.</p>
                <?php endif; ?>
                    </div>
            </div>
        </div>

        <?php if (!empty($data)): ?>
            <table id="dataTable" style='margin-top: 50px;'>
                <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th><?php echo htmlspecialchars(
                                $column
                            ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?php echo htmlspecialchars(
                                    $cell
                                ); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                    
            <script>
                function exportToExcel() {
                    if (typeof XLSX === 'undefined') {
                        console.error("XLSX library is not loaded");
                        return;
                    }

                    var table = document.getElementById('dataTable');
                    var rows = table.rows;
                    var data = [];

                    for (var i = 0; i < rows.length; i++) {
                        var cells = rows[i].cells;
                        var row = [];
                        for (var j = 0; j < cells.length; j++) {
                            var cellText = cells[j].innerText || cells[j].textContent;
                            row.push(cellText.trim());
                        }
                        data.push(row);
                    }
                    var wb = XLSX.utils.book_new();
                    var ws = XLSX.utils.aoa_to_sheet(data);
                    XLSX.utils.book_append_sheet(wb, ws, "PowerMeterData");

                    XLSX.writeFile(wb, "PowerMeterData.xlsx");
                }
            </script>
        <?php endif; ?>       
    </body>
</html>
