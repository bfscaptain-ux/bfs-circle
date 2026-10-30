<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../config.php';
require_role(['Staff']);

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Get staff's registered address to zoom into their approximate area
$stmt = $db->prepare("SELECT current_address FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff_city = $stmt->fetchColumn();
if (!$staff_city) $staff_city = 'India';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=3">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=3">
    <link rel="shortcut icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=3">
    <link rel="apple-touch-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Route - BFS Financial Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: #F1F5F9; color: #0F172A; }
        .header { background: #FF7A00; padding: 15px 20px; color: white; display: flex; align-items: center; gap: 15px; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .header a { color: white; text-decoration: none; font-size: 20px; }
        .header h1 { font-size: 18px; font-weight: 600; }
        #map { width: 100%; height: calc(100vh - 54px); }
        .stats-overlay { position: fixed; bottom: 20px; left: 20px; right: 20px; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 1000; display: flex; justify-content: space-between; align-items: center; }
        .stat { text-align: center; }
        .stat i { color: #FF7A00; font-size: 20px; margin-bottom: 5px; }
        .stat-val { font-size: 16px; font-weight: 700; color: #0F172A; }
        .stat-lbl { font-size: 11px; color: #64748B; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <div class="header">
        <a href="index.php"><i class="fas fa-arrow-left"></i></a>
        <h1>My Today's Route</h1>
    </div>
    
    <div id="map"></div>
    
    <div class="stats-overlay">
        <div class="stat">
            <i class="fas fa-route"></i>
            <div class="stat-val" id="total_dist">0 km</div>
            <div class="stat-lbl">Distance</div>
        </div>
        <div class="stat">
            <i class="fas fa-battery-half" id="bat-icon"></i>
            <div class="stat-val" id="my_battery">--%</div>
            <div class="stat-lbl">Battery</div>
        </div>
        <div class="stat">
            <i class="fas fa-signal"></i>
            <div class="stat-val" id="my_status">Active</div>
            <div class="stat-lbl">Status</div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Fetch staff city from PHP
        const staffCity = "<?php echo addslashes($staff_city); ?>";
        
        // Default to India
        const map = L.map('map').setView([20.5937, 78.9629], 5);
        
        // Auto-center map to the Staff's Registered City so it looks clean initially
        if (staffCity && staffCity.toLowerCase() !== 'india') {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(staffCity + ', India')}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.length > 0) {
                        // Fly to the registered city at a nice zoom level (12)
                        map.setView([data[0].lat, data[0].lon], 12);
                    }
                }).catch(e => console.log('Geocoding error:', e));
        }
        
        // As soon as page loads, try to find the user's exact current city/street
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                const lat = pos.coords.latitude;
                const lon = pos.coords.longitude;
                // Fly directly to the staff's current city/street with high zoom (15)
                map.flyTo([lat, lon], 15, {
                    animate: true,
                    duration: 1.5
                });
                
                // Add a pulse marker for their exact current location
                L.circleMarker([lat, lon], {
                    radius: 8,
                    fillColor: "#3b82f6",
                    color: "#ffffff",
                    weight: 3,
                    opacity: 1,
                    fillOpacity: 0.9
                }).addTo(map).bindPopup("You are here").openPopup();
            }, err => {
                console.log("Could not get initial location for zoom.");
            });
        }
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a>'
        }).addTo(map);

        let myPolyline = L.polyline([], {color: '#FF7A00', weight: 4, opacity: 0.8}).addTo(map);
        let currentMarker = null;

        function fetchMyRoute() {
            fetch('../?api=get_online_staff')
                .then(r => r.json())
                .then(data => {
                    const me = data.staff.find(s => s.username === '<?php echo $username; ?>');
                    if (me) {
                        document.getElementById('my_battery').innerText = me.battery || '--%';
                        document.getElementById('my_status').innerText = me.status || 'Offline';
                        document.getElementById('total_dist').innerText = me.distance_km + ' km';
                        
                        let batIcon = 'fa-battery-full';
                        let batVal = parseInt(me.battery);
                        if (batVal < 20) batIcon = 'fa-battery-empty';
                        else if (batVal < 50) batIcon = 'fa-battery-quarter';
                        else if (batVal < 80) batIcon = 'fa-battery-half';
                        document.getElementById('bat-icon').className = 'fas ' + batIcon;
                        
                        if (me.route && me.route.length > 0) {
                            const latlngs = me.route.map(p => [parseFloat(p.lat), parseFloat(p.lon)]);
                            myPolyline.setLatLngs(latlngs);
                            
                            const lastPt = latlngs[latlngs.length - 1];
                            if (!currentMarker) {
                                currentMarker = L.marker(lastPt).addTo(map);
                            } else {
                                currentMarker.setLatLng(lastPt);
                            }
                            map.fitBounds(myPolyline.getBounds(), {padding: [50, 50]});
                        }
                    }
                });
        }
        
        fetchMyRoute();
        setInterval(fetchMyRoute, 30000);
    </script>
</body>
</html>
