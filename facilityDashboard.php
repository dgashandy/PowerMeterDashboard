<?php
include('koneksiStarSchema.php'); // Include the database connection

// Function to calculate the last day of the month
function getLastDayOfMonth($year, $month) {
    return date("t", strtotime("$year-$month-01"));
}

// Initialize variables for chart data
$dates = [];
$facilityValues = [];
$subtractedValues = [];
$totalValues = [];
$sumFacility = 0; // For MTD Facility values
$sumSubtracted = 0; // For MTD Subtracted values
$sumMSB = 0; // For the sum of MSB values
$sumTotalValues = 0; // For the sum of totalValues
$percentageFacilityToMSB = 0; // To calculate the percentage of sum of facilityValues to sumMSB
$categoriesData = [];
$categories = [];


// Check if the form has been submitted with GET parameters
if (isset($_GET['InputMonth']) && isset($_GET['InputYear']) && isset($_GET['InputBuilding'])) {
    // Get form inputs from the query string
    $inputMonth = $_GET['InputMonth'];
    $inputYear = $_GET['InputYear'];
    $inputBuilding = $_GET['InputBuilding'];

    // Generate the start and end dates
    $p_StartDate = "$inputYear-$inputMonth-01";
    $p_EndDate = "$inputYear-$inputMonth-" . getLastDayOfMonth($inputYear, $inputMonth);

    // First query: Call spGetDailyValueFacility
    $sqlFacility = "CALL spGetDailyValueFacility('$p_StartDate', '$p_EndDate', '$inputBuilding')";
    $resultFacility = mysqli_query($conn, $sqlFacility);

    if (!$resultFacility) {
        die("Facility query failed: " . mysqli_error($conn));
    }

    // Fetch the Facility results
    while ($row = mysqli_fetch_assoc($resultFacility)) {
        $dates[] = $row['Date'];  // Store the date
        $facilityValues[] = $row['TotalSubskWH']; // Store the Facility TotalSubskWH
        $sumFacility += $row['TotalSubskWH']; // Sum MTD Facility values
    }

    // Close the result and reset for second query
    mysqli_next_result($conn);

    // Second query: Call spGetDailyValueMSB
    $sqlMSB = "CALL spGetDailyValueMSB('$p_StartDate', '$p_EndDate', '$inputBuilding')";
    $resultMSB = mysqli_query($conn, $sqlMSB);

    if (!$resultMSB) {
        die("MSB query failed: " . mysqli_error($conn));
    }

    // Fetch the MSB results
    while ($row = mysqli_fetch_assoc($resultMSB)) {
        $msbValues[] = $row['TotalSubskWH']; // Store the MSB TotalSubskWH
        $sumMSB += $row['TotalSubskWH']; // Sum MTD MSB values
    }

    // Calculate the difference between Facility and MSB
    for ($i = 0; $i < count($facilityValues); $i++) {
        $subtractedValues[] = isset($msbValues[$i]) ? $msbValues[$i] - $facilityValues[$i] : $facilityValues[$i];
        $sumSubtracted += isset($msbValues[$i]) ? $msbValues[$i] - $facilityValues[$i] : 0; // Sum MTD Subtracted values
    }

    // Calculate total values
    for ($i = 0; $i < count($facilityValues); $i++) {
        $totalValues[] = isset($subtractedValues[$i]) ? $facilityValues[$i] + $subtractedValues[$i] : $facilityValues[$i];
        $sumTotalValues += $totalValues[$i]; // Calculate the sum of totalValues
    }

    // Calculate the percentage of sum of facilityValues to sum of sumMSB
    if ($sumMSB > 0) {
        $percentageFacilityToMSB = ($sumFacility / $sumMSB) * 100;
    }

    // Close the connection after retrieving data
    mysqli_next_result($conn);

    $sqlCategory = "CALL spGetDailyPerCategory('$p_StartDate', '$p_EndDate', '$inputBuilding')";
    $resultCategory = mysqli_query($conn, $sqlCategory);

    if (!$resultCategory) {
        die("Category query failed: " . mysqli_error($conn));
    }

    // Fetch the Category results
    while ($row = mysqli_fetch_assoc($resultCategory)) {
        $date = $row['Date'];
        $category = $row['Category'];
        $totalSubskWH = $row['TotalSubskWH'];

        // Populate the categories array
        if (!in_array($category, $categories)) {
            $categories[] = $category;
        }

        // Store the data for each category
        if (!isset($categoriesData[$category])) {
            $categoriesData[$category] = [];
        }

        // Match the dates and fill the values
        $categoriesData[$category][] = $totalSubskWH;
    }

    $categorySums = []; // To store the sum for each category

    foreach ($categoriesData as $category => $values) {
        $categorySums[$category] = array_sum($values); // Calculate the sum of values for each category
    }

    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Bar and Pie Power Meter Chart</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: "Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
            padding: 10px 30px 10px 30px;
            background-color: #edf2ee;
        }

        .title_wrapper {
            color: #045c11;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
        }
        .all_wrapper{
            width: 95%;
            margin-left: auto;
            margin-right: auto;
            height: auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            border: 2px solid #a1f0ac;
            box-shadow: 5px 5px 8px rgba(0, 0, 0, 0.3);
        }
        .filter_wrapper{
            display: flex;
            margin-left: 10px;
            margin-top: 10px;
            margin-bottom: 0px;
        }

        .filter_wrapper form{
            display: flex;
            gap: 20px;
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

        input[type="submit"] {
            display: flex;
            align-items: center;
            background-color: #27912b;
            color: white;
            font-size: 10px;
            font-weight: 20px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 100px;
            height: 60px;
            margin-left: 10px;
            margin-top: 10px;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
        }

        input[type="submit"]:hover {
            background-color: #5cdb60;
        }

        .chart_container {
            gap: 20px;
            justify-content: space-between;
            align-items: stretch; /* Ensure all items stretch to the same height */
            margin: 20px 0;
            min-height: 400px;
        }

        .chart_first_row {
            display: flex;
            flex: 1;
            gap: 20px;
            justify-content: space-between;
            align-items: stretch; /* Ensure the children stretch to equal height */
        }

        .msb_facility_chart_wrapper {
            flex: 1 1 66%;
            min-width: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #e1f2ea;
            border-radius: 10px;
            background-color: #e1f2ea;
            height: 75%;
            padding: 10px;
            min-height: 200px; /* Set the same minimum height as the other elements */
        }
        
        .msb_facility_chart_wrapper canvas {
            max-height: 300px; /* Limit the chart height */
            height: 100%; /* Stretch to fill the wrapper */
        }

        .div_wrapper_first_row {
            flex: 1 1 30%;
            min-width: 300px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            justify-content: center;
            align-items: center;
            height: 80%; /* Stretch to full height */
            min-height: 200px; /* Set the same minimum height */
        }

        #facilitySum, #percentageDisplay {
            font-size: 18px;
            border: 1px solid #e1f2ea;
            border-radius: 10px;
            background-color: #e1f2ea;
            text-align: center;
            width: 100%;
            min-height: 155px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: row;
            gap: 20px;
        }

        #facilitySum .text,
        #percentageDisplay .text{
            color: #045c11;
        }

        .circle {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 100px;
            height: 100px;
            background-color: #57d57d; /* Green background for the circle */
            border-radius: 50%;
            color: white;
            font-size: 22px;
            font-weight: bold;
            margin-left: 50px;
        }

        .circle .value {
            font-size: 15px;
            color: #045c11;
        }

        .circle .unit {
            font-size: 14px;
            font-weight: normal;
            color: #045c11;
        }

        .chart_second_row {
            display: flex;
            flex: 1;
            gap: 20px;
            justify-content: space-between;
            align-items: stretch; /* Stretch all items to the same height */
            margin-top: 10px;
        }

        .msb_facility_pie_wrapper, .percategory_chart, #categorySumDisplay {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border: 1px solid #e1f2ea;
            border-radius: 10px;
            background-color: #e1f2ea;
            height: auto;
            padding: 10px;
        }

        .msb_facility_pie_wrapper {
            flex: 1 1 30%; /* The pie chart takes about 30% of the width */
        }

        .percategory_chart {
            flex: 1 1 55%; /* Give more space to the bar chart */

        }

        #categorySumDisplay {
            flex: 1 1 15%; /* Reduce the width of the category sum display */
            padding: 10px;
            height: auto;
        }

        .msb_facility_pie_wrapper canvas,
        .percategory_chart canvas {
            max-height: 300px; /* Limit the chart height */
            height: 100%; /* Stretch to fill the wrapper */
        }

        .text_wrap_category{
            display: flex;
            margin-bottom: 10px;
        }

        .text_wrap_category .description{
            position: relative;
            top: 10px;
            font-size: 10px;
            font-weight: bold;
            color: #045c11;
        }

        .text_wrap_category .large-number{
            font-size: 20px;
            font-weight: bold;
            color: #045c11;
        }
    </style>
