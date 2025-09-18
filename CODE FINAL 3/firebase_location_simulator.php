<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once 'db_connect.php';
// Kunin ang lahat ng 'En Route' na trips kasama ang detalye ng sasakyan at driver
$trips_result = $conn->query("
    SELECT 
        t.id, 
        t.trip_code, 
        v.type, 
        v.model,
        d.name as driver_name
    FROM trips t 
    JOIN vehicles v ON t.vehicle_id = v.id 
    JOIN drivers d ON t.driver_id = d.id
    WHERE t.status = 'En Route'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Firebase Location Simulator</title>
    <link rel="stylesheet" href="style.css">
    <!-- Firebase SDKs -->
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
</head>
<body style="padding: 2rem;">
    <div class="card" style="max-width: 600px; margin: auto;">
        <h1>Firebase Location Simulator</h1>
        <p>Gamitin ito para magpadala ng live GPS data na konektado sa sasakyan at driver.</p>
        <div class="form-group">
            <label for="trip_id">Select Trip to Simulate:</label>
            <select id="trip_id" class="form-control">
                <option value="">-- Pumili ng Biyahe --</option>
                <?php while($trip = $trips_result->fetch_assoc()): ?>
                    <option 
                        value="<?php echo $trip['id']; ?>"
                        data-vehicle-info="<?php echo htmlspecialchars($trip['type'] . ' ' . $trip['model']); ?>"
                        data-driver-name="<?php echo htmlspecialchars($trip['driver_name']); ?>">
                        <?php echo htmlspecialchars($trip['trip_code'] . ' (' . $trip['type'] . ' - ' . $trip['driver_name'] . ')'); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-actions" style="justify-content: flex-start;">
            <button id="startBtn" class="btn btn-success">Start Simulation</button>
            <button id="stopBtn" class="btn btn-danger" disabled>Stop Simulation</button>
        </div>
        <div id="log" style="margin-top: 1rem; background: #f0f0f0; padding: 0.5rem; border-radius: 4px; height: 150px; overflow-y: scroll; font-family: monospace; font-size: 0.9em; border: 1px solid #ddd;"></div>
    </div>

<script>
    // --- START OF FIREBASE CONFIG ---
    // !! MAHALAGA: PALITAN ITO NG IYONG SARILING FIREBASE CONFIG !!
    const firebaseConfig = {
    apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",
    authDomain: "slate49-cde60.firebaseapp.com",
    databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com",
    projectId: "slate49-cde60",
    storageBucket: "slate49-cde60.firebasestorage.app",
    messagingSenderId: "809390854040",
    appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed",
    measurementId: "G-FNW2WP3351"
  };
    // --- END OF FIREBASE CONFIG ---

    // Initialize Firebase
    firebase.initializeApp(firebaseConfig);
    const database = firebase.database();

    const startBtn = document.getElementById('startBtn');
    const stopBtn = document.getElementById('stopBtn');
    const tripSelect = document.getElementById('trip_id');
    const logDiv = document.getElementById('log');
    let simulationInterval;

    // Sample path (Quezon City Circle to SM North)
    const path = [
        { lat: 14.6517, lng: 121.0475 }, { lat: 14.6525, lng: 121.0460 },
        { lat: 14.6533, lng: 121.0445 }, { lat: 14.6541, lng: 121.0430 },
        { lat: 14.6550, lng: 121.0415 }, { lat: 14.6558, lng: 121.0400 },
        { lat: 14.6565, lng: 121.0385 }, { lat: 14.6568, lng: 121.0360 },
        { lat: 14.6569, lng: 121.0345 }, { lat: 14.6565, lng: 121.0330 },
        { lat: 14.6558, lng: 121.0315 }, { lat: 14.6550, lng: 121.0300 }
    ];
    let pathIndex = 0;

    function log(message) {
        const timestamp = new Date().toLocaleTimeString();
        logDiv.innerHTML += `[${timestamp}] ${message}<br>`;
        logDiv.scrollTop = logDiv.scrollHeight;
    }

    startBtn.addEventListener('click', () => {
        const selectedOption = tripSelect.options[tripSelect.selectedIndex];
        const tripId = selectedOption.value;
        if (!tripId) {
            alert('Please select a trip to simulate.');
            return;
        }

        const vehicleInfo = selectedOption.getAttribute('data-vehicle-info');
        const driverName = selectedOption.getAttribute('data-driver-name');

        startBtn.disabled = true;
        stopBtn.disabled = false;
        tripSelect.disabled = true;
        pathIndex = 0;
        log('Starting simulation for Trip ID: ' + tripId);

        simulationInterval = setInterval(() => {
            if (pathIndex >= path.length) pathIndex = 0; // Loop the path
            
            const currentLocation = path[pathIndex];
            const data = {
                lat: currentLocation.lat,
                lng: currentLocation.lng,
                speed: Math.floor(Math.random() * (60 - 40 + 1)) + 40,
                timestamp: new Date().toISOString(),
                vehicle_info: vehicleInfo, // Added vehicle info
                driver_name: driverName     // Added driver name
            };

            database.ref('live_tracking/' + tripId).set(data)
                .then(() => log(`Sent: ${data.lat.toFixed(4)}, ${data.lng.toFixed(4)} for ${vehicleInfo}`))
                .catch(error => log('Error: ' + error.message));

            pathIndex++;
        }, 3000);
    });

    stopBtn.addEventListener('click', () => {
        clearInterval(simulationInterval);
        const tripId = tripSelect.value;
        if (tripId) {
            // Remove data from Firebase to simulate end of trip
            database.ref('live_tracking/' + tripId).remove()
                .then(() => log(`Trip ${tripId} data removed from Firebase.`))
                .catch(error => log(`Error removing data: ${error.message}`));
        }
        startBtn.disabled = false;
        stopBtn.disabled = true;
        tripSelect.disabled = false;
        log('Simulation stopped.');
    });
</script>
</body>
</html>

