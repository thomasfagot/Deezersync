<?php
function sanitizePath($string)
{
    return trim(str_replace(['/', ':', '\\', '>', '<', '?', '|', '*', '"', '...', '..'], '', $string), " \t\n\r\0\x0B.");
}

function playlists()
{
    $response = request('https://api.deezer.com/user/'.getenv('deezer_user_id').'/playlists', true);
    if (!empty($response['data'])) {
        foreach ($response['data'] as $playlist) {
            echo implode(' - ', [$playlist['id'], $playlist['title'], $playlist['nb_tracks'].' tracks']).PHP_EOL;
        }
    }
}

function sync(string $playlist_id)
{
    $url = 'https://api.deezer.com/playlist/'.$playlist_id.'/tracks?index=0';
    while ($url) {
        $response = request($url, true);
        if (empty($response)) {
            die();
        }
        $url = $response['next'] ?? false;
        foreach ($response['data'] as $track) {
            $path = str_replace(['//', '  '], ['/', ' '], getenv('music_folder').'/'.sanitizePath($track['artist']['name']).'/'.sanitizePath($track['album']['title']).'/'.sanitizePath($track['title']).'.mp3');
            if (!file_exists($path)) {
                $search_result = request(
                    'https://www.googleapis.com/youtube/v3/search'
                    .'?q='.urlencode($track['artist']['name'].' '.$track['title'])
                    .'&maxResults=1'
                    .'&part=snippet'
                    .'&key='.getenv('youtube_api_key')
                    .'&type=video', true
                );
                if (!empty($search_result['items'][0]['id']['videoId'])) {
                    downloadYoutube($search_result['items'][0]['id']['videoId'], $path, $track);
                }
            }
        }
    }
    echo 'Done.'.PHP_EOL;
}

function request(string $url, bool $json = false)
{
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
        echo ($error ?? 'Request failed.').PHP_EOL;
        $result = '';
    }
    curl_close($ch);
    return $json ? json_decode($result, true) : $result;
}

function downloadYoutube(string $youtube_id, string $path, array $track)
{
    $dir = str_replace(basename($path), '', $path);
    if (!file_exists($dir) && !mkdir($dir, 0644, true)) {
        echo 'Could not create folder '.$dir.'.'.PHP_EOL;
    } else {
        echo 'Downloading: '.$path.PHP_EOL;
        exec(
            'python "'.getenv('youtube_dl_path').'" '
            .escapeshellarg('https://www.youtube.com/watch?v='.$youtube_id)
            .' --no-playlist --ignore-config --extract-audio --audio-format=mp3 --audio-quality=0 '.
            '"--output="data/%(id)s.%(ext)s"', $output, $return
        );
        if ($return != 0) {
            die(implode("\n", $output).PHP_EOL);
        }
        if (!rename(__DIR__.'/data/'.$youtube_id.'.mp3', $path)) {
            echo 'Could not move from "'.__DIR__.'/'.$youtube_id.'.mp3" to "'.$path.'"'.PHP_EOL;
        } else {
            setMetadata($path, $track);
        }
    }
}

function setMetadata(string $path, array $track)
{
    static $tagwriter = null;
    if ($tagwriter === null) {
        $getID3 = new getID3;
        $getID3->setOption(['encoding' => 'UTF-8']);
        getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'write.php', __FILE__, true);
        $tagwriter = new getid3_writetags;
        $tagwriter->tagformats = ['id3v2.3'];
        $tagwriter->overwrite_tags = true;
        $tagwriter->tag_encoding = 'UTF-8';
    }

    $tagwriter->filename = $path;
    $tagwriter->tag_data = [
        'title' => [$track['title'] ?? ''],
        'album' => [$track['album']['title'] ?? ''],
        'artist' => [$track['artist']['name'] ?? ''],
        'track' => [$track['track_position'] ?? ''],
    ];

    if (!$tagwriter->WriteTags()) {
        echo 'Failed writing tags to "'.$path.'".'.PHP_EOL;
    }
}
