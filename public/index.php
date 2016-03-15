<?php
if (php_sapi_name() == 'cli-server') {
    if (preg_match('/\.(?:png|jpg|jpeg|gif|js|css)$/', $_SERVER["REQUEST_URI"])) {
        return false;
    }
}

require '../vendor/autoload.php';

ini_set('memory_limit','1G');

Odango\OdangoPhp\Registry::setStash(new Stash\Pool(new Stash\Driver\Sqlite()));
Odango\OdangoPhp\Registry::setDatabase(new Ark\Database\Connection('pgsql:host=localhost;port=5432;dbname=odango', 'eater', 'test'));
Odango\OdangoPhp\Registry::setNyaa(new Odango\OdangoPhp\Nyaa\DatabaseWithMeta());

$loader = new Twig_Loader_Filesystem(__DIR__ . '/../storage/views/');
$twig = new Twig_Environment($loader, array(
    'debug' => true,
    'cache' => '/tmp/twig/',
));
$twig->addExtension(new Twig_Extension_Debug());

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
            'query'   => $request->getParam('q'),
            'searched' => $titles,
            'results'  => $array
        ])
    );
});

$app->get('/series/{query}/{hash}', function ($request, $response, $args) use ($twig) {
    $nyaaCollector = new Odango\OdangoPhp\NyaaCollector();
    $results = $nyaaCollector->collect($args['query'], [ 'category' => '1_37' ]);
    $series = $results[$args['query'] . '-' . $args['hash']];

    return $response->write($twig->render('series.html', [
        'series' => $series->toArray()
    ]));
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

    $this->get('meta', function ($request, $response, $args) {
        $title = $request->getParam('title');
        $meta = Odango\OdangoPhp\NyaaMeta::createFromTitle($title);

        return $response->write(json_encode($meta->toArray()));
    });

})->add(function ($request, $response, $next) {
    $next($request, $response);
    $response = $response->withHeader('Content-Type', 'application/json');
    return $response;
});

$app->run();
