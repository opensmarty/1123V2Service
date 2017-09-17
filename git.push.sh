#!/bin/bash
cd /home/opensmarty/www/1123V2Service
git pull origin master
git add .
git commit -m "$1"
git push origin master
