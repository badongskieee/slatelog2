<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Role-based Access Control: Bawal ang drivers dito.
if ($_SESSION['role'] === 'driver') {
    header("location: mobile_app.php");
    exit;
}

require_once 'db_connect.php';
$message = '';

// Handle Add/Edit Vehicle
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_vehicle'])) {
    $id = $_POST['vehicle_id'];
    $type = $_POST['type'];
    $model = $_POST['model'];
    $tag_type = $_POST['tag_type'];
    $tag_code = $_POST['tag_code'];
    $load_capacity_kg = isset($_POST['load_capacity_kg']) && $_POST['load_capacity_kg'] !== '' ? (int)$_POST['load_capacity_kg'] : NULL;
    $plate_no = $_POST['plate_no'];

    if (empty($id)) { // Add new vehicle
        $check_sql = "SELECT id FROM vehicles WHERE tag_code = ? OR plate_no = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $tag_code, $plate_no);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $message = "<div class='message-banner error'>Error: A vehicle with this Tag Code or Plate No. already exists.</div>";
        } else {
            $sql = "INSERT INTO vehicles (type, model, tag_type, tag_code, load_capacity_kg, plate_no, status, assigned_driver_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssissi", $type, $model, $tag_type, $tag_code, $load_capacity_kg, $plate_no, $status, $assigned_driver_id);
            if ($stmt->execute()) {
                $message = "<div class='message-banner success'>New vehicle added successfully!</div>";
            } else {
                $message = "<div class='message-banner error'>Error saving vehicle: " . $conn->error . "</div>";
            }
            $stmt->close();
        }
        $check_stmt->close();
    } else { // Update existing vehicle
        $check_sql = "SELECT id FROM vehicles WHERE (tag_code = ? OR plate_no = ?) AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssi", $tag_code, $plate_no, $id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
             $message = "<div class='message-banner error'>Error: Another vehicle with this Tag Code or Plate No. already exists.</div>";
        } else {
            $sql = "UPDATE vehicles SET type=?, model=?, tag_type=?, tag_code=?, load_capacity_kg=?, plate_no=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssisi", $type, $model, $tag_type, $tag_code, $load_capacity_kg, $plate_no, $id);
            if ($stmt->execute()) {
                $message = "<div class='message-banner success'>Vehicle updated successfully!</div>";
            } else {
                $message = "<div class='message-banner error'>Error updating vehicle: " . $conn->error . "</div>";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}


// Handle Delete Vehicle
if (isset($_GET['delete_vehicle'])) {
    $id = $_GET['delete_vehicle'];
    $sql = "DELETE FROM vehicles WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Vehicle deleted successfully!</div>";
    } else {
        $message = "<div class='message-banner error'>Error deleting vehicle. It might be assigned to a trip.</div>";
    }
    $stmt->close();
}

// Handle Add/Edit Maintenance Request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_maintenance'])) {
    $maintenance_id = $_POST['maintenance_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $arrival_date = $_POST['arrival_date'];
    $date_of_return = !empty($_POST['date_of_return']) ? $_POST['date_of_return'] : NULL;
    $status = $_POST['status'];

    if (empty($maintenance_id)) { // Add
        $sql = "INSERT INTO maintenance_approvals (vehicle_id, arrival_date, date_of_return, status) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $vehicle_id, $arrival_date, $date_of_return, $status);
    } else { // Update
        $sql = "UPDATE maintenance_approvals SET vehicle_id=?, arrival_date=?, date_of_return=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssi", $vehicle_id, $arrival_date, $date_of_return, $status, $maintenance_id);
    }
    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Maintenance request saved successfully!</div>";
    } else {
        $message = "<div class='message-banner error'>Error saving maintenance request: " . $conn->error . "</div>";
    }
    $stmt->close();
}

