<?php

require '../vendor/autoload.php';

Odango\Registry::setStash(new Stash\Pool(new Stash\Driver\Sqlite()));
Odango\Registry::setDatabase(new Ark\Database\Connection('mysql:dbname=odango', 'root'));
Odango\Registry::setNyaa(new Odango\Nyaa\Database());

$app = new Slim\App;

$app->get('/', function ($request, $response) {
    return $response->write('Hallo wereld!');
});

$app->group('/json/v1/', function () {
    $this->add(function ($request, $response, $next) {
        $next($request, $response);
        $response = $response->withHeader('Content-Type', 'application/json');
        return $response;
    });

    $this->get('/', function ($request, $response) {
        return $response->write('{"version":"1.0"}');
    });

    $this->get('/autocomplete', function ($request, $response) {
        $aniDbTitles = Odango\AniDbTitles::construct();
        return $response->write(json_encode($aniDbTitles->autocomplete($request->getParam('q', ''))));
    });

    $this->get('/collect', function ($request, $response) {
        $nyaaCollector = new Odango\NyaaCollector();
        $aniDbTitles = Odango\AniDbTitles::construct();
        $titles = $aniDbTitles->getAlternativeTitles($request->getParam('q'));
        if (empty($titles)) {
            $titles = [$request->getParam('q')];
        }

        $array = [];

        foreach ($titles as $title) {
            $array += array_map(function ($set) {
                return $set->toArray();
            }, $nyaaCollector->collect($title, [ 'category' => '1_37' ]));
        }

        return $response->write(json_encode(
            [
                'searched' => $titles,
                'results' => $array
            ]
        ));
    });
});

$app->run();
