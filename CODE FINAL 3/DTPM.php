<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once 'db_connect.php';
$message = '';

// Handle Add/Edit/Delete Driver (No changes here)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_driver'])) {
    $id = $_POST['driver_id']; $name = $_POST['name']; $license_number = $_POST['license_number']; $status = $_POST['status']; $rating = $_POST['rating']; $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : NULL;
    if (empty($id)) { $sql = "INSERT INTO drivers (name, license_number, status, rating, user_id) VALUES (?, ?, ?, ?, ?)"; $stmt = $conn->prepare($sql); $stmt->bind_param("sssdi", $name, $license_number, $status, $rating, $user_id);
    } else { $sql = "UPDATE drivers SET name=?, license_number=?, status=?, rating=?, user_id=? WHERE id=?"; $stmt = $conn->prepare($sql); $stmt->bind_param("sssdii", $name, $license_number, $status, $rating, $user_id, $id); }
    if ($stmt->execute()) { $message = "<div class='message-banner success'>Driver saved successfully!</div>"; } else { $message = "<div class='message-banner error'>Error saving driver. The license number might already exist.</div>"; }
    $stmt->close();
}
if (isset($_GET['delete_driver'])) {
    $id = $_GET['delete_driver']; $sql = "DELETE FROM drivers WHERE id = ?"; $stmt = $conn->prepare($sql); $stmt->bind_param("i", $id);
    if ($stmt->execute()) { $message = "<div class='message-banner success'>Driver deleted successfully!</div>"; } else { $message = "<div class='message-banner error'>Error: Cannot delete a driver assigned to a trip.</div>"; }
    $stmt->close();
}

