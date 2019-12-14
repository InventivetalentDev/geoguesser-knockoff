<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "../db_stuff.php";

function generateRandomString($length = 16) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/// https://assemblysys.com/php-point-in-polygon-algorithm/
function pointInPolygon($point, $polygon, $pointOnVertex = false) {

    $vertices = array();
    foreach ($polygon as $vertex) {
        $vertices[] = array("x"=>$vertex[0],"y"=>$vertex[1]);
    }

    // Check if the point sits exactly on a vertex
//    if ($pointOnVertex == true and $this->pointOnVertex($point, $vertices) == true) {
//        return "vertex";
//    }

    // Check if the point is inside the polygon or on the boundary
    $intersections = 0;
    $vertices_count = count($vertices);

    for ($i=1; $i < $vertices_count; $i++) {
        $vertex1 = $vertices[$i-1];
        $vertex2 = $vertices[$i];
        if ($vertex1['y'] == $vertex2['y'] and $vertex1['y'] == $point['y'] and $point['x'] > min($vertex1['x'], $vertex2['x']) and $point['x'] < max($vertex1['x'], $vertex2['x'])) { // Check if point is on an horizontal polygon boundary
            return "boundary";
        }
        if ($point['y'] > min($vertex1['y'], $vertex2['y']) and $point['y'] <= max($vertex1['y'], $vertex2['y']) and $point['x'] <= max($vertex1['x'], $vertex2['x']) and $vertex1['y'] != $vertex2['y']) {
            $xinters = ($point['y'] - $vertex1['y']) * ($vertex2['x'] - $vertex1['x']) / ($vertex2['y'] - $vertex1['y']) + $vertex1['x'];
            if ($xinters == $point['x']) { // Check if point is on the polygon boundary (other than horizontal)
                return "boundary";
            }
            if ($vertex1['x'] == $vertex2['x'] || $point['x'] <= $xinters) {
                $intersections++;
            }
        }
    }
    // If the number of edges we passed through is odd, then it's in the polygon.
    if ($intersections % 2 != 0) {
        return "inside";
    } else {
        return "outside";
    }
}

function isPointOnLand($point, $geojson){
    $features = $geojson["features"];
    for($i = 0;$i<count($features);$i++){
        $feature = $features[$i];
        $geometry = $feature["geometry"];
        $coordinates = $geometry["coordinates"];
        if($geometry["type"]==="MultiPolygon"){
            for($j = 0;$j<count($coordinates);$j++){
                for($k = 0;$k<count($coordinates[$j]);$k++){
                    $test = pointInPolygon($point,$coordinates[$j][$k]);
//                    echo $test;
                    if ($test !== "outside") {
                        return true;
                    }
                }
            }
        }
        if($geometry["type"]==="Polygon"){
            for($j = 0;$j<count($coordinates);$j++){
                $test = pointInPolygon($point,$coordinates[$j]);
//                echo $test;
                if ($test !== "outside") {
                    return true;
                }
            }
        }
    }
    return false;
}

