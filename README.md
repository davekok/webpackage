davekok/webpackage
================================================================================

This is a simple packaging format for packaging static web resources like HTML, CSS, Javascript and image files. It is a semi binary format, as not all web files are text based. Still it can be parsed as if it is a text file. Please make sure you don't make too large web packages. Split them up if they get too large.

A web server, API gateway or web controller may support uploading web packages and then serve the files from the web package from the respective URLs. Allowing for a graceful way of dealing with static files. Other approaches are abusing persistent storage or putting the static files in a container with web server, API gateway or web controller. In this way static files can be packaged separately and uploaded later, without having to restart or redeploy or abuse system features to serve the static resources.
