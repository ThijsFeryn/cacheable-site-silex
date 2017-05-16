<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\HttpCacheServiceProvider;

if (php_sapi_name() == 'cli-server' && preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|ttf|woff|json|html|htm)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}

$app = new Silex\Application();
$app['locale'] = 'en';
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), ['twig.path' => __DIR__.'/../views']);
$app->register(new Silex\Provider\TranslationServiceProvider(), ['locale_fallbacks' => ['en','nl']]);
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new HttpFragmentServiceProvider());
$app->register(new HttpCacheServiceProvider());

$app->extend('translator', function($translator, $app) {
    $translator->addLoader('yaml', new YamlFileLoader());
    $translator->addResource('yaml', dirname(__DIR__).'/locales/en.yml', 'en');
    $translator->addResource('yaml',dirname( __DIR__).'/locales/nl.yml', 'nl');
    return $translator;
});

$app['credentials'] = [
    'admin' => '$2y$10$431rvq1qS9ewNFP0Gti/o.kBbuMK4zs8IDTLlxm5uzV7cbv8wKt0K'
];

$app->before(function (Request $request) use ($app){
    $request->setLocale($request->getPreferredLanguage());
    $app['translator']->setLocale($request->getPreferredLanguage());
});

$app->after(function(Request $request, Response $response) use ($app){
    $response->setVary('Accept-Language',false);
    $response->headers->set('Content-Length',strlen($response->getContent()));
});

$app->get('/', function () use($app) {
    $response =  new Response($app['twig']->render('index.twig'),200);
    $response
        ->setSharedMaxAge(500)
        ->setPublic();
    return $response;
})->bind('home');

$app->get('/header', function () use($app) {
    $response =  new Response($app['twig']->render('header.twig'),200);
    $response
        ->setSharedMaxAge(500)
        ->setPublic();
    return $response;
})->bind('header');
$app->get('/footer', function () use($app) {
    $response =  new Response($app['twig']->render('footer.twig'),200);
    $response
        ->setSharedMaxAge(500)
        ->setPublic();
    return $response;
})->bind('footer');
$app->get('/nav', function (Request $request) use($app) {
    if($app['session']->has('username')) {
        $loginLogoutUrl = $app['url_generator']->generate('logout');
        $loginLogoutLabel = 'log_out';
    } else {
        $loginLogoutUrl = $app['url_generator']->generate('login');
        $loginLogoutLabel = 'log_in';
    }
    $response =  new Response($app['twig']->render('nav.twig',['loginLogoutUrl'=>$loginLogoutUrl,'loginLogoutLabel'=>$loginLogoutLabel]),200);
    $response->headers->addCacheControlDirective('no-store', true);
    $response->headers->addCacheControlDirective('no-cache', true);
    $response
        ->setSharedMaxAge(0)
        ->setPrivate();
    return $response;
})->bind('nav');


$app->get('/login', function (Request $request) use($app) {
    if($app['session']->has('username')) {
        return new RedirectResponse($app['url_generator']->generate('home'));
    }
    $loginLogoutUrl = $app['url_generator']->generate('login');
    $loginLogoutLabel = 'log_in';
    $response =  new Response($app['twig']->render('login.twig',['loginLogoutUrl'=>$loginLogoutUrl,'loginLogoutLabel'=>$loginLogoutLabel]),200);
    $response
        ->setSharedMaxAge(500)
        ->setPublic();
    return $response;
})->bind('login');

$app->get('/logout', function () use($app) {
    $response = new RedirectResponse($app['url_generator']->generate('login'));
    $app['session']->invalidate();
    return $response;
})->bind('logout');

$app->post('/login', function (Request $request) use($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if(!$username || !$password || !isset($app['credentials'][$username]) || !password_verify($password,$app['credentials'][$username])) {
        return new RedirectResponse($app['url_generator']->generate('login'));
    }

    $app['session']->set('username',$username);
    $response = new RedirectResponse($app['url_generator']->generate('home'));
    return $response;
})->bind('loginpost');

$app->get('/private', function () use($app) {
    if(!$app['session']->has('username')) {
        return new RedirectResponse($app['url_generator']->generate('login'));
    }
    $response =  new Response($app['twig']->render('private.twig'),200);
    $response->headers->addCacheControlDirective('no-store', true);
    $response->headers->addCacheControlDirective('no-cache', true);
    $response
        ->setSharedMaxAge(0)
        ->setPrivate();
    return $response;
})->bind('private');

$app->run();
