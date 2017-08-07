vcl 4.0;

import digest;
import std;
import cookie;
import var;

backend default {
    .host = "localhost";
    .port = "8080";
    .probe = {
         .url = "/";
         .interval = 5s;
         .timeout = 5s;
         .window = 5;
         .threshold = 3;
     }
}


sub vcl_recv {
    var.set("key","SlowWebSitesSuck");
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
    call jwt;
    if(req.url == "/private" && req.http.X-Login != "true") {
        return(synth(302,"/logout"));
    }
    return(hash);
}

sub vcl_backend_response {
    set beresp.http.x-host = bereq.http.host;
    set beresp.http.x-url = bereq.url;
    if(beresp.http.Surrogate-Control~"ESI/1.0") {
        unset beresp.http.Surrogate-Control;
        set beresp.do_esi=true;
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
    unset req.http.X-Login;
    if(req.http.cookie ~ "^([^;]+;[ ]*)*token=[^\.]+\.[^\.]+\.[^\.]+([ ]*;[^;]+)*$") {
        std.log("Token cookie found");
        cookie.parse(req.http.cookie);
        cookie.filter_except("token");
        var.set("token", cookie.get("token"));
        var.set("header", regsub(var.get("token"),"([^\.]+)\.[^\.]+\.[^\.]+","\1"));
        var.set("type", regsub(digest.base64url_decode(var.get("header")),{"^.*?"typ"\s*:\s*"(\w+)".*?$"},"\1"));
        var.set("algorithm", regsub(digest.base64url_decode(var.get("header")),{"^.*?"alg"\s*:\s*"(\w+)".*?$"},"\1"));

        if(var.get("type") != "JWT" || var.get("algorithm") != "HS256") {
            return(synth(400, "Invalid token"));
        }

        var.set("rawPayload",regsub(var.get("token"),"[^\.]+\.([^\.]+)\.[^\.]+$","\1"));
        var.set("signature",regsub(var.get("token"),"^[^\.]+\.[^\.]+\.([^\.]+)$","\1"));
        var.set("currentSignature",digest.base64url_nopad_hex(digest.hmac_sha256(var.get("key"),var.get("header") + "." + var.get("rawPayload"))));
        var.set("payload", digest.base64url_decode(var.get("rawPayload")));
        var.set("exp",regsub(var.get("payload"),{"^.*?"exp"\s*:\s*(\w+).*?$"},"\1"));
        var.set("username",regsub(var.get("payload"),{"^.*?"sub"\s*:\s*"(\w+)".*?$"},"\1"));

        if(var.get("signature") != var.get("currentSignature")) {
            return(synth(400, "Invalid token"));
        }

        if(var.get("username") ~ "^\w+$") {
            std.log("Username: " + var.get("username"));
            if(std.time(var.get("exp"),now) >= now) {
                std.log("JWT not expired");
                set req.http.X-Login="true";
            } else {
            set req.http.X-Login="false";
                std.log("JWT expired");
            }
        }
    }
}
