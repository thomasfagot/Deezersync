<?php

function sanitizePath($string) {
    return preg_replace('/[:\\><\/"\?\|\*]/', '', $string);
}

function playlists() {
    $response = request('https://api.deezer.com/user/' . getenv('deezer_user_id') . '/playlists', true);
    if (!empty($response['data'])) {
        foreach ($response['data'] as $playlist) {
            echo implode(' - ', [$playlist['id'], $playlist['title'], $playlist['nb_tracks'] . ' tracks']) . PHP_EOL;
        }
    }
}

function sync(string $playlist_id) {
    $url = 'https://api.deezer.com/playlist/' . $playlist_id . '/tracks?index=0';
    while ($url) {
        $response = request($url, true);
        if (empty($response)) {
            die();
        }
        $url = $response['next'] ?? false;
        foreach ($response['data'] as $track) {
            $path = preg_replace('#//+#', '/', getenv('music_folder') . '/' . sanitizePath($track['artist']['name']) . '/' . $track['album']['title'] . '/' . sanitizePath($track['title']) . '.mp3');
            if (!file_exists($path)) {
                $search_result = request(
                        'https://www.googleapis.com/youtube/v3/search'
                        . '?q=' . urlencode($track['artist']['name'] . ' ' . $track['title'])
                        . '&maxResults=1'
                        . '&part=snippet'
                        . '&key=' . getenv('youtube_api_key')
                        . '&type=video', true
                );
                if (!empty($search_result['items'][0]['id']['videoId'])) {
                    downloadYoutube($search_result['items'][0]['id']['videoId'], $path, $track);
                }
            }
            die();
        }
    }
}

function request(string $url, bool $json = false) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $result = curl_exec($ch);
    if ($result === false || ($error = curl_error($ch))) {
        echo ($error ?? 'Request failed.') . PHP_EOL;
        $result = '';
    }
    curl_close($ch);
    return $json ? json_decode($result, true) : $result;
}

function downloadYoutube(string $youtube_id, string $path, array $track) {
    $dir = str_replace(basename($path), '', $path);
    if (!file_exists($dir) && !mkdir($dir, 0644, true)) {
        echo 'Could not create folder ' . $dir . '.' . PHP_EOL;
        return false;
    }
    echo 'Downloading: ' . $path . PHP_EOL;
    exec('python "' . getenv('youtube_dl_path') . '" ' . escapeshellarg('https://www.youtube.com/watch?v=' . $youtube_id) . ' --no-playlist --ignore-config --extract-audio --audio-format=mp3 --audio-quality=0 "--output="%(id)s.%(ext)s"', $output, $return);
    if ($return != 0) {
        die(implode("\n", $output) . PHP_EOL);
    }
    echo 'Downloaded.' . PHP_EOL;
    if (!rename(__DIR__ . '/' . $youtube_id . '.mp3', $path)) {
        echo 'Could not move from "' . __DIR__ . '/' . $youtube_id . '.mp3" to "' . $path . '"' . PHP_EOL;
    } else {
        setMetadata($path, $track);
    }
}

function setMetadata(string $path, array $track) {
    static $getID3 = null;
    if ($getID3 === null) {
        $getID3 = new getID3;
        $getID3->setOption(['encoding' => 'UTF-8']);
        getid3_lib::IncludeDependency(GETID3_INCLUDEPATH . 'write.php', __FILE__, true);
    }

    //TODO
    
}
