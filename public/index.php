<?php
if (php_sapi_name() == 'cli-server') {
    if (preg_match('/\.(?:png|jpg|jpeg|gif|js|css)$/', $_SERVER["REQUEST_URI"])) {
        return false;
    }
}

require '../vendor/autoload.php';

Odango\OdangoPhp\Registry::setStash(new Stash\Pool(new Stash\Driver\Sqlite()));
Odango\OdangoPhp\Registry::setDatabase(new Ark\Database\Connection('mysql:dbname=odango', 'root'));
Odango\OdangoPhp\Registry::setNyaa(new Odango\OdangoPhp\Nyaa\Database());

$loader = new Twig_Loader_Filesystem(__DIR__ . '/../storage/views/');
$twig = new Twig_Environment($loader, array(
    'debug' => true,
    'cache' => '/tmp/twig/',
));

$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];
$c = new \Slim\Container($configuration);
$app = new \Slim\App($c);

$app->get('/', function ($request, $response) use ($twig) {
    return $response->write($twig->render('index.html'));
});

$app->get('/collect', function ($request, $response) use ($twig) {
    return $response->write($twig->render('collect.html', [
        'query' => $request->getParam('q')
    ]));
});

$app->get('/part/collect', function ($request, $response) use ($twig) {
    $nyaaCollector = new Odango\OdangoPhp\NyaaCollector();
    $aniDbTitles = Odango\OdangoPhp\AniDbTitles::construct();
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

    return $response->write(
        $twig->render('part_collect.html', [
            'searched' => $titles,
            'results'  => $array
        ])
    );
});

$app->group('/json/v1/', function () {

    $this->get('', function ($request, $response) {
        return $response->write('{"version":"1.0"}');
    });

    $this->get('autocomplete', function ($request, $response) {
        $aniDbTitles = Odango\OdangoPhp\AniDbTitles::construct();
        return $response->write(json_encode($aniDbTitles->autocomplete($request->getParam('q', ''))));
    });

    $this->get('collect', function ($request, $response) {
        $nyaaCollector = new Odango\OdangoPhp\NyaaCollector();
        $aniDbTitles = Odango\OdangoPhp\AniDbTitles::construct();
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
})->add(function ($request, $response, $next) {
    $next($request, $response);
    $response = $response->withHeader('Content-Type', 'application/json');
    return $response;
});

$app->run();
