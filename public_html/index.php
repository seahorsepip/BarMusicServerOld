<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SimpleCrud\SimpleCrud;

require "../vendor/autoload.php";

$pdo = new PDO("mysql:host=localhost;dbname=barzo", "root", "<snip>");

$container = new Slim\Container();
$container["db"] = function () use ($pdo) {
    $db = new SimpleCrud($pdo);
    return $db;
};

$app = new Slim\App($container);

/*
 * Get current song queue ordered by votes
 *
 * Current ORM should be replaced with a more advanced orm that allows nested queries of some sort
 */
$app->get("/queue/{bar_uuid}", function (Request $request, Response $response, $args) use ($pdo) {
    $songs = [];
    $query = "SELECT song.id, title, artist, album, genre, COUNT(song.id) AS votes FROM song INNER JOIN vote ON song.id = vote.song_id WHERE bar_uuid = 'testuuid' GROUP BY song.id ORDER BY votes DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$args["bar_uuid"]]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $songs[] = [
            "id" => $row["id"],
            "title" => $row["title"],
            "artist" => $row["artist"],
            "album" => $row["album"],
            "genre" => $row["genre"],
            "votes" => $row["votes"]
        ];
    }
    return $response->withJson($songs);
});

/*
 * Vote for a song, example data:
 *
 * device_uuid: "4td3tgstehws5ygth"
 */
$app->post("/vote/{song_id}", function (Request $request, Response $response, $args) {
    $params = $request->getQueryParams();
    $this->db->vote->insert()
        ->data([
            "song_id" => $args["song_id"],
            "device_uuid" => $params["device_uuid"]
        ])
        ->run();
    return $response;
});

/*
 * Get all songs
 */
$app->get("/{bar_uuid}", function (Request $request, Response $response, $args) {
    $songs = $this->db->song->select()
        ->where("bar_uuid = :bar_uuid", [":bar_uuid" => $args["bar_uuid"]])
        ->run();
    return $response->withJson($songs);
});

/*
 * Upload songs, example data:
 *
 * songs: [
 *     {title: "Bang My Head (feat. Sia & Fetty Wap)", artist: "David Guetta, Fetty Wap, Sia", album: "Listen Again", genre: "Pop"},
 *     {title: "Stay", artist: "Kygo, Maty Noyes", album: "Stay", genre: "Pop"},
 *     {title: "Love Yourself", artist: "Justin Bieber", album: "Purpose (Deluxe)", genre: "Pop"}
 * ]
 */
$app->post("/{bar_uuid}", function (Request $request, Response $response, $args) {
    $params = $request->getParsedBody();
    foreach ($params["songs"] as $song) {
        $this->db->song[] = [
            "title" => $song["title"],
            "artist" => $song["artist"],
            "album" => $song["album"],
            "genre" => $song["genre"],
            "bar_uuid" => $args["bar_uuid"]
        ];
    }
    return $response;
});

/*
 * Search for a song
 */
$app->get("/{bar_uuid}/{query}", function (Request $request, Response $response, $args) {
    $songs = $this->db->song->select()
        ->where("MATCH (title, artist, album) AGAINST(:query)", [":query" => $args["query"]])
        ->where("bar_uuid = :bar_uuid", [":bar_uuid" => $args["bar_uuid"]])
        ->run();
    return $response->withJson($songs);
});

unset($app->getContainer()['errorHandler']);
unset($app->getContainer()['phpErrorHandler']);
$app->run();