// Handle Update Maintenance Status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_maintenance_status'])) {
    $maintenance_id = $_POST['maintenance_id_status'];
    $new_status = $_POST['new_status'];
    $allowed_statuses = ['Approved', 'In Progress', 'Completed', 'Rejected'];
    
    if (in_array($new_status, $allowed_statuses)) {
        $stmt = $conn->prepare("UPDATE maintenance_approvals SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $maintenance_id);
        if ($stmt->execute()) {
            $message = "<div class='message-banner success'>Maintenance status updated to '$new_status'.</div>";
        } else {
            $message = "<div class='message-banner error'>Error updating status.</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='message-banner error'>Invalid status update attempted.</div>";
    }
}


// Handle Delete Maintenance Request
if (isset($_GET['delete_maintenance'])) {
    $id = $_GET['delete_maintenance'];
    $sql = "DELETE FROM maintenance_approvals WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Maintenance request deleted successfully!</div>";
    } else {
        $message = "<div class='message-banner error'>Error deleting request.</div>";
    }
    $stmt->close();
}

// Handle Add/Edit Usage Log
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_usage_log'])) {
    $log_id = $_POST['log_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $log_date = $_POST['log_date'];
    $metrics = $_POST['metrics'];
    $fuel_usage = $_POST['fuel_usage'];
    $mileage = $_POST['mileage'];

    if (empty($log_id)) { // Add
        $sql = "INSERT INTO usage_logs (vehicle_id, log_date, metrics, fuel_usage, mileage) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issdi", $vehicle_id, $log_date, $metrics, $fuel_usage, $mileage);
    } else { // Update
        $sql = "UPDATE usage_logs SET vehicle_id=?, log_date=?, metrics=?, fuel_usage=?, mileage=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issdii", $vehicle_id, $log_date, $metrics, $fuel_usage, $mileage, $log_id);
    }
    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Usage log saved successfully!</div>";
    } else {
        $message = "<div class='message-banner error'>Error saving usage log: " . $conn->error . "</div>";
    }
    $stmt->close();
}

// Handle Delete Usage Log
if (isset($_GET['delete_log'])) {
    $id = $_GET['delete_log'];
    $sql = "DELETE FROM usage_logs WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Usage log deleted successfully!</div>";
    } else {
        $message = "<div class='message-banner error'>Error deleting log.</div>";
    }
    $stmt->close();
}


// Fetch data
$search_query = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';
$where_clause = '';
if (!empty($search_query)) {
    $where_clause = "WHERE v.type LIKE '%$search_query%' OR v.model LIKE '%$search_query%' OR v.tag_code LIKE '%$search_query%' OR v.plate_no LIKE '%$search_query%' OR d.name LIKE '%$search_query%'";
}

