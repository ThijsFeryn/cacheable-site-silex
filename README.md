# Develop cacheable sites by levering HTTP
This piece of example code uses the [Silex](http://silex.sensiolabs.org/) framework to illustrate how you can leverage HTTP to develop cacheable sites.
 
The code uses the following HTTP concepts:
 
 * The use of `Cache-Control` headers using directives like `Public`, `Private` to decide which HTTP responses are cacheable and which are not
 * The use of `Cache-Control` headers using directives like `Max-Age` and `S-Maxage` to determine how long HTTP responses can be cached  
 * `Expires` headers as a fallback to control the *time-to-live*
 * Cache variations based on the `Vary` header
 * Conditional requests based on the `Etag` header
 * Returning an `HTTP 304` status code when content was successfully revalidated
 * Content negotiation and language selection based on the `Accept-Language` header
 * Block caching using [Edge Side Includes](https://www.w3.org/TR/esi-lang) and [HInclude](http://mnot.github.io/hinclude/)
 
## Cacheable
 
The output that this example code generates is highly cacheable. We don't keep track of state using cookies and the proper `Cache-Control` headers are used to store the output in an HTTP cache.
 
If a reverse caching proxy (like [Varnish](https://www.varnish-cache.org/)) is installed in front of this application, it will respect the *time-to-live* that was set by the application.

Reverse caching proxies will also create cache variations by respecting the `Vary` header. A separate version of the response is stored in cache per language.

Non-cacheable content blocks will not cause a full miss on the page. These content blocks are loaded separately using *ESI* or *HInclude*. Either of those techniques load blocks as a subrequest.

*ESI* tags are rendered by the reverse proxy, *HInclude* tags are loaded client-side by the browser. If the code notices that there's no *reverse caching proxy* in front of the application, it will render the output inline, without ESI.


> When you test this code, please have a look at the HTTP request headers and HTTP response headers.
> That's where the magic happens.

## How to install

The minimum PHP version requirement is `PHP 5.5.9`. All dependencies are loaded via [Composer](https://getcomposer.org), the PHP package manager. Dependency definition happens in the [composer.json](/composer.json) file:

```json
{
    "require": {
        "silex/silex": "^2.0",
        "twig/twig": "^1.27",
        "symfony/twig-bridge": "^3.1",
        "symfony/translation": "^3.1"
    }
}
```

Run `composer install` to install these dependencies. They will be stored in the *vendor* folder. They are bootstrapped in [index.php](/public/index.php) via `require_once __DIR__ . '/../vendor/autoload.php';
`

If you use *Apache* as your webserver, there's an [.htaccess](/public/.htaccess) file that routes all traffic for non-existent files and directories to [index.php](/public/index.php).

## Key components

The [index.php](/public/index.php) file is the controller of the application. It reads HTTP input and generates HTTP output.
Routes are matched via `$app->get()` callbacks.

Output is formatted using the [Twig](http://twig.sensiolabs.org) templating language. The [views](/views) folder contains the template file for each route:

* [footer.twig](/views/footer.twig) contains the footer template which returns a translated string and a timestamp
* [header.twig](/views/header.twig) contains the header template which also returns a translated string and a timestamp
* [index.twig](/views/index.twig) contains the main template where the header, footer, and navigation are loaded, either via *ESI* or via *HInclude*
* [nav.twig](/views/nav.twig) contains the navigation template

## Varnish

To see the impact of this code, I would advise you to install [Varnish](https://www.varnish-cache.org/). Varnish will respect the *HTTP response headers* that were set and will cache the output.

This is the minimum amount of [VCL code](https://www.varnish-cache.org/docs/4.1/reference/vcl.html#varnish-configuration-language) you need to make this work:

```
vcl 4.0;

backend default {
    .host = "localhost";
    .port = "8080";
}

sub vcl_recv {
    set req.http.Surrogate-Capability="key=ESI/1.0";
    if ((req.method != "GET" && req.method != "HEAD") || req.http.Authorization) {
        return (pass);
    }
    return(hash);
}

sub vcl_backend_response {
    if(beresp.http.Surrogate-Control~"ESI/1.0") {
        unset beresp.http.Surrogate-Control;
        set beresp.do_esi=true;
        return(deliver);
    }
}
```

**This piece of *VCL* code assumes that Varnis is installed on port 80 and your webserver on port 8080 on the same machine.**

## Disclaimer

This repository and its code are part of the code examples that are featured in my book **"Getting Started With Varnish Cache"**. 
It serves as a set of best practices that should encourage developers to control the cacheability of their sites themselves, instead of relying on infrastructure configuration.

More information about me:

* Visit my website: https://feryn.eu
* Follow me on Twitter: [@ThijsFeryn](https://twitter.com/ThijsFeryn)
* Follow me on Instagram: [@ThijsFeryn](https://instagram.com/ThijsFeryn)
* View my LinkedIn profile: http://linkedin.com/in/thijsferyn
* View my public speaking track record: https://talks.feryn.eu
* Read my blog: https://blog.feryn.eu