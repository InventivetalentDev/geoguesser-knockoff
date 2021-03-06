<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$token = $_GET["token"];
$gameid = $_GET["gameid"];
if (empty($token) || !isset($gameid)) {
    exit();
}

$pano = $_GET["pano"];
if (!isset($pano) || empty($pano)) {
    exit();
}
$guessLat = floatval($_GET["lat"]);
$guessLon = floatval($_GET["lng"]);


include "../../db_stuff.php";

$stmt = $conn->prepare("SELECT id,startLat,startLng,lat,lng,creation_time FROM geoguesser_games WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->bind_result($dbGameId, $startLat, $startLng, $fallbackLat,$fallbackLng, $creation_time);
$valid = $stmt->fetch();
$stmt->close();
unset($stmt);


if (empty($startLat) || $startLat == 0) {
    $startLat= $fallbackLat;
}
if (empty($startLng) || $startLng == 0) {
    $startLng = $fallbackLng;
}

if (!$valid) {
    $conn->close();
    exit("invalid game");
}

include "../../vars.php";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://maps.googleapis.com/maps/api/streetview/metadata?key=" . $googleApiKey . "&pano=" . $pano);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

$json = json_decode($result, true);


function measure($lat1, $lon1, $lat2, $lon2)
{
    $PI = 3.14159265359;
    $R = 6378.137; // Radius of earth in KM
    $dLat = $lat2 * $PI / 180 - $lat1 * $PI / 180;
    $dLon = $lon2 * $PI / 180 - $lon1 * $PI / 180;
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos($lat1 * $PI / 180) * cos($lat2 * $PI / 180) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $d = $R * $c;
    return $d * 1000; // meters
}

if ($json["status"] === "OK") {


    $location = $json["location"];
    $diffCoords = array(
        "lat" => abs($guessLat - $location["lat"]),
        "lng" => abs($guessLon - $location["lng"])
    );
    $distance = measure($guessLat, $guessLon, $location["lat"], $location["lng"]);


    $date = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("INSERT INTO geoguesser_guesses (game,lat,lng,time,distance) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE time=?");
    $stmt->bind_param("sddsds", $dbGameId, $guessLat, $guessLon, $date, $distance,$date);
    $stmt->execute();
    $last_id = $conn->insert_id;
    $stmt->close();
    unset($stmt);


    $res = array(
        "status" => "OK",
        "guess" => array(
            "lat" => $guessLat,
            "lng" => $guessLon
        ),
        "actual" => $location,
        "start"=>array(
            "lat"=>$startLat,
            "lng"=>$startLng
        ),
        "diff" => $diffCoords,
        "distanceInMeters" => $distance,
        "distanceInKilometers" => ($distance / 1000),
        "copyright" => $json["copyright"]
    );
} else {
    $res = $json;
}

$conn->close();

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

            #map {
                width: 100%;
                height: 100%;
            }
        </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css"
              integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ=="
              crossorigin=""/>
        <!--        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA6mCaC_pAXzUTg1aPoXCQFhhwaQ95I4UQ"></script>-->
    </head>
    <body>
            <div id="map"></div>

        <a href="/game" class="btn" style="position: absolute; bottom: 40px; right: 40px; z-index: 9999;">Play Again!</a>


        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js" integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
        <script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js" integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og==" crossorigin=""></script>
        <script>
            <?php
            if ($res["status"] === "OK") {
                echo "let res = " . json_encode($res) . ";";
            }
            ?>
            let gameId = <?php echo $dbGameId; ?>;
            let guessId = <?php echo $last_id; ?>;


            let map;
            function initMap() {
                map = new L.Map('map', {});
                let osm = new L.TileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    subdomains:"abc",
                    minZoom: 2,
                    maxZoom: 20,
                    attribution: 'Map data © <a href="https://openstreetmap.org">OpenStreetMap</a> contributors'
                });
                map.addLayer(osm);
                map.setView(new L.LatLng(res.guess.lat, res.guess.lng), 10);


                let guessMarker = new L.Marker([res.guess.lat, res.guess.lng], {
                    icon: L.icon({
                        iconUrl: "/img/self_marker_24px.svg",
                        iconSize: new L.Point(24, 24)
                    })
                });
                guessMarker .bindTooltip("Your Guess",
                    {
                        permanent: true,
                        direction: 'right',
                        offset: new L.Point(10,0)
                    });
                guessMarker.addTo(map);

                let startMarker = new L.Marker([res.start.lat, res.start.lng], {
                    icon: L.icon({
                        iconUrl: "/img/play-circle-solid.svg",
                        iconSize: new L.Point(24, 24)
                    })
                });
                startMarker .bindTooltip("Start",
                    {
                        permanent: true,
                        direction: 'right',
                        offset: new L.Point(10,0)
                    });
                startMarker.addTo(map);

                let targetMarker = new L.Marker([res.actual.lat, res.actual.lng], {
                    icon: L.icon({
                        iconUrl: "/img/flag-solid.svg",
                        iconSize: new L.Point(24, 24)
                    })
                });
                targetMarker .bindTooltip("Last Location",
                    {
                        permanent: true,
                        direction: 'right',
                        offset: new L.Point(10,0)
                    });
                targetMarker.addTo(map);

                let dots = [
                    [res.actual.lat, res.actual.lng],
                    [res.guess.lat, res.guess.lng],
                ];
                if(res.actual.lat!==res.start.lat&&res.actual.lng!==res.start.lng){
                    dots.unshift([res.start.lat, res.start.lng]);
                }else{
                    startMarker.setOpacity(0);
                }
                L.polyline(dots).bindTooltip((Math.round(res.distanceInKilometers*100.0)/100.0)+"km",{
                    permanent: true,
                    direction:"left",
                    offset: new L.Point(-5,0)
                }).addTo(map);

            }

            if (res) {
                initMap();
            }


            let otherGuessCount =0;
            function updateOtherGuesses(guesses) {
                console.log(guesses)
                let diff = guesses.length-otherGuessCount;
                if (diff > 0) {
                    for (let i = 0; i < diff; i++) {
                        console.log(guesses.length - i-1)
                        let guess = guesses[guesses.length - i-1];
                        console.log(guess)
                        let otherMarker = new L.Marker([guess.lat, guess.lng], {
                            opacity:0.3,
                            icon: L.icon({
                                iconUrl: "/img/self_marker_24px.svg",
                                iconSize: new L.Point(24, 24)
                            })
                        });
                        otherMarker .bindTooltip("Other Player Guess",
                            {
                                permanent: true,
                                direction: 'right',
                                offset: new L.Point(10,0)
                            });
                        otherMarker.addTo(map);

                        otherGuessCount++;
                    }
                }
            }

            function doOtherGuessUpdate() {
                $.get("otherGuesses.php?gameid="+gameId+"&selfGuess="+guessId,function (data) {
                    updateOtherGuesses(data.guesses)
                })
            }
            setInterval(doOtherGuessUpdate, 30000);
            doOtherGuessUpdate();

        </script>
    </body>
</html>


