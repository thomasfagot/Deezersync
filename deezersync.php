#!/c/PHP/php
<?php
include_once(__DIR__.'/vendor/autoload.php');
include_once(__DIR__.'/functions.php');

use Symfony\Component\Dotenv\Dotenv;
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

switch (true) {
    case !empty($argv[1]) && $argv[1] === 'playlists':
        playlists();
        break;
    case !empty($argv[1]) && is_numeric($argv[1]):
        sync($argv[1]);
        break;
    default:
        die('Usage: deezersync [playlists|playlist_id]');
}