</head>
<body>
    <div class="title_wrapper">
        <h1>FACILITY DASHBOARD</h1>
    </div>

    <div class="all_wrapper">
        <div class="filter_wrapper">
            <form method="GET" action="facilityDashboard.php">
                <div class="input_group">
                    <label for="InputMonth">Month:</label>
                    <select name="InputMonth" id="InputMonth">
                        <option value="01">January</option>
                        <option value="02">February</option>
                        <option value="03">March</option>
                        <option value="04">April</option>
                        <option value="05">May</option>
                        <option value="06">June</option>
                        <option value="07">July</option>
                        <option value="08">August</option>
                        <option value="09">September</option>
                        <option value="10" selected>October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select><br>
                </div>

                <div class="input_group">
                    <label for="InputYear">Year:</label>
                    <select name="InputYear" id="InputYear">
                        <option value="2022">2022</option>
                        <option value="2023">2023</option>
                        <option value="2024" selected>2024</option>
                    </select><br>
                </div>

                <div class="input_group">
                    <label for="InputBuilding">Building:</label>
                    <select name="InputBuilding" id="InputBuilding">
                        <option value="Panbil">Panbil</option>
                        <option value="Garuda">Garuda</option>
                        <option value="All Building" selected>All Building</option>
                    </select><br>
                </div>

                <div class="input_group">
                    <input type="submit" value="Generate Chart">
                </div>
            </form>
        </div>

        <div class="chart_container">
            <div class="chart_first_row">
                <div class="msb_facility_chart_wrapper">
                    <canvas id="dailyChart"></canvas>
                </div>

                <div class="div_wrapper_first_row">
                    <div id="facilitySum">
                        <div class="text">
                            <strong>MTD Power <br>Generated</strong>
                        </div>
                        <div class="circle">
                            <span class="value"><?php echo number_format($sumMSB, 2); ?></span>
                            <span class="unit">kWh</span>
                        </div>
                    </div>

                    <div id="percentageDisplay">
                        <div class="text">
                            <strong>MTD Facility <br>Consumption <br> Rate</strong>
                        </div>
                        <div class="circle">
                            <span class="value"><?php echo number_format($percentageFacilityToMSB, 2); ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="chart_second_row">
                <div class="msb_facility_pie_wrapper">
                    <canvas id="pieChart" style="width: 200px;"></canvas>
                </div>
                <div class="percategory_chart">
                    <canvas id="combinedChart"></canvas>
                </div>
 
                <div id="categorySumDisplay">
                    <strong style='color: #045c11;'>% Categories :</strong><br>
                    <?php if (!empty($categorySums) && !empty($sumFacility) && $sumFacility != 0): ?>
                        <?php foreach ($categorySums as $category => $sum): ?>
                            <?php
                                // Calculate the percentage
                                $percentage = ($sum / $sumFacility) * 100;
                            ?>
                            <div class="text_wrap_category">
                                <span class="large-number"><?php echo number_format($percentage, 2); ?>%</span>
                                <div class="description">&nbsp;&nbsp;<?php echo $category; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($sumFacility == 0): ?>
                        <strong>Error: sumFacility is zero, unable to calculate percentages.</strong>
                    <?php else: ?>
                        <strong>No category data available.</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>   
    </div>      
            
    <script>
        // Only render the charts if there is data (after form submission)
        <?php if ($_SERVER['REQUEST_METHOD'] == 'GET' && !empty($_GET['InputMonth']) && !empty($_GET['InputYear'])): ?>
        const dates = <?php echo json_encode($dates); ?>;
        const facilityValues = <?php echo json_encode($facilityValues); ?>;
        const subtractedValues = <?php echo json_encode($subtractedValues); ?>;
        const totalValues = <?php echo json_encode($totalValues); ?>;
        const categories = <?php echo json_encode($categories); ?>;
        const categoriesData = <?php echo json_encode($categoriesData); ?>;

        const ctxBar = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Others',
                        data: subtractedValues,
                        backgroundColor: '#10c92d', // Changed bar color
                        borderColor: '#10c92d', // Changed border color to match bar
                        borderWidth: 1
                    },
                    {
                        label: 'Facility',
                        data: facilityValues,
                        backgroundColor: '#11f534', // Changed bar color
                        borderColor: '#11f534', // Changed border color to match bar
                        borderWidth: 1
                    },
                    {
                        label: 'Totals',
                        data: totalValues,
                        type: 'line',
                        fill: false,
                        borderColor: '#045c11', // Changed line color
                        tension: 0.1
                    }
                ]
            },
            options: {
                scales: {
                    x: { 
                        beginAtZero: true,
                        grid: {
                            display: false // Removes x-axis grid
                        }
                    },
                    y: { 
                        beginAtZero: true,
                        grid: {
                            display: false // Removes y-axis grid
                        }
                    }
                }
            }
        });

        const ctxCombined = document.getElementById('combinedChart').getContext('2d');
        const combinedChart = new Chart(ctxCombined, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Facility Values (Bar)',
                        data: facilityValues,
                        backgroundColor: '#10c92d', // Bar color
                        borderColor: '#10c92d', // Bar border color
                        borderWidth: 1,
                        type: 'bar',
                        order: 2
                    },
                    ...categories.map(category => ({
                        label: category + ' (Line)',
                        data: categoriesData[category],
                        fill: false,
                        borderColor: getDarkBrownColor(), // Line border color (dark brown)
                        backgroundColor: getDarkBrownColor(), // Line background color (same as border)
                        tension: 0.1,
                        type: 'line',
                        order: 1
                    }))
                ]
            },
            options: {
                scales: {
                    x: { 
                        beginAtZero: true,
                        grid: { display: false } // Remove x-axis grid
                    },
                    y: { 
                        beginAtZero: true,
                        grid: { display: false } // Remove y-axis grid
                    }
                }
            }
        });

        // Function to generate dark brown-related colors for the lines
        function getDarkBrownColor() {
            const browns = ['#8B4513', '#A0522D', '#CD853F', '#D2691E', '#654321'];
            return browns[Math.floor(Math.random() * browns.length)];
        }


        const ctxPie = document.getElementById('pieChart').getContext('2d');
        const pieChart = new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: ['Others', 'Facility', ],
                datasets: [{
                    data: [<?php echo $sumSubtracted; ?>, <?php echo $sumFacility; ?>],
                    backgroundColor: ['#10c92d','#11f534'],
                    borderColor: ['#10c92d','#11f534']
                }]
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
