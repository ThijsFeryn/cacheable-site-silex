#!/bin/bash
head=$(dirname "$0")"/../vcl"
cd "$head"
sudo /usr/local/sbin/varnishd -s malloc,100m -F -a 127.0.0.1:80 -T 127.0.0.1:6082 -f ../vcl/default.vcl -p vsl_mask=+Hash -n `pwd`