if (isset($_GET["g"])) {
    $token = $_GET["g"];

    $stmt = $conn->prepare("SELECT id,lat,lng,creation_time FROM geoguesser_games WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($id, $lat,$lng,$creation_time);
    $stmt->fetch();
     $stmt->close();
    unset($stmt);
    $conn->close();


}else{

    echo "Generating new Location...";

    $geojson = json_decode(file_get_contents(__DIR__."/../data/ne_10m_land.json"),true);

    $latMin = -60;
    $latMax = 80;
    $lngMin = -170;
    $lngMax = 170;

    $lat = rand($latMin*1000.0,$latMax*1000.0)/1000.0;
    $lng = rand($lngMin*1000.0,$lngMax*1000.0)/1000.0;
    $c = 0;
    while (!isPointOnLand(array("x"=>$lng,"y"=>$lat), $geojson)) {
        $lat = rand($latMin*1000.0,$latMax*1000.0)/1000.0;
        $lng = rand($lngMin*1000.0,$lngMax*1000.0)/1000.0;
        $c++;
    }


    $token = generateRandomString(16);

    $date = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("INSERT INTO geoguesser_games (token,lat,lng,creation_time) VALUES(?,?,?,?)");
    $stmt->bind_param("sdds", $token,$lat,$lng,$date);
    $stmt->execute();
    $stmt->close();
    unset($stmt);
    $conn->close();

//    echo $token;

    header("Location: /game?g=" . $token."&c=".$c);
    exit();
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Haylee's Geoguesser Knockoff</title>
        <style>

            /* Optional: Makes the sample page fill the window. */
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
            }

            #pano {
                width: 100%;
                height: 100%;
                float: left;
            }
            #map{
                width: 100%;
                height: 100%;
            }
            #mapAndGuessOverlay{
                position:absolute;
                right:40px;
                bottom: 40px;
                z-index:9999;
                width: 30%;
                height: 30%;
            }
        </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css"
              integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ=="
              crossorigin=""/>
        <!--        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA6mCaC_pAXzUTg1aPoXCQFhhwaQ95I4UQ"></script>-->
    </head>
    <body>
        <div id="pano"></div>
        <div id="mapAndGuessOverlay">
            <div id="map"></div>
            <button class="btn" id="guessBtn" disabled>Guess</button>
        </div>


        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js" integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
        <script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js" integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og==" crossorigin=""></script>
        <script>
            let token = '<?php echo $token; ?>';
            let gameId = <?php echo $id; ?>;


            var panorama;

            function initPano() {
                var sv = new google.maps.StreetViewService();
                panorama = new google.maps.StreetViewPanorama(
                    document.getElementById('pano'), {
                        position: {lat:0,lng:0},
                        pov: {
                            heading: 270,
                            pitch: 0
                        },
                        visible: true,
                        addressControl: false,
                        showRoadLabels: false,
                        fullscreenControl: false,
                        zoomControlOptions: {
                            position: google.maps.ControlPosition.LEFT_BOTTOM
                        }
                    });

                console.log(JSON.parse(atob('<?php echo base64_encode(json_encode(array("lat"=>(float)$lat,"lng"=>(float)$lng))); ?>')));
                sv.getPanorama({
                    location:JSON.parse(atob('<?php echo base64_encode(json_encode(array("lat"=>(float)$lat,"lng"=>(float)$lng))); ?>')),
                    radius:20000,
                    source:"outdoor",
                    preference:"best"
                },function (data,status) {
                    console.log(status)
                    // console.log(data)
                    if(status==="OK") {
                        panorama.setPano(data.location.pano);
                    }
                })

                panorama.addListener('pano_changed', function () {
                });

                panorama.addListener('links_changed', function () {
                });

                panorama.addListener('position_changed', function () {
                    console.log("Pos: " + panorama.getPosition())
                });

                panorama.addListener('pov_changed', function () {
                });


            }

            let marker;
            function initMap() {
                let map  = new L.Map('map',{
                });
                let osm = new L.TileLayer('https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png', {
                    minZoom: 2,
                    maxZoom: 20,
                    attribution: 'Map data © <a href="https://openstreetmap.org">OpenStreetMap</a> contributors'});
                map.addLayer(osm);
                map.setView(new L.LatLng(0,0), 1);

                marker= new L.Marker([0,0],{
                    opacity:0,
                    icon:L.icon({
                        iconUrl:"/img/self_marker_24px.svg",
                        iconSize:new L.Point(24,24)
                    })
                });
                marker.addTo(map);

                map.on("click",function (e) {
                    marker.setLatLng(e.latlng);
                    marker.setOpacity(1);

                    $("#guessBtn").prop("disabled", false);
                })
            }
            initMap();

            $("#guessBtn").on("click", function (e) {
                e.preventDefault();

                let latLng = marker.getLatLng();
                let lat = latLng.lat;
                let lng = latLng.lng;
                if(lat!==0&&lng!==0) {
                    window.location = "guess/?token="+token+"&gameid="+gameId+"&pano=" + panorama.getPano() + "&lat=" + lat + "&lng=" + lng
                }
                // $.post("guess.php", {
                //     pano: panorama.getPano(),
                //     lat: latLng.lat,
                //     lon: latLng.lng
                // }, function (data, status) {
                //     console.log(data);
                // })
            })
        </script>
        <script
            src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA6mCaC_pAXzUTg1aPoXCQFhhwaQ95I4UQ&callback=initPano"
            async defer>
        </script>
    </body>
</html>

