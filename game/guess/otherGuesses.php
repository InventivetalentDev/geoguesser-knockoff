<?php

$gameid = $_GET["gameid"];
if ( !isset($gameid)) {
    exit();
}

$selfGuessId = $_GET["selfGuess"];
if (!isset($selfGuessId)) {
    exit();
}

include "../../db_stuff.php";

$guesses = array();

$stmt = $conn->prepare("SELECT id,game,lat,lng,distance,time FROM geoguesser_guesses WHERE game = ? AND id != ?");
$stmt->bind_param("ii", $gameid,$selfGuessId);
$stmt->execute();
$stmt->bind_result($id,$game,$lat,$lng,$distance,$time);
while ($stmt->fetch()) {
    $guesses[]=array(
        "lat"=>$lat,
        "lng"=>$lng,
        "distance"=>$distance,
        "time"=>$time
    );
}
$stmt->close();
unset($stmt);
$conn->close();


header("Content-Type: application/json");
echo json_encode(array("guesses"=>$guesses));