// Fetch Initial Tracking Data for vehicles already en route when page loads.
$initial_tracking_query = $conn->query("
    SELECT t.id as trip_id, v.type, v.model, d.name as driver_name, tl.latitude, tl.longitude, tl.speed_mph, tl.status_message
    FROM tracking_log tl
    JOIN trips t ON tl.trip_id = t.id AND t.status = 'En Route'
    JOIN vehicles v ON t.vehicle_id = v.id
    JOIN drivers d ON t.driver_id = d.id
    INNER JOIN ( SELECT trip_id, MAX(log_time) AS max_log_time FROM tracking_log GROUP BY trip_id ) latest_log 
    ON tl.trip_id = latest_log.trip_id AND tl.log_time = latest_log.max_log_time
");
$initial_locations = [];
if ($initial_tracking_query) { while($row = $initial_tracking_query->fetch_assoc()) { $initial_locations[] = $row; } }
$initial_locations_json = json_encode($initial_locations);

// Fetch Driver Data
$drivers = $conn->query("SELECT d.*, v.type as vehicle_type, v.model as vehicle_model, v.tag_code FROM drivers d LEFT JOIN vehicles v ON d.id = v.assigned_driver_id ORDER BY d.name ASC");
$users = $conn->query("SELECT id, username FROM users WHERE role = 'driver'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver & Trip Performance Monitoring | LOGISTICS II</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.marker.slideto@0.2.0/Leaflet.Marker.SlideTo.js"></script>
  <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
  <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
</head>
<body>
  <div class="sidebar" id="sidebar">
    <div class="logo"><img src="logo.png" alt="SLATE Logo"></div>
    <div class="system-name">LOGISTIC 2</div>
    <a href="landpage.php">Dashboard</a>
    <a href="FVM.php">Fleet & Vehicle Management (FVM)</a>
    <a href="VRDS.php">Vehicle Reservation & Dispatch System (VRDS)</a>
    <a href="DTPM.php" class="active">Driver and Trip Performance Monitoring</a>
    <a href="TCAO.php">Transport Cost Analysis & Optimization (TCAO)</a>
    <a href="MA.php">Mobile Fleet Command App</a>
    <a href="logout.php">Logout</a>
  </div>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Driver & Trip Performance Monitoring System</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <?php echo $message; ?>

    <div class="table-section">
      <h3>Live Tracking Module</h3>
       <div style="margin-bottom: 1rem;">
          <a href="trip_history.php" class="btn btn-info">Trip History Logs</a>
       </div>
      <div id="liveTrackingMap" style="height: 400px; width: 100%; border-radius: var(--border-radius); margin-bottom: 1.5rem;"></div>
      
      <table>
        <thead>
          <tr><th>Vehicle</th><th>Driver</th><th>Location (Lat, Lng)</th><th>Speed</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody id="tracking-table-body">
          <tr id="no-tracking-placeholder" style="<?php echo count($initial_locations) > 0 ? 'display: none;' : ''; ?>">
            <td colspan="6">No live tracking data available. Start the simulator or a real device.</td>
          </tr>
          <?php if(count($initial_locations) > 0): ?>
            <?php foreach($initial_locations as $row): ?>
            <tr class="clickable-row" id="trip-row-<?php echo $row['trip_id']; ?>" data-tripid="<?php echo $row['trip_id']; ?>" style="cursor: pointer;">
                <td><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
                <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                <td class="location-cell"><?php echo htmlspecialchars($row['latitude'] . ', ' . $row['longitude']); ?></td>
                <td class="speed-cell"><?php echo htmlspecialchars($row['speed_mph']); ?> mph</td>
                <td><?php echo htmlspecialchars($row['status_message']); ?></td>
                <td><a href="VRDS.php" class="btn btn-info btn-sm">View Dispatch</a></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>      

    <div class="table-section-2">
      <h3>Driver Profiles</h3>
      <button id="addDriverBtn" class="btn btn-primary" style="margin-bottom: 1rem;">Add Driver</button>
      <table>
        <thead>
          <tr><th>ID</th><th>Name</th><th>Assigned Vehicle</th><th>Status</th><th>Rating</th><th>Actions</th></tr>   
        </thead>
        <tbody>
            <?php if($drivers->num_rows > 0): mysqli_data_seek($drivers, 0); ?>
               <?php while($row = $drivers->fetch_assoc()): ?>
              <tr>
                  <td><?php echo $row['id']; ?></td>
                  <td><?php echo htmlspecialchars($row['name']); ?></td>
                  <td>
                    <?php if (!empty($row['vehicle_type'])): ?>
                        <a href="FVM.php?query=<?php echo urlencode($row['tag_code']); ?>" title="Click to view in FVM Module">
                            <?php echo htmlspecialchars($row['vehicle_type'] . ' ' . $row['vehicle_model']); ?>
                        </a>
                    <?php else: ?>
                        <span style="color: #888;">N/A</span>
                    <?php endif; ?>
                  </td>
                  <td><span class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                  <td><?php echo htmlspecialchars($row['rating']); ?> ★</td>
                  <td class="action-buttons">
                    <button class="btn btn-warning btn-sm editDriverBtn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['name']); ?>" data-license_number="<?php echo htmlspecialchars($row['license_number']); ?>" data-status="<?php echo $row['status']; ?>" data-rating="<?php echo $row['rating']; ?>" data-user_id="<?php echo $row['user_id']; ?>">Edit</button>
                    <a href="DTPM.php?delete_driver=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</a>
                    <a href="trip_history.php?driver_id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">Trips</a>
                    <?php if (!empty($row['user_id'])): ?><a href="MA.php#messaging" class="btn btn-success btn-sm">Message</a><?php endif; ?>
                  </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?><tr><td colspan="6">No drivers found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <div id="driverModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2 id="modalTitle">Add Driver</h2>
        <form action="DTPM.php" method="POST">
            <input type="hidden" id="driver_id" name="driver_id">
            <div class="form-group"><label for="name">Full Name</label><input type="text" name="name" id="name" class="form-control" required></div>
            <div class="form-group"><label for="license_number">License Number</label><input type="text" name="license_number" id="license_number" class="form-control" required></div>
            <div class="form-group"><label for="rating">Rating (1.0 - 5.0)</label><input type="number" step="0.1" min="1" max="5" name="rating" id="rating" class="form-control" required></div>
            <div class="form-group"><label for="status">Status</label><select name="status" id="status" class="form-control" required><option value="Active">Active</option><option value="Suspended">Suspended</option><option value="Inactive">Inactive</option></select></div>
            <div class="form-group"><label for="user_id">Link to User Account</label><select name="user_id" id="user_id" class="form-control"><option value="">-- None --</option><?php mysqli_data_seek($users, 0); while($user = $users->fetch_assoc()): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option><?php endwhile; ?></select></div>
            <div class="form-actions"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="save_driver" class="btn btn-primary">Save Driver</button></div>
        </form>
    </div>
  </div>
  
<script>
    document.getElementById('themeToggle').addEventListener('change', function() { document.body.classList.toggle('dark-mode', this.checked); });
    document.getElementById('hamburger').addEventListener('click', function() {
      const sidebar = document.getElementById('sidebar'); const mainContent = document.getElementById('mainContent');
      if (window.innerWidth <= 992) { sidebar.classList.toggle('show'); } else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('expanded'); }
    });
    document.querySelectorAll('.modal').forEach(modal => {
        const closeBtn = modal.querySelector('.close-button'); const cancelBtn = modal.querySelector('.cancelBtn');
        closeBtn.addEventListener('click', () => modal.style.display = 'none');
        if(cancelBtn) { cancelBtn.addEventListener('click', () => modal.style.display = 'none'); }
        window.addEventListener('click', (event) => { if (event.target == modal) { modal.style.display = 'none'; } });
    });
    const driverModal = document.getElementById("driverModal");
    document.getElementById("addDriverBtn").addEventListener("click", () => { driverModal.querySelector('form').reset(); driverModal.querySelector('#driver_id').value = ''; driverModal.querySelector("#modalTitle").textContent = 'Add New Driver'; driverModal.style.display = 'block'; });
    document.querySelectorAll(".editDriverBtn").forEach(btn => {
        btn.addEventListener("click", () => {
            driverModal.querySelector('form').reset(); driverModal.querySelector("#modalTitle").textContent = 'Edit Driver';
            driverModal.querySelector('#driver_id').value = btn.dataset.id; driverModal.querySelector('#name').value = btn.dataset.name; driverModal.querySelector('#license_number').value = btn.dataset.license_number; driverModal.querySelector('#status').value = btn.dataset.status; driverModal.querySelector('#rating').value = btn.dataset.rating; driverModal.querySelector('#user_id').value = btn.dataset.user_id;
            driverModal.style.display = 'block';
        });
    });

    // --- UPGRADED JAVASCRIPT FOR LIVE TRACKING (WITH DEBUGGING AND FIX) ---
    console.log("Live Tracking Script Initialized.");
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

    try {
        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
            console.log("Firebase Initialized.");
        } else {
            firebase.app(); 
            console.log("Firebase was already initialized.");
        }
    } catch (e) {
        console.error("Firebase initialization failed. Please check your config.", e);
    }
    const database = firebase.database();
    
    const map = L.map('liveTrackingMap').setView([12.8797, 121.7740], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>' }).addTo(map);
    
    const markers = {};
    const trackingTableBody = document.getElementById('tracking-table-body');
    const noTrackingPlaceholder = document.getElementById('no-tracking-placeholder');

    function createOrUpdateMarkerAndRow(tripId, data) {
        console.log(`Updating data for Trip ID: ${tripId}`, data);
        const vehicle = data.vehicle_info || "Unknown Vehicle";
        const driver = data.driver_name || "Unknown Driver";
        const newLatLng = [data.lat, data.lng];

        // Hide placeholder if it's visible
        if (noTrackingPlaceholder.style.display !== 'none') {
            noTrackingPlaceholder.style.display = 'none';
        }

        let tripRow = document.getElementById(`trip-row-${tripId}`);
        if (!markers[tripId]) {
            console.log(`Creating new marker and row for Trip ID: ${tripId}`);
            const popupContent = `<b>Vehicle:</b> ${vehicle}<br><b>Driver:</b> ${driver}`;
            markers[tripId] = L.marker(newLatLng).addTo(map).bindPopup(popupContent);
            markers[tripId].options.duration = 2000; // for slideTo
            
            // Create new table row only if it doesn't exist
            if (!tripRow) {
                const newRow = document.createElement('tr');
                newRow.id = `trip-row-${tripId}`; newRow.className = 'clickable-row'; newRow.dataset.tripid = tripId;
                newRow.innerHTML = `<td>${vehicle}</td><td>${driver}</td><td class="location-cell">${data.lat.toFixed(6)}, ${data.lng.toFixed(6)}</td><td class="speed-cell">${data.speed} mph</td><td>Trip Started</td><td><a href="VRDS.php" class="btn btn-info btn-sm">View Dispatch</a></td>`;
                newRow.addEventListener('click', () => focusOnMarker(tripId));
                trackingTableBody.appendChild(newRow);
            }
        } else { // If marker and row exist, just update them
            markers[tripId].slideTo(newLatLng, { duration: 2000 });
            if (tripRow) {
                tripRow.querySelector('.location-cell').textContent = `${data.lat.toFixed(6)}, ${data.lng.toFixed(6)}`;
                tripRow.querySelector('.speed-cell').textContent = `${data.speed} mph`;
                tripRow.style.backgroundColor = 'var(--info-color)';
                setTimeout(() => { tripRow.style.backgroundColor = ''; }, 1500);
            }
        }
    }

    function removeMarkerAndRow(tripId) {
        console.log(`Removing marker and row for Trip ID: ${tripId}`);
        if (markers[tripId]) { map.removeLayer(markers[tripId]); delete markers[tripId]; }
        const tripRow = document.getElementById(`trip-row-${tripId}`);
        if (tripRow) { tripRow.remove(); }
        // Show placeholder only if the table is truly empty
        if (trackingTableBody.childElementCount === 0) {
            trackingTableBody.appendChild(noTrackingPlaceholder);
            noTrackingPlaceholder.style.display = '';
        }
    }
    
    function focusOnMarker(tripId) { if (markers[tripId]) { map.flyTo(markers[tripId].getLatLng(), 15); markers[tripId].openPopup(); } }

    // This part for initial locations is now handled by the PHP loop directly into the HTML
    // We just need to make sure the clickable event listeners are added to them
    document.querySelectorAll('#tracking-table-body .clickable-row').forEach(row => {
        row.addEventListener('click', () => focusOnMarker(row.dataset.tripid));
    });

    const initialLocations = <?php echo $initial_locations_json; ?>;
    console.log("Initial locations from server:", initialLocations);
    if (initialLocations.length > 0) {
      initialLocations.forEach(loc => {
        const popupContent = `<b>Vehicle:</b> ${loc.type} ${loc.model}<br><b>Driver:</b> ${loc.driver_name}`;
        markers[loc.trip_id] = L.marker([loc.latitude, loc.longitude]).addTo(map).bindPopup(popupContent);
        markers[loc.trip_id].options.duration = 2000; // for slideTo
      });
    }

    const trackingRef = database.ref('live_tracking');
    console.log("Setting up Firebase listeners at 'live_tracking' path...");
    trackingRef.on('child_added', (snapshot) => { console.log("Firebase child_added:", snapshot.key); createOrUpdateMarkerAndRow(snapshot.key, snapshot.val()); });
    trackingRef.on('child_changed', (snapshot) => { console.log("Firebase child_changed:", snapshot.key); createOrUpdateMarkerAndRow(snapshot.key, snapshot.val()); });
    trackingRef.on('child_removed', (snapshot) => { console.log("Firebase child_removed:", snapshot.key); removeMarkerAndRow(snapshot.key); });
</script>
</body>
</html>

