vcl 4.0;

import digest;
import std;

backend default {
    .host = "localhost";
    .port = "8080";
}

sub vcl_recv {
    set req.url = std.querysort(req.url);
    call lang;
    set req.http.Surrogate-Capability="key=ESI/1.0";
    if ((req.method != "GET" && req.method != "HEAD") || req.http.Authorization) {
        return (pass);
    }
    call jwt;
    if(req.url == "/private" && req.http.X-Login != "true") {
        return(synth(302,"/login"));
    }
    return(hash);
}

sub vcl_backend_response {
    set beresp.http.x-host = bereq.http.host;
    set beresp.http.x-url = bereq.url;
    if(beresp.http.Surrogate-Control~"ESI/1.0") {
        unset beresp.http.Surrogate-Control;
        set beresp.do_esi=true;
        return(deliver);
    }
}

sub vcl_deliver {
    unset resp.http.x-host;
    unset resp.http.x-url;
    unset resp.http.vary;
}

sub vcl_synth {
    if (resp.status == 301 || resp.status == 302) {
        set resp.http.location = resp.reason;
        set resp.reason = "Moved";
        return (deliver);
    }
}

sub jwt {
    if(req.http.cookie ~ "^([^;]+;[ ]*)*token=[^\.]+\.[^\.]+\.[^\.]+([ ]*;[^;]+)*$") {
        set req.http.x-token = ";" + req.http.Cookie;
        set req.http.x-token = regsuball(req.http.x-token, "; +", ";");
        set req.http.x-token = regsuball(req.http.x-token, ";(token)=","; \1=");
        set req.http.x-token = regsuball(req.http.x-token, ";[^ ][^;]*", "");
        set req.http.x-token = regsuball(req.http.x-token, "^[; ]+|[; ]+$", "");

        set req.http.tmpHeader = regsub(req.http.x-token,"token=([^\.]+)\.[^\.]+\.[^\.]+","\1");
        set req.http.tmpTyp = regsub(digest.base64url_decode(req.http.tmpHeader),{"^.*?"typ"\s*:\s*"(\w+)".*?$"},"\1");
        set req.http.tmpAlg = regsub(digest.base64url_decode(req.http.tmpHeader),{"^.*?"alg"\s*:\s*"(\w+)".*?$"},"\1");

        if(req.http.tmpTyp != "JWT") {
            return(synth(400, "Token is not a JWT: " + req.http.tmpHeader));
        }
        if(req.http.tmpAlg != "HS256") {
            return(synth(400, "Token does not use HS256 hashing"));
        }

        set req.http.tmpPayload = regsub(req.http.x-token,"token=[^\.]+\.([^\.]+)\.[^\.]+$","\1");

        set req.http.tmpRequestSig = regsub(req.http.x-token,"^[^\.]+\.[^\.]+\.([^\.]+)$","\1");
        set req.http.tmpCorrectSig = digest.base64url_nopad_hex(digest.hmac_sha256("SlowWebSitesSuck",req.http.tmpHeader + "." + req.http.tmpPayload));

        if(req.http.tmpRequestSig != req.http.tmpCorrectSig) {
            return(synth(403, "Invalid JWT signature"));
        }

        set req.http.tmpPayload = digest.base64url_decode(req.http.tmpPayload);
        set req.http.X-Login = regsub(req.http.tmpPayload,{"^.*?"login"\s*:\s*(\w+).*?$"},"\1");
        set req.http.X-Username = regsub(req.http.tmpPayload,{"^.*?"sub"\s*:\s*"(\w+)".*?$"},"\1");

        unset req.http.tmpHeader;
        unset req.http.tmpTyp;
        unset req.http.tmpAlg;
        unset req.http.tmpPayload;
        unset req.http.tmpRequestSig;
        unset req.http.tmpCorrectSig;
        unset req.http.tmpPayload;

        std.log("X-Login: " + req.http.X-Login);
    }
}

sub lang {
    if(req.http.accept-language ~ "^\s*(nl)") {
        set req.http.accept-language = regsub(req.http.accept-language,"^\s*(nl).*$","\1");
    } else {
        set req.http.accept-language = "en";
    }
    std.log("Accept-Language: " + req.http.accept-language);
}