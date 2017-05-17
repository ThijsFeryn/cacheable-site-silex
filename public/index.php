<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\HttpCacheServiceProvider;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Firebase\JWT\JWT;

if (php_sapi_name() == 'cli-server' && preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|ttf|woff|json|html|htm)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}

$app = new Silex\Application();
$app['locale'] = 'en';
$app['debug'] = true;
$app['jwtKey'] = 'SlowWebSitesSuck';
$app->register(new Silex\Provider\TwigServiceProvider(), ['twig.path' => __DIR__.'/../views']);
$app->register(new Silex\Provider\LocaleServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), ['locale_fallbacks' => ['en','nl']]);
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new HttpFragmentServiceProvider());
$app->register(new HttpCacheServiceProvider());

$app->extend('translator', function(Translator $translator, $app) {
    $translator->addLoader('yaml', new YamlFileLoader());
    $translator->addResource('yaml', dirname(__DIR__).'/locales/en.yml', 'en');
    $translator->addResource('yaml',dirname( __DIR__).'/locales/nl.yml', 'nl');
    return $translator;
});

$app['credentials'] = [
    'admin' => '$2y$10$431rvq1qS9ewNFP0Gti/o.kBbuMK4zs8IDTLlxm5uzV7cbv8wKt0K'
];

$app['jwtEncode'] = function() use ($app){
    return function($username) use ($app) {
        return JWT::encode([
            'sub'=>$username,
            'login'=>true,
        ],$app['jwtKey']);
    };
};

$app['jwtValidate'] = function() use ($app) {
    return function($token) use ($app) {
        try {
            $data = JWT::decode($token,$app['jwtKey'],['HS256']);
            $data = (array)$data;
            if(!isset($app['credentials'][$data['sub']])) {
                return false;
            }
            return true;
        } catch(UnexpectedValueException $e) {
            return false;
        }
    };
};

$app->before(function (Request $request) use ($app){
    $request->setLocale($request->getPreferredLanguage());
    $app['translator']->setLocale($request->getPreferredLanguage());
    if($request->getRequestUri() == $app['url_generator']->generate('private')
        && !$app['jwtValidate']($request->cookies->get('token'))) {
        $response = new RedirectResponse($app['url_generator']->generate('login'));
        $response
            ->setPublic()
            ->setSharedMaxAge(500);
    }
});

$app->after(function(Request $request, Response $response) use ($app){
    $response
        ->setETag(md5($response->getContent()))
        ->setVary('Accept-Language',false)
        ->isNotModified($request);
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
        ->setVary('X-Login',false)
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
    if($app['jwtValidate']($request->cookies->get('token'))) {
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
        ->setVary('X-Login',false)
        ->setSharedMaxAge(500)
        ->setPublic();
    return $response;
})->bind('nav');


$app->get('/login', function (Request $request) use($app) {
    if($app['jwtValidate']($request->cookies->get('token'))) {
        return new RedirectResponse($app['url_generator']->generate('home'));
    }
    $loginLogoutUrl = $app['url_generator']->generate('login');
    $loginLogoutLabel = 'log_in';
    $response =  new Response($app['twig']->render('login.twig',['loginLogoutUrl'=>$loginLogoutUrl,'loginLogoutLabel'=>$loginLogoutLabel]),200);
    $response
        ->setSharedMaxAge(500)
        ->setVary('X-Login',false)
        ->setPublic();
    return $response;
})->bind('login');

$app->get('/logout', function () use($app) {
    $response = new RedirectResponse($app['url_generator']->generate('login'));
    $response->headers->clearCookie('token');
    return $response;
})->bind('logout');

$app->post('/login', function (Request $request) use($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if(!$username || !$password || !isset($app['credentials'][$username]) || !password_verify($password,$app['credentials'][$username])) {
        return new RedirectResponse($app['url_generator']->generate('login'));
    }

    $cookie = new Cookie("token",$app['jwtEncode']($username), time() + (3600 * 48), '/', null, false, false);
    $response = new RedirectResponse($app['url_generator']->generate('home'));
    $response->headers->setCookie($cookie);
    return $response;
})->bind('loginpost');

$app->get('/private', function () use($app) {
    if(!$app['session']->has('username')) {
        return new RedirectResponse($app['url_generator']->generate('login'));
    }
    $response =  new Response($app['twig']->render('private.twig'),200);
    $response
        ->setSharedMaxAge(500)
        ->setPublic();
    return $response;
})->bind('private');

$app->run();
