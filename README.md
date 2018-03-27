# WP Post Image Watermarks

Our supplemental member-only content requires users to be a Silver member or above, but these posts also sometimes get listed in other places (on the homepage, in email newsletters, sometimes in sidebars).

In Drupal, we have a library that works like this:

1. When an image is uploaded to an article, check if the article has a member level restriction on it.
2. If it does, check which icon style is selected.
3. Put the corresponding MP+ png on top of the image, position it, and save it.

It runs on the thumbnail images that appear in the above locations.

On [this article](https://www.minnpost.com/minnpost-picks/2018/03/minnpost-picks-civil-and-digital-rights-creative-cursing-and-saying-goodbye-p), it works like this:

![example MP+ overlay](https://www.minnpost.com/sites/default/files/imagecache/feature/images/thumbnails/articles/PortlandiaThumb.jpg)
