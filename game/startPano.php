<?php


$pano = $_POST["pano"];
if (!isset($pano) || empty($pano)) {
    exit();
}
$token = $_POST["token"];


include "../vars.php";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://maps.googleapis.com/maps/api/streetview/metadata?key=" . $googleApiKey . "&pano=" . $pano);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

$json = json_decode($result, true);


if ($json["status"] === "OK") {
    $location = $json["location"];
    $lat = $location["lat"];
    $lng = $location["lng"];

    include "../db_stuff.php";

    $stmt = $conn->prepare("UPDATE geoguesser_games SET startLat=?, startLng = ? WHERE token = ? AND startLat is NULL AND startLng is NULL");
    $stmt->bind_param("dds",  $lat,$lng,$token);
    $stmt->execute();
    $stmt->close();
    unset($stmt);
    $conn->close();

}
