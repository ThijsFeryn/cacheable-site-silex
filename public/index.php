<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\HttpCacheServiceProvider;

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), ['twig.path' => __DIR__.'/../views']);
$app->register(new HttpFragmentServiceProvider());
$app->register(new HttpCacheServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), ['locale_fallbacks' => ['en']]);

$app['locale'] = 'en';
$app['translator.domains'] = [
    'messages' => [
        'en' => [
            'welcome'     => 'Welcome to the site',
            'rendered'    => 'Rendered at %date%',
            'example'     => 'An example page'
        ],
        'nl' => [
            'welcome'     => 'Welkom op de site',
            'rendered'    => 'Samengesteld op %date%',
            'example'     => 'Een voorbeeldpagina'
        ]
    ]
];

$app->before(function (Request $request) use ($app){
    $app['translator']->setLocale($request->getPreferredLanguage());
});

$app->after(function(Request $request, Response $response) use ($app){
    $date = new DateTime();
    $date->add(new DateInterval('PT'.$response->getTtl().'S'));
    $response
        ->setExpires($date)
        ->setVary('Accept-Language')
        ->setETag(md5($response->getContent()))
        ->isNotModified($request);
});

$app->get('/', function () use($app) {
    $response =  new Response($app['twig']->render('index.twig'),200);
    $response
        ->setSharedMaxAge(5)
        ->setPublic();
    return $response;
})->bind('home');

$app->get('/header', function () use($app) {
    $response =  new Response($app['twig']->render('header.twig'),200);
    $response
        ->setPrivate()
        ->setSharedMaxAge(0);
    return $response;
})->bind('header');

$app->get('/footer', function () use($app) {
    $response =  new Response($app['twig']->render('footer.twig'),200);
    $response
        ->setSharedMaxAge(10)
        ->setPublic();
    return $response;
})->bind('footer');

$app->get('/nav', function () use($app) {
    $response =  new Response($app['twig']->render('nav.twig'),200);
    $response
        ->setSharedMaxAge(20)
        ->setPublic();
    return $response;
})->bind('nav');

$app->run();
