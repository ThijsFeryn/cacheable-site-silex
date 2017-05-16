<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;

if (php_sapi_name() == 'cli-server' && preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|ttf|woff|json|html|htm)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}

$app = new Silex\Application();
$app['locale'] = 'en';
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), ['twig.path' => __DIR__.'/../views']);
$app->register(new Silex\Provider\TranslationServiceProvider(), ['locale_fallbacks' => ['en','nl']]);
$app->register(new Silex\Provider\SessionServiceProvider());

$app['translator.domains'] = [
    'messages' => [
        'en' => [
            'home'        => 'Home',
            'welcome'     => 'Welcome to the site',
            'rendered'    => 'Rendered at %date%',
            'example'     => 'An example page',
            'log_in'      => 'Log in',
            'login'       => 'Login',
            'log_out'     => 'Log out',
            'username'    => 'Username',
            'password'    => 'Password',
            'private'     => 'Private',
            'privatetext' => 'Looks like some very private data',
        ],
        'nl' => [
            'home'        => 'Start',
            'welcome'     => 'Welkom op de site',
            'rendered'    => 'Samengesteld op %date%',
            'example'     => 'Een voorbeeldpagina',
            'log_in'      => 'Inloggen',
            'login'       => 'Login',
            'log_out'     => 'Uitloggen',
            'username'    => 'Gebruikersnaam',
            'password'    => 'Wachtwoord',
            'private'     => 'Privé',
            'privatetext' => 'Deze tekst ziet er vrij privé uit',
        ]
    ]
];

$app['credentials'] = [
    'admin' => '$2y$10$431rvq1qS9ewNFP0Gti/o.kBbuMK4zs8IDTLlxm5uzV7cbv8wKt0K'
];

$app->before(function (Request $request) use ($app){
    $request->setLocale($request->getPreferredLanguage());
    $app['translator']->setLocale($request->getPreferredLanguage());
    if($request->getRequestUri() == $app['url_generator']->generate('private')) {
        $response = new RedirectResponse($app['url_generator']->generate('login'));
        return $response;
    }
});

$app->after(function(Request $request, Response $response) use ($app){
    $response->headers->set('Content-Length',strlen($response->getContent()));
});

$app->get('/', function () use($app) {
    if($app['session']->get('logged_in')) {
        $loginLogoutUrl = $app['url_generator']->generate('logout');
        $loginLogoutLabel = 'log_out';
    } else {
        $loginLogoutUrl = $app['url_generator']->generate('login');
        $loginLogoutLabel = 'log_in';
    }
    $response =  new Response($app['twig']->render('index.twig',['loginLogoutUrl'=>$loginLogoutUrl,'loginLogoutLabel'=>$loginLogoutLabel]),200);
    return $response;
})->bind('home');

$app->get('/login', function (Request $request) use($app) {
    if($app['session']->get('logged_in')) {
        return new RedirectResponse($app['url_generator']->generate('home'));
    }
    $loginLogoutUrl = $app['url_generator']->generate('login');
    $loginLogoutLabel = 'log_in';
    $response =  new Response($app['twig']->render('login.twig',['loginLogoutUrl'=>$loginLogoutUrl,'loginLogoutLabel'=>$loginLogoutLabel]),200);
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

    $app['session']->set('logged_in',true);
    $app['session']->set('username',$username);
    $response = new RedirectResponse($app['url_generator']->generate('home'));
    return $response;
})->bind('loginpost');

$app->get('/private', function () use($app) {
    $response =  new Response($app['twig']->render('private.twig'),200);
    return $response;
})->bind('private');

$app->run();
