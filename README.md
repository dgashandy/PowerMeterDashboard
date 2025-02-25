# Power Meter Dashboard

This project sets up a dashboard for displaying kWh usage of various facility devices. The power usage data is uploaded by **Raspberry Pi** devices and extracted using **Node-RED**, then visualized on a **PHP** web interface.

## 🚀 Features
- **Real-time kWh usage monitoring** of facility devices.
- Integration with **Raspberry Pi** for capturing device data.
- **Node-RED** for extracting and processing power meter data from the cloud.
- **PHP** dashboard for visualizing power consumption over different time periods.
- **JavaScript** for enhancing the interactivity of the dashboard, allowing for dynamic data updates and visualization.

## 🛠️ Technologies
- **Raspberry Pi**: Gathers power usage data from connected devices.
- **Node-RED**: Manages cloud extraction and processing of power meter data.
- **PHP**: For building the web interface to display data.
- **JavaScript**: Adds dynamic behavior to the dashboard (real-time updates, interactive charts).
- **MySQL**: Stores the collected data from Raspberry Pi for historical analysis and querying.

## 🌐 Architecture Overview
1. **Raspberry Pi Data Collection**: The Raspberry Pi is connected to power meters that monitor kWh usage for facility devices.
2. **Node-RED Cloud Extraction**: Node-RED handles the extraction of real-time data from the Raspberry Pi or cloud services, formats it, and sends it to the PHP dashboard.
3. **PHP Web Dashboard**: Displays the data using charts, tables, and real-time metrics. It also supports filtering by time range (daily, monthly, yearly).
4. **MySQL Database**: Stores the kWh usage data collected over time.

## 🔧 Setup Instructions

### Prerequisites
- Raspberry Pi with sensors connected to power meters for measuring kWh usage.
- Node-RED installed on the Raspberry Pi or a server.
- A cloud service to upload Raspberry Pi data, or use direct local access if no cloud is required.
- PHP installed on a web server for rendering the dashboard.
- MySQL for storing kWh data.