$vehicles_result = $conn->query("SELECT v.*, d.name as driver_name FROM vehicles v LEFT JOIN drivers d ON v.assigned_driver_id = d.id $where_clause ORDER BY v.id DESC");
$maintenance_result = $conn->query("SELECT m.*, v.type, v.model FROM maintenance_approvals m JOIN vehicles v ON m.vehicle_id = v.id ORDER BY m.arrival_date DESC");
$usage_logs_result = $conn->query("SELECT u.*, v.type, v.model FROM usage_logs u JOIN vehicles v ON u.vehicle_id = v.id ORDER BY u.log_date DESC");
$drivers_result = $conn->query("SELECT id, name FROM drivers WHERE status = 'Active'");
$all_vehicles = $conn->query("SELECT id, type, model FROM vehicles ORDER BY type, model");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fleet & Vehicle Management | LOGISTICS II</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
  <div class="sidebar" id="sidebar">
    <div class="logo"><img src="logo.png" alt="SLATE Logo"></div>
    <div class="system-name">LOGISTIC 2</div>

    <a href="landpage.php" class="<?php echo ($current_page == 'landpage.php') ? 'active' : ''; ?>">Dashboard</a>

    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff'): ?>
        <div class="dropdown <?php echo ($current_page == 'FVM.php') ? 'active' : ''; ?>">
            <a href="FVM.php" class="dropdown-toggle">Fleet & Vehicle Management (FVM)</a>
            <div class="dropdown-menu">
                <a href="FVM.php#vehicle-list">Vehicle List</a>
                <a href="FVM.php#maintenance-approval">Maintenance Approval</a>
                <a href="FVM.php#usage-logs">Usage Logs</a>
            </div>
        </div>
        <a href="VRDS.php" class="<?php echo ($current_page == 'VRDS.php') ? 'active' : ''; ?>">Vehicle Reservation & Dispatch System (VRDS)</a>
        <a href="DTPM.php" class="<?php echo ($current_page == 'DTPM.php' || $current_page == 'trip_history.php') ? 'active' : ''; ?>">Driver & Trip Performance Monitoring</a>
        <a href="TCAO.php" class="<?php echo ($current_page == 'TCAO.php') ? 'active' : ''; ?>">Transport Cost Analysis & Optimization (TCAO)</a>
    <?php endif; ?>
    
    <a href="MA.php" class="<?php echo ($current_page == 'MA.php') ? 'active' : ''; ?>">Mobile Fleet Command App</a>
    <a href="logout.php">Logout</a>
  </div>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Fleet & Vehicle Management</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <?php echo $message; ?>

    <div class="table-section" id="vehicle-list">
      <h3>Vehicle List</h3>
      <div class="search-container">
        <form action="FVM.php" method="GET" style="display: flex; gap: 0.5rem;">
          <input type="text" name="query" class="search-box" placeholder="Search vehicle or driver..." value="<?php echo htmlspecialchars($search_query); ?>">
          <button type="submit" class="btn btn-secondary">Search</button>
        </form>
      </div>

      <table>
        <thead>
          <tr>
            <th>ID</th><th>VEHICLE TYPE</th><th>MODEL</th><th>TAG CODE</th><th>LOAD CAP.</th><th>PLATE NO</th><th>ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <?php if($vehicles_result->num_rows > 0): ?>
            <?php while($row = $vehicles_result->fetch_assoc()): ?>
            <tr>
              <td><?php echo $row['id']; ?></td>
              <td><?php echo htmlspecialchars($row['type']); ?></td>
              <td><?php echo htmlspecialchars($row['model']); ?></td>
              <td><?php echo htmlspecialchars($row['tag_code']); ?></td>
              <td><?php echo htmlspecialchars($row['load_capacity_kg']); ?> kg</td>
              <td><?php echo htmlspecialchars($row['plate_no']); ?></td>
              <td>
                <button class="btn btn-info btn-sm viewVehicleBtn"
                  data-id="<?php echo $row['id']; ?>"
                  data-type="<?php echo htmlspecialchars($row['type']); ?>"
                  data-model="<?php echo htmlspecialchars($row['model']); ?>"
                  data-tag_type="<?php echo htmlspecialchars($row['tag_type']); ?>"
                  data-tag_code="<?php echo htmlspecialchars($row['tag_code']); ?>"
                  data-load_capacity_kg="<?php echo htmlspecialchars($row['load_capacity_kg']); ?>"
                  data-plate_no="<?php echo htmlspecialchars($row['plate_no']); ?>">View</button>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7">No vehicles found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <div class="table-section-2" id="maintenance-approval">
      <h3>Maintenance Approval</h3>
      <table>
        <thead>
          <tr>
            <th>VEHICLE NAME</th>
            <th>ARRIVAL DATE</th>
            <th>DATE OF RETURN</th>
            <th>STATUS</th>
            <th>ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <?php if($maintenance_result->num_rows > 0): ?>
            <?php while($row = $maintenance_result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
              <td><?php echo htmlspecialchars($row['arrival_date']); ?></td>
              <td><?php echo htmlspecialchars($row['date_of_return'] ?? 'N/A'); ?></td>
              <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '.', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
              <td>
                <?php if ($row['status'] == 'Pending'): ?>
                    <form action="FVM.php" method="POST" style="display: inline;">
                        <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="new_status" value="Approved">
                        <button type="submit" name="update_maintenance_status" class="btn btn-success btn-sm">Approve</button>
                    </form>
                <?php elseif ($row['status'] == 'Approved'): ?>
                    <form action="FVM.php" method="POST" style="display: inline;">
                        <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="new_status" value="In Progress">
                        <button type="submit" name="update_maintenance_status" class="btn btn-info btn-sm">In Progress</button>
                    </form>
                    <form action="FVM.php" method="POST" style="display: inline;">
                        <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="new_status" value="Completed">
                        <button type="submit" name="update_maintenance_status" class="btn btn-primary btn-sm">Mark as Done</button>
                    </form>
                <?php elseif ($row['status'] == 'In Progress'): ?>
                    <form action="FVM.php" method="POST" style="display: inline;">
                        <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="new_status" value="Completed">
                        <button type="submit" name="update_maintenance_status" class="btn btn-primary btn-sm">Mark as Done</button>
                    </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5">No maintenance requests found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="table-section-2" id="usage-logs">
      <h3>Usage Logs</h3>
      <table>
        <thead>
          <tr>
            <th>VEHICLE NAME</th>
            <th>DATE</th>
            <th>METRICS</th>
            <th>FUEL (L)</th>
            <th>MILEAGE (km)</th>
            <th>ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <?php if($usage_logs_result->num_rows > 0): ?>
            <?php while($row = $usage_logs_result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
              <td><?php echo htmlspecialchars($row['log_date']); ?></td>
              <td><?php echo htmlspecialchars($row['metrics']); ?></td>
              <td><?php echo htmlspecialchars($row['fuel_usage']); ?></td>
              <td><?php echo htmlspecialchars($row['mileage']); ?></td>
              <td>
                <!-- Actions removed as requested -->
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6">No usage logs found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Vehicle Modal -->
  <div id="vehicleModal" class="modal">
    <div class="modal-content">
      <span class="close-button">&times;</span>
      <h2 id="modalTitle">Update Vehicle</h2>
      <form action="FVM.php" method="post">
        <input type="hidden" id="vehicle_id" name="vehicle_id">
        <div class="form-group"><label for="type">Type</label><input type="text" id="type" name="type" class="form-control" required></div>
        <div class="form-group"><label for="model">Model</label><input type="text" id="model" name="model" class="form-control" required></div>
        <div class="form-group"><label for="plate_no">Plate No.</label><input type="text" id="plate_no" name="plate_no" class="form-control" required></div>
        <div class="form-group"><label for="tag_type">Tag Type</label><select id="tag_type" name="tag_type" class="form-control" required><option value="RFID">RFID</option><option value="Barcode">Barcode</option><option value="QR Code">QR Code</option></select></div>
        <div class="form-group"><label for="tag_code">Tag Code</label><input type="text" id="tag_code" name="tag_code" class="form-control" required></div>
        <div class="form-group"><label for="load_capacity_kg">Load Capacity (kg)</label><input type="number" id="load_capacity_kg" name="load_capacity_kg" class="form-control" required></div>
        <div class="form-actions"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="save_vehicle" class="btn btn-primary">Save Vehicle</button></div>
      </form>
    </div>
  </div>
   
  <!-- Maintenance Modal -->
  <div id="maintenanceModal" class="modal">
    <div class="modal-content">
      <span class="close-button">&times;</span>
      <h2 id="maintenanceModalTitle">Add Maintenance Request</h2>
      <form action="FVM.php" method="post">
        <input type="hidden" id="maintenance_id" name="maintenance_id">
        <div class="form-group"><label for="maintenance_vehicle_id">Vehicle</label><select id="maintenance_vehicle_id" name="vehicle_id" class="form-control" required><option value="">-- Select Vehicle --</option><?php mysqli_data_seek($all_vehicles, 0); while($v = $all_vehicles->fetch_assoc()){ echo "<option value='{$v['id']}'>".htmlspecialchars($v['type'].' - '.$v['model'])."</option>"; } ?></select></div>
        <div class="form-group"><label for="arrival_date">Arrival Date</label><input type="date" id="arrival_date" name="arrival_date" class="form-control" required></div>
        <div class="form-group"><label for="date_of_return">Date of Return</label><input type="date" id="date_of_return" name="date_of_return" class="form-control"></div>
        <div class="form-group"><label for="maintenance_status">Status</label><select id="maintenance_status" name="status" class="form-control" required><option value="Pending">Pending</option><option value="Approved">Approved</option><option value="In Progress">In Progress</option><option value="Completed">Completed</option><option value="Rejected">Rejected</option></select></div>
        <div class="form-actions"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="save_maintenance" class="btn btn-primary">Save Request</button></div>
      </form>
    </div>
  </div>

  <!-- Usage Log Modal -->
  <div id="usageLogModal" class="modal">
    <div class="modal-content">
      <span class="close-button">&times;</span>
      <h2 id="usageLogModalTitle">Add Usage Log</h2>
      <form action="FVM.php" method="post">
        <input type="hidden" id="log_id" name="log_id">
        <div class="form-group"><label for="usage_vehicle_id">Vehicle</label><select id="usage_vehicle_id" name="vehicle_id" class="form-control" required><option value="">-- Select Vehicle --</option><?php mysqli_data_seek($all_vehicles, 0); while($v = $all_vehicles->fetch_assoc()){ echo "<option value='{$v['id']}'>".htmlspecialchars($v['type'].' - '.$v['model'])."</option>"; } ?></select></div>
        <div class="form-group"><label for="log_date">Date</label><input type="date" id="log_date" name="log_date" class="form-control" required></div>
        <div class="form-group"><label for="metrics">Metrics</label><input type="text" id="metrics" name="metrics" class="form-control" placeholder="e.g., Daily Checkup" required></div>
        <div class="form-group"><label for="fuel_usage">Fuel Usage (Liters)</label><input type="number" step="0.01" id="fuel_usage" name="fuel_usage" class="form-control" required></div>
        <div class="form-group"><label for="mileage">Mileage (km)</label><input type="number" id="mileage" name="mileage" class="form-control" required></div>
        <div class="form-actions"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="save_usage_log" class="btn btn-primary">Save Log</button></div>
      </form>
    </div>
  </div>

  <!-- View Vehicle Modal -->
  <div id="viewVehicleModal" class="modal">
    <div class="modal-content">
      <span class="close-button">&times;</span>
      <h2>View Vehicle Details</h2>
      <div id="viewVehicleBody" style="line-height: 1.8;">
        <!-- Details will be populated by JS -->
      </div>
       <div class="form-actions">
           <button type="button" class="btn btn-secondary cancelBtn">Close</button>
       </div>
    </div>
  </div>

  <script>
    document.getElementById('themeToggle').addEventListener('change', function() { document.body.classList.toggle('dark-mode', this.checked); });
    document.getElementById('hamburger').addEventListener('click', function() {
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      if (window.innerWidth <= 992) { sidebar.classList.toggle('show'); } 
      else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('expanded'); }
    });

    document.querySelectorAll('.modal').forEach(modal => {
        const closeBtn = modal.querySelector('.close-button');
        const cancelBtn = modal.querySelector('.cancelBtn');
        closeBtn.addEventListener('click', () => modal.style.display = 'none');
        if(cancelBtn) { cancelBtn.addEventListener('click', () => modal.style.display = 'none'); }
        window.addEventListener('click', (event) => { if (event.target == modal) { modal.style.display = 'none'; } });
    });

    const vehicleModal = document.getElementById('vehicleModal');
    
    document.querySelectorAll('.editVehicleBtn').forEach(button => {
      button.addEventListener('click', () => {
        vehicleModal.querySelector('form').reset();
        vehicleModal.querySelector('#modalTitle').textContent = 'Update Vehicle';
        vehicleModal.querySelector('#vehicle_id').value = button.dataset.id;
        vehicleModal.querySelector('#type').value = button.dataset.type;
        vehicleModal.querySelector('#model').value = button.dataset.model;
        vehicleModal.querySelector('#plate_no').value = button.dataset.plate_no;
        vehicleModal.querySelector('#tag_type').value = button.dataset.tag_type;
        vehicleModal.querySelector('#tag_code').value = button.dataset.tag_code;
        vehicleModal.querySelector('#load_capacity_kg').value = button.dataset.load_capacity_kg;
        vehicleModal.style.display = 'block';
      });
    });

    const viewVehicleModal = document.getElementById('viewVehicleModal');
    const viewVehicleBody = document.getElementById('viewVehicleBody');
    document.querySelectorAll('.viewVehicleBtn').forEach(button => {
      button.addEventListener('click', () => {
        const model = button.dataset.model.toLowerCase();
        const type = button.dataset.type.toLowerCase();
        let imageUrl;

        if (model.includes('elf')) {
            imageUrl = `elf.PNG`;
        } else if (model.includes('hiace')) {
            imageUrl = `hiace.PNG`;
        } else if (model.includes('canter')) {
            imageUrl = `canter.PNG`;
        } else if (type.includes('container truck')) {
            imageUrl = 'https://placehold.co/400x300/e74a3b/white?text=Container+Truck';
        } else if (type.includes('trailer truck')) {
            imageUrl = 'https://placehold.co/400x300/f6c23e/white?text=Trailer+Truck';
        } else if (type.includes('box truck')) {
             imageUrl = 'https://placehold.co/400x300/36b9cc/white?text=Box+Truck';
        } else if (type.includes('truck')) {
            imageUrl = `canter.PNG`; // Generic for other trucks
        } else {
            imageUrl = 'https://placehold.co/400x300/e2e8f0/e2e8f0?text=Walang+Larawan';
        }

        const detailsHtml = `
            <img src="${imageUrl}" alt="${button.dataset.type}" style="width: 100%; height: auto; max-height: 250px; object-fit: cover; border-radius: 0.35rem; margin-bottom: 1rem;">
            <p><strong>ID:</strong> ${button.dataset.id}</p>
            <p><strong>Type:</strong> ${button.dataset.type}</p>
            <p><strong>Model:</strong> ${button.dataset.model}</p>
            <p><strong>Plate No.:</strong> ${button.dataset.plate_no}</p>
            <p><strong>Tag Type:</strong> ${button.dataset.tag_type}</p>
            <p><strong>Tag Code:</strong> ${button.dataset.tag_code}</p>
            <p><strong>Load Capacity:</strong> ${button.dataset.load_capacity_kg} kg</p>
        `;
        viewVehicleBody.innerHTML = detailsHtml;
        viewVehicleModal.style.display = 'block';
      });
    });

    const maintenanceModal = document.getElementById('maintenanceModal');
    
    document.querySelectorAll('.editMaintenanceBtn').forEach(button => {
      button.addEventListener('click', () => {
        maintenanceModal.querySelector('form').reset();
        maintenanceModal.querySelector('#maintenanceModalTitle').textContent = 'Edit Maintenance Request';
        maintenanceModal.querySelector('#maintenance_id').value = button.dataset.id;
        maintenanceModal.querySelector('#maintenance_vehicle_id').value = button.dataset.vehicle_id;
        maintenanceModal.querySelector('#arrival_date').value = button.dataset.arrival_date;
        maintenanceModal.querySelector('#date_of_return').value = button.dataset.date_of_return;
        maintenanceModal.querySelector('#maintenance_status').value = button.dataset.status;
        maintenanceModal.style.display = 'block';
      });
    });

    const usageLogModal = document.getElementById('usageLogModal');
    
    document.querySelectorAll('.editUsageLogBtn').forEach(button => {
      button.addEventListener('click', () => {
        usageLogModal.querySelector('form').reset();
        usageLogModal.querySelector('#usageLogModalTitle').textContent = 'Edit Usage Log';
        usageLogModal.querySelector('#log_id').value = button.dataset.id;
        usageLogModal.querySelector('#usage_vehicle_id').value = button.dataset.vehicle_id;
        usageLogModal.querySelector('#log_date').value = button.dataset.log_date;
        usageLogModal.querySelector('#metrics').value = button.dataset.metrics;
        usageLogModal.querySelector('#fuel_usage').value = button.dataset.fuel_usage;
        usageLogModal.querySelector('#mileage').value = button.dataset.mileage;
        usageLogModal.style.display = 'block';
      });
    });
    
    // --- Sidebar Dropdown Logic ---
    document.addEventListener('DOMContentLoaded', function() {
        // This script handles the sidebar dropdown functionality.
        const activeDropdown = document.querySelector('.sidebar .dropdown.active');
        if (activeDropdown) {
            activeDropdown.classList.add('open');
            activeDropdown.querySelector('.dropdown-menu').style.display = 'block';
        }

        document.querySelectorAll('.sidebar .dropdown .dropdown-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                let parent = this.closest('.dropdown');
                
                // If we are not on the FVM page, let the link work normally.
                if (!parent.classList.contains('active')) {
                    return;
                }
                
                // If we are on the active page, prevent navigation and toggle the menu.
                e.preventDefault();
                
                parent.classList.toggle('open');
                let menu = parent.querySelector('.dropdown-menu');

                if (menu.style.display === "block") {
                    menu.style.display = "none";
                } else {
                    menu.style.display = "block";
                }
            });
        });
    });
  </script>
</body>
</html>

