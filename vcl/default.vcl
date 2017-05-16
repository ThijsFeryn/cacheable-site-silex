vcl 4.0;

import std;

backend default {
    .host = "localhost";
    .port = "8080";
}

sub vcl_recv {
    set req.url = std.querysort(req.url);

    if(req.http.accept-language ~ "^\s*(nl)") {
        set req.http.accept-language = regsub(req.http.accept-language,"^\s*(nl).*$","\1");
    } else {
        set req.http.accept-language = "en";
    }